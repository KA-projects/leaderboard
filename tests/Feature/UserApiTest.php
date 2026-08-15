<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'John',
                'email' => 'john@example.com',
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John',
            'email' => 'john@example.com',
        ]);
    }

    public function test_created_user_response_contains_id(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $response->assertJsonStructure(['id', 'name', 'email']);
    }

    public function test_name_is_required(): void
    {
        $response = $this->postJson('/api/users', [
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_email_is_required_and_valid(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'John',
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/users', [
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_user_can_be_fetched(): void
    {
        $user = User::factory()->create([
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $response = $this->getJson("/api/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $user->id,
                'name' => 'John',
                'email' => 'john@example.com',
            ]);
    }

    public function test_fetching_missing_user_returns_404(): void
    {
        $response = $this->getJson('/api/users/9999');

        $response->assertStatus(404);
    }
}
