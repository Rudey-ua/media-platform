<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.default' => 'public']);
        Storage::fake('public');
    }

    public function test_authenticated_user_can_upload_profile_avatar(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->withHeaders($this->authorizationHeaders($user))
            ->postJson('/api/v1/profile/avatar', [
                'profile_image' => UploadedFile::fake()->image('avatar.jpg'),
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('type', 'data')
            ->assertJsonPath('data.user.id', $user->id);

        $user->refresh();

        $this->assertNotNull($user->profile_image);
        $this->assertStringContainsString('avatar.jpg', $user->profile_image);
        Storage::disk('public')->assertExists('profile_images/'.$user->profile_image);
        $this->assertStringContainsString('/storage/profile_images/', (string) $response->json('data.user.profile_image'));
    }

    public function test_uploading_avatar_removes_old_file(): void
    {
        $oldAvatar = 'old-avatar.jpg';

        Storage::disk('public')->put('profile_images/'.$oldAvatar, 'old-avatar-content');

        $user = User::factory()->create([
            'profile_image' => $oldAvatar,
        ]);

        $response = $this
            ->withHeaders($this->authorizationHeaders($user))
            ->postJson('/api/v1/profile/avatar', [
                'profile_image' => UploadedFile::fake()->image('new-avatar.jpg'),
            ]);

        $response->assertOk();

        $user->refresh();

        $this->assertNotNull($user->profile_image);
        $this->assertNotSame($oldAvatar, $user->profile_image);
        Storage::disk('public')->assertMissing('profile_images/'.$oldAvatar);
        Storage::disk('public')->assertExists('profile_images/'.$user->profile_image);
    }

    public function test_authenticated_user_can_delete_profile_avatar(): void
    {
        $avatar = 'avatar-for-delete.jpg';

        Storage::disk('public')->put('profile_images/'.$avatar, 'avatar-content');

        $user = User::factory()->create([
            'profile_image' => $avatar,
        ]);

        $response = $this
            ->withHeaders($this->authorizationHeaders($user))
            ->deleteJson('/api/v1/profile/avatar');

        $response
            ->assertOk()
            ->assertJsonPath('type', 'data')
            ->assertJsonPath('data.user.profile_image', null);

        $user->refresh();

        $this->assertNull($user->profile_image);
        Storage::disk('public')->assertMissing('profile_images/'.$avatar);
    }

    public function test_upload_avatar_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/profile/avatar', [
            'profile_image' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_delete_avatar_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/v1/profile/avatar');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_upload_avatar_validation_requires_profile_image_field(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->withHeaders($this->authorizationHeaders($user))
            ->postJson('/api/v1/profile/avatar', []);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('type', 'validation_error');

        $details = $response->json('details');

        $this->assertIsArray($details);
        $fields = array_column($details, 'field');
        $this->assertContains('profile_image', $fields);
    }

    public function test_upload_avatar_validation_rejects_invalid_mime_type(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->withHeaders($this->authorizationHeaders($user))
            ->postJson('/api/v1/profile/avatar', [
                'profile_image' => UploadedFile::fake()->create('avatar.pdf', 32, 'application/pdf'),
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('type', 'validation_error');

        $details = $response->json('details');

        $this->assertIsArray($details);
        $fields = array_column($details, 'field');
        $this->assertContains('profile_image', $fields);
    }

    private function authorizationHeaders(User $user): array
    {
        $accessToken = auth('api')->login($user);

        $this->assertIsString($accessToken);

        return ['Authorization' => 'Bearer '.$accessToken];
    }
}
