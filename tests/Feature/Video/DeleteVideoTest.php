<?php

namespace Tests\Feature\Video;

use App\Enums\VideoStatus;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeleteVideoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.default' => 'public']);
        Storage::fake('public');

        Role::findOrCreate('owner', 'web');
        Role::findOrCreate('member', 'web');
    }

    public function test_owner_can_delete_video_and_output_assets(): void
    {
        $owner = $this->createOwner();
        $videoUuid = (string) Str::uuid();

        $video = Video::query()->create([
            'uuid' => $videoUuid,
            'user_id' => $owner->id,
            'title' => 'Demo video',
            'original_path' => 's3://media-platform-private-bucket/media-platform/source_videos/'.$videoUuid.'.mp4',
            'hls_path' => 'output_videos/'.$videoUuid.'/playlist.m3u8',
            'status' => VideoStatus::Ready,
        ]);

        Storage::disk('public')->put('output_videos/'.$videoUuid.'/playlist.m3u8', "#EXTM3U\n");
        Storage::disk('public')->put('output_videos/'.$videoUuid.'/segment-00001.ts', 'segment');

        $response = $this
            ->withHeaders($this->authorizationHeaders($owner))
            ->deleteJson('/api/v1/videos/'.$video->uuid);

        $response
            ->assertOk()
            ->assertJsonPath('type', 'info')
            ->assertJsonPath('message', 'Video deleted');

        $this->assertDatabaseMissing('videos', [
            'id' => $video->id,
        ]);
        Storage::disk('public')->assertMissing('output_videos/'.$videoUuid.'/playlist.m3u8');
        Storage::disk('public')->assertMissing('output_videos/'.$videoUuid.'/segment-00001.ts');
    }

    public function test_processing_video_cannot_be_deleted(): void
    {
        $owner = $this->createOwner();
        $videoUuid = (string) Str::uuid();

        $video = Video::query()->create([
            'uuid' => $videoUuid,
            'user_id' => $owner->id,
            'title' => 'Processing video',
            'original_path' => 's3://media-platform-private-bucket/media-platform/source_videos/'.$videoUuid.'.mp4',
            'hls_path' => 'output_videos/'.$videoUuid.'/playlist.m3u8',
            'status' => VideoStatus::Processing,
        ]);

        Storage::disk('public')->put('output_videos/'.$videoUuid.'/playlist.m3u8', "#EXTM3U\n");

        $response = $this
            ->withHeaders($this->authorizationHeaders($owner))
            ->deleteJson('/api/v1/videos/'.$video->uuid);

        $response
            ->assertConflict()
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Video is processing and cannot be deleted yet.');

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
        ]);
        Storage::disk('public')->assertExists('output_videos/'.$videoUuid.'/playlist.m3u8');
    }

    public function test_member_cannot_delete_owner_video(): void
    {
        $owner = $this->createOwner();
        $member = $this->createMember(owner: $owner);
        $videoUuid = (string) Str::uuid();

        $video = Video::query()->create([
            'uuid' => $videoUuid,
            'user_id' => $owner->id,
            'title' => 'Ready video',
            'original_path' => 's3://media-platform-private-bucket/media-platform/source_videos/'.$videoUuid.'.mp4',
            'hls_path' => 'output_videos/'.$videoUuid.'/playlist.m3u8',
            'status' => VideoStatus::Ready,
        ]);

        $response = $this
            ->withHeaders($this->authorizationHeaders($member))
            ->deleteJson('/api/v1/videos/'.$video->uuid);

        $response
            ->assertForbidden()
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Only owners can delete videos');

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
        ]);
    }

    public function test_owner_cannot_delete_video_of_another_owner(): void
    {
        $owner = $this->createOwner();
        $anotherOwner = $this->createOwner();
        $videoUuid = (string) Str::uuid();

        $video = Video::query()->create([
            'uuid' => $videoUuid,
            'user_id' => $owner->id,
            'title' => 'Ready video',
            'original_path' => 's3://media-platform-private-bucket/media-platform/source_videos/'.$videoUuid.'.mp4',
            'hls_path' => 'output_videos/'.$videoUuid.'/playlist.m3u8',
            'status' => VideoStatus::Ready,
        ]);

        $response = $this
            ->withHeaders($this->authorizationHeaders($anotherOwner))
            ->deleteJson('/api/v1/videos/'.$video->uuid);

        $response
            ->assertNotFound()
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Video not found');

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
        ]);
    }

    public function test_delete_video_requires_authentication(): void
    {
        $owner = $this->createOwner();
        $videoUuid = (string) Str::uuid();
        $video = Video::query()->create([
            'uuid' => $videoUuid,
            'user_id' => $owner->id,
            'title' => 'Ready video',
            'original_path' => 's3://media-platform-private-bucket/media-platform/source_videos/'.$videoUuid.'.mp4',
            'hls_path' => 'output_videos/'.$videoUuid.'/playlist.m3u8',
            'status' => VideoStatus::Ready,
        ]);

        $response = $this->deleteJson('/api/v1/videos/'.$video->uuid);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Unauthenticated');

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
        ]);
    }

    private function createOwner(): User
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        return $owner;
    }

    private function createMember(User $owner): User
    {
        $member = User::factory()->create([
            'owner_id' => $owner->id,
        ]);
        $member->assignRole('member');

        return $member;
    }

    private function authorizationHeaders(User $user): array
    {
        $accessToken = auth('api')->login($user);

        $this->assertIsString($accessToken);

        return ['Authorization' => 'Bearer '.$accessToken];
    }
}
