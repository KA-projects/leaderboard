<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class UserRankApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * API возвращает score и rank пользователя на основе ZREVRANK и ZSCORE.
     */
    public function test_rank_returns_user_score_and_rank(): void
    {
        $user = User::factory()->create(['name' => 'John']);

        Redis::shouldReceive('zrevrank')
            ->once()
            ->with(LeaderboardService::KEY, (string) $user->id)
            ->andReturn(2);
        Redis::shouldReceive('zscore')
            ->once()
            ->with(LeaderboardService::KEY, (string) $user->id)
            ->andReturn(450);

        $this->getJson("/api/users/{$user->id}/rank")
            ->assertOk()
            ->assertExactJson([
                'user_id' => $user->id,
                'score' => 450,
                'rank' => 3,
            ]);
    }

    /**
     * Позиция первого в рейтинге считается как rank 1, хотя ZREVRANK возвращает 0.
     */
    public function test_top_user_gets_rank_one(): void
    {
        $user = User::factory()->create();

        Redis::shouldReceive('zrevrank')->once()->andReturn(0);
        Redis::shouldReceive('zscore')->once()->andReturn(300);

        $this->getJson("/api/users/{$user->id}/rank")
            ->assertOk()
            ->assertJson(['rank' => 1, 'score' => 300]);
    }

    /**
     * Если у пользователя нет баллов в Redis, API возвращает null rank и score.
     */
    public function test_unranked_user_returns_null_score_and_rank(): void
    {
        $user = User::factory()->create();

        Redis::shouldReceive('zrevrank')->once()->andReturn(false);
        Redis::shouldReceive('zscore')->once()->andReturn(false);

        $this->getJson("/api/users/{$user->id}/rank")
            ->assertOk()
            ->assertExactJson([
                'user_id' => $user->id,
                'score' => null,
                'rank' => null,
            ]);
    }

    /**
     * Для несуществующего пользователя возвращается 404.
     */
    public function test_rank_for_missing_user_returns_404(): void
    {
        $this->getJson('/api/users/9999/rank')->assertStatus(404);
    }

    /**
     * Параметр period определяет, из какого рейтинга берётся позиция.
     */
    public function test_rank_respects_period(): void
    {
        $user = User::factory()->create();

        Redis::shouldReceive('zrevrank')
            ->once()
            ->with(LeaderboardService::keyForPeriod('monthly'), (string) $user->id)
            ->andReturn(1);
        Redis::shouldReceive('zscore')
            ->once()
            ->with(LeaderboardService::keyForPeriod('monthly'), (string) $user->id)
            ->andReturn(250);

        $this->getJson("/api/users/{$user->id}/rank?period=monthly")
            ->assertOk()
            ->assertJson(['score' => 250, 'rank' => 2]);
    }
}
