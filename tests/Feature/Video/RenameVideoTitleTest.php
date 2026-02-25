<?php

namespace Tests\Feature\Video;

use App\Enums\VideoStatus;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RenameVideoTitleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('owner', 'web');
        Role::findOrCreate('member', 'web');
    }

    public function test_owner_can_rename_video_title(): void
    {
        $owner = $this->createOwner();
        $video = $this->createVideo(owner: $owner, title: 'Initial title');

        $response = $this
            ->withHeaders($this->authorizationHeaders($owner))
            ->patchJson('/api/v1/videos/'.$video->uuid, [
                'title' => 'Renamed title',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('type', 'data')
            ->assertJsonPath('data.video.id', $video->uuid)
            ->assertJsonPath('data.video.title', 'Renamed title');

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'title' => 'Renamed title',
        ]);
    }

    public function test_owner_can_clear_video_title_with_blank_value(): void
    {
        $owner = $this->createOwner();
        $video = $this->createVideo(owner: $owner, title: 'Filled title');

        $response = $this
            ->withHeaders($this->authorizationHeaders($owner))
            ->patchJson('/api/v1/videos/'.$video->uuid, [
                'title' => '   ',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('type', 'data')
            ->assertJsonPath('data.video.id', $video->uuid)
            ->assertJsonPath('data.video.title', null);

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'title' => null,
        ]);
    }

    public function test_member_cannot_rename_owner_video(): void
    {
        $owner = $this->createOwner();
        $member = $this->createMember(owner: $owner);
        $video = $this->createVideo(owner: $owner, title: 'Owner title');

        $response = $this
            ->withHeaders($this->authorizationHeaders($member))
            ->patchJson('/api/v1/videos/'.$video->uuid, [
                'title' => 'New member title',
            ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Only owners can rename videos');
    }

    public function test_owner_cannot_rename_video_of_another_owner(): void
    {
        $owner = $this->createOwner();
        $anotherOwner = $this->createOwner();
        $video = $this->createVideo(owner: $owner, title: 'Owner title');

        $response = $this
            ->withHeaders($this->authorizationHeaders($anotherOwner))
            ->patchJson('/api/v1/videos/'.$video->uuid, [
                'title' => 'Cross-owner title',
            ]);

        $response
            ->assertNotFound()
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Video not found');
    }

    public function test_rename_video_requires_authentication(): void
    {
        $owner = $this->createOwner();
        $video = $this->createVideo(owner: $owner, title: 'Title');

        $response = $this->patchJson('/api/v1/videos/'.$video->uuid, [
            'title' => 'Updated title',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_rename_video_validates_title_presence(): void
    {
        $owner = $this->createOwner();
        $video = $this->createVideo(owner: $owner, title: 'Title');

        $response = $this
            ->withHeaders($this->authorizationHeaders($owner))
            ->patchJson('/api/v1/videos/'.$video->uuid, []);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('type', 'validation_error')
            ->assertJsonPath('message', 'Title field must be present.');
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

    private function createVideo(User $owner, string $title): Video
    {
        $videoUuid = (string) Str::uuid();

        return Video::query()->create([
            'uuid' => $videoUuid,
            'user_id' => $owner->id,
            'title' => $title,
            'original_path' => 's3://media-platform-private-bucket/media-platform/source_videos/'.$videoUuid.'.mp4',
            'hls_path' => 'output_videos/'.$videoUuid.'/playlist.m3u8',
            'status' => VideoStatus::Ready,
        ]);
    }

    private function authorizationHeaders(User $user): array
    {
        $accessToken = auth('api')->login($user);

        $this->assertIsString($accessToken);

        return ['Authorization' => 'Bearer '.$accessToken];
    }
}
