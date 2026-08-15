<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class LeaderboardApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * API возвращает пользователей в порядке убывания баллов с именами из PostgreSQL.
     */
    public function test_leaderboard_returns_users_in_score_desc_order(): void
    {
        $a = User::factory()->create(['name' => 'A']);
        $b = User::factory()->create(['name' => 'B']);
        $c = User::factory()->create(['name' => 'C']);

        Redis::shouldReceive('zrevrange')
            ->once()
            ->with(LeaderboardService::KEY, 0, 9, true)
            ->andReturn([
                (string) $b->id => 300,
                (string) $c->id => 200,
                (string) $a->id => 100,
            ]);

        $response = $this->getJson('/api/leaderboard');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'rank' => 1,
                        'user' => ['id' => $b->id, 'name' => 'B'],
                        'score' => 300,
                    ],
                    [
                        'rank' => 2,
                        'user' => ['id' => $c->id, 'name' => 'C'],
                        'score' => 200,
                    ],
                    [
                        'rank' => 3,
                        'user' => ['id' => $a->id, 'name' => 'A'],
                        'score' => 100,
                    ],
                ],
                'meta' => [
                    'page' => 1,
                    'per_page' => 10,
                ],
            ]);
    }

    /**
     * Сценарий: User A = 100, User B = 300, User C = 200.
     */
    public function test_leaderboard_scenario_from_spec(): void
    {
        $a = User::factory()->create(['name' => 'A']);
        $b = User::factory()->create(['name' => 'B']);
        $c = User::factory()->create(['name' => 'C']);

        Redis::shouldReceive('zrevrange')
            ->once()
            ->with(LeaderboardService::KEY, 0, 9, true)
            ->andReturn([
                (string) $b->id => 300,
                (string) $c->id => 200,
                (string) $a->id => 100,
            ]);

        $this->getJson('/api/leaderboard')
            ->assertOk()
            ->assertJsonPath('data.0.user.name', 'B')
            ->assertJsonPath('data.0.score', 300)
            ->assertJsonPath('data.1.user.name', 'C')
            ->assertJsonPath('data.1.score', 200)
            ->assertJsonPath('data.2.user.name', 'A')
            ->assertJsonPath('data.2.score', 100);
    }

    /**
     * Параметры page и per_page передаются в ZREVRANGE и возвращаются в meta.
     */
    public function test_leaderboard_respects_page_and_per_page(): void
    {
        $users = User::factory()->count(4)->create();

        Redis::shouldReceive('zrevrange')
            ->once()
            ->with(LeaderboardService::KEY, 2, 3, true)
            ->andReturn([
                (string) $users[3]->id => 400,
                (string) $users[2]->id => 300,
            ]);

        $this->getJson('/api/leaderboard?page=2&per_page=2')
            ->assertOk()
            ->assertJson([
                'data' => [
                    ['rank' => 3, 'score' => 400],
                    ['rank' => 4, 'score' => 300],
                ],
                'meta' => [
                    'page' => 2,
                    'per_page' => 2,
                ],
            ]);
    }

    /**
     * Пользователь из Redis, отсутствующий в PostgreSQL, пропускается.
     */
    public function test_user_missing_in_database_is_skipped(): void
    {
        $existing = User::factory()->create(['name' => 'Existing']);

        Redis::shouldReceive('zrevrange')
            ->once()
            ->andReturn([
                '9999' => 500,
                (string) $existing->id => 100,
            ]);

        $this->getJson('/api/leaderboard')
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'rank' => 1,
                        'user' => ['id' => $existing->id, 'name' => 'Existing'],
                        'score' => 100,
                    ],
                ],
            ]);
    }

    /**
     * Пустой leaderboard возвращает пустой список data.
     */
    public function test_empty_leaderboard_returns_empty_data(): void
    {
        Redis::shouldReceive('zrevrange')
            ->once()
            ->with(LeaderboardService::KEY, 0, 9, true)
            ->andReturn([]);

        $this->getJson('/api/leaderboard')
            ->assertOk()
            ->assertJson([
                'data' => [],
                'meta' => [
                    'page' => 1,
                    'per_page' => 10,
                ],
            ]);
    }
}
