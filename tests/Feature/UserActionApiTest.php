<?php

namespace Tests\Feature;

use App\Enums\UserActionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserActionApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Действие «purchase» начисляет пользователю 100 баллов.
     */
    public function test_purchase_action_records_100_points(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/actions', [
            'user_id' => $user->id,
            'type' => 'purchase',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'user_id' => $user->id,
                'type' => 'purchase',
                'points' => 100,
            ]);

        $this->assertDatabaseHas('user_actions', [
            'user_id' => $user->id,
            'type' => 'purchase',
            'points' => 100,
        ]);
    }

    /**
     * Каждый поддерживаемый тип действия начисляет своё количество баллов.
     */
    public function test_each_supported_action_type_awards_its_points(): void
    {
        $user = User::factory()->create();

        foreach (UserActionType::cases() as $type) {
            $response = $this->postJson('/api/actions', [
                'user_id' => $user->id,
                'type' => $type->value,
            ]);

            $response->assertStatus(201)
                ->assertJson([
                    'user_id' => $user->id,
                    'type' => $type->value,
                    'points' => $type->points(),
                ]);
        }
    }

    /**
     * Ответ содержит id сохранённого действия.
     */
    public function test_response_contains_action_id(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/actions', [
            'user_id' => $user->id,
            'type' => 'like',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'user_id', 'type', 'points']);
    }

    /**
     * Запрос с несуществующим user_id отклоняется.
     */
    public function test_action_for_missing_user_is_rejected(): void
    {
        $response = $this->postJson('/api/actions', [
            'user_id' => 9999,
            'type' => 'purchase',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('user_id');

        $this->assertDatabaseCount('user_actions', 0);
    }

    /**
     * Неподдерживаемый тип действия отклоняется.
     */
    public function test_invalid_action_type_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/actions', [
            'user_id' => $user->id,
            'type' => 'cheat',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type');

        $this->assertDatabaseCount('user_actions', 0);
    }

    /**
     * Отсутствующий тип действия отклоняется.
     */
    public function test_type_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/actions', [
            'user_id' => $user->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type');

        $this->assertDatabaseCount('user_actions', 0);
    }

    /**
     * Переданные в запросе баллы игнорируются и вычисляются из типа действия.
     */
    public function test_points_from_request_are_ignored(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/actions', [
            'user_id' => $user->id,
            'type' => 'purchase',
            'points' => 1000000,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'user_id' => $user->id,
                'type' => 'purchase',
                'points' => 100,
            ]);

        $this->assertDatabaseHas('user_actions', [
            'user_id' => $user->id,
            'type' => 'purchase',
            'points' => 100,
        ]);
    }
}
