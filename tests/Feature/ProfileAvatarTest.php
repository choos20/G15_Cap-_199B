<?php

namespace Tests\Feature;

use App\Http\Controllers\OrgProfileController;
use App\Http\Controllers\ProfilePhotoController;
use App\Models\OrgOfficer;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('participant');
            $table->string('profile_photo_path')->nullable();
            $table->timestamp('org_profile_updated_at')->nullable();
            $table->timestamps();
        });
        Schema::create('org_officers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('name');
            $table->string('position');
            $table->string('email')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('avatar_path')->nullable();
            $table->timestamps();
        });
    }

    public function test_any_user_can_upload_and_remove_their_own_profile_photo(): void
    {
        $user = $this->createUser('participant');
        $this->mockBackRedirect();

        $request = Request::create('/account/profile-photo', 'POST');
        $request->files->set('profile_photo', $this->fakeImage());
        $request->setUserResolver(fn () => $user);

        app(ProfilePhotoController::class)->update($request);

        $user->refresh();
        $this->assertNotNull($user->profile_photo_path);
        Storage::disk('public')->assertExists($user->profile_photo_path);

        $this->mockBackRedirect();
        $removeRequest = Request::create('/account/profile-photo', 'DELETE');
        $removeRequest->setUserResolver(fn () => $user);
        app(ProfilePhotoController::class)->destroy($removeRequest);

        $user->refresh();
        $this->assertNull($user->profile_photo_path);
    }

    public function test_profile_photo_upload_rejects_images_below_the_minimum_resolution(): void
    {
        $user = $this->createUser('participant');
        $request = Request::create('/account/profile-photo', 'POST');
        $request->files->set('profile_photo', $this->fakeImage(128, 128));
        $request->setUserResolver(fn () => $user);

        try {
            app(ProfilePhotoController::class)->update($request);
            $this->fail('A low-resolution profile photo should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('profile_photo', $exception->errors());
        }

        $this->assertNull($user->fresh()->profile_photo_path);
        $this->assertSame([], Storage::disk('public')->allFiles('profile_photos'));
    }

    public function test_student_organization_president_can_update_a_co_officer_photo(): void
    {
        $organization = $this->createUser('student_org');
        $officerId = DB::table('org_officers')->insertGetId([
            'user_id' => $organization->id,
            'name' => 'Co-officer',
            'position' => 'Secretary',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $officer = OrgOfficer::findOrFail($officerId);
        $this->mockRouteRedirect('org.about.edit', ['tab' => 'officers']);

        $request = Request::create('/student-org/about/officers/'.$officer->id.'/avatar', 'POST');
        $request->files->set('avatar', $this->fakeImage());
        $request->setUserResolver(fn () => $organization);

        app(OrgProfileController::class)->updateOfficerAvatar($request, $officer);

        $officer->refresh();
        $this->assertNotNull($officer->avatar_path);
        Storage::disk('public')->assertExists($officer->avatar_path);
    }

    private function createUser(string $role): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Test '.$role,
            'email' => $role.'@example.test',
            'password' => bcrypt('password'),
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    private function fakeImage(int $width = 300, int $height = 300): UploadedFile
    {
        $row = "\0".str_repeat("\0", $width * 4);
        $pixels = gzcompress(str_repeat($row, $height));
        $contents = "\x89PNG\r\n\x1a\n"
            .$this->pngChunk('IHDR', pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0))
            .$this->pngChunk('IDAT', $pixels)
            .$this->pngChunk('IEND', '');

        return UploadedFile::fake()->createWithContent('avatar.png', $contents);
    }

    private function pngChunk(string $type, string $data): string
    {
        $chunk = $type.$data;

        return pack('N', strlen($data)).$chunk.pack('N', crc32($chunk));
    }

    private function mockBackRedirect(): void
    {
        $this->mockRedirector('back', [302, [], false], new RedirectResponse('/'));
    }

    private function mockRouteRedirect(string $route, array $parameters): void
    {
        $this->mockRedirector('route', [$route, $parameters], new RedirectResponse('/student-org/about?tab=officers'));
    }

    private function mockRedirector(string $method, array $arguments, RedirectResponse $response): void
    {
        $response->setSession($this->app['session.store']);
        $redirector = \Mockery::mock(Redirector::class);
        $redirector->shouldReceive($method)
            ->once()
            ->with(...$arguments)
            ->andReturn($response);
        $this->app->instance('redirect', $redirector);
    }
}
