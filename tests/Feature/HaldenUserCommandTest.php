<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HaldenUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_faculty_login_and_keeps_the_password_on_update(): void
    {
        $this->artisan('halden:user', ['email' => 'Prof@Example.edu', 'name' => 'Prof', '--role' => 'faculty'])
            ->expectsOutputToContain('prof@example.edu is set up as faculty.')
            ->expectsOutputToContain('Password (shown once')
            ->assertSuccessful();

        $user = User::query()->where('email', 'prof@example.edu')->firstOrFail();
        $this->assertTrue($user->isFaculty());
        $this->assertNotNull($user->email_verified_at);
        $hash = $user->password;

        $this->artisan('halden:user', ['email' => 'prof@example.edu', 'name' => 'Professor', '--role' => 'admin'])
            ->doesntExpectOutputToContain('Password')
            ->assertSuccessful();
        $user->refresh();
        $this->assertSame('Professor', $user->name);
        $this->assertTrue($user->isAdmin());
        $this->assertSame($hash, $user->password);
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_it_refuses_bad_input(): void
    {
        $this->artisan('halden:user', ['email' => 'nope', 'name' => 'X'])->assertFailed();
        $this->artisan('halden:user', ['email' => 'a@b.co', 'name' => 'X', '--role' => 'owner'])->assertFailed();
    }
}
