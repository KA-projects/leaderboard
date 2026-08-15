<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class UserNeighborsApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * API возвращает пользователя выше и ниже текущего в рейтинге.
     */
    public function test_neighbors_returns_user_above_and_below(): void
    {
        $john = User::factory()->create(['name' => 'John']);
        $alex = User::factory()->create(['name' => 'Alex']);
        $bob = User::factory()->create(['name' => 'Bob']);

        Redis::shouldReceive('zrevrank')
            ->once()
            ->with(LeaderboardService::KEY, (string) $john->id)
            ->andReturn(2);
        Redis::shouldReceive('zscore')
            ->once()
            ->with(LeaderboardService::KEY, (string) $john->id)
            ->andReturn(450);
        Redis::shouldReceive('zrevrange')
            ->once()
            ->with(LeaderboardService::KEY, 1, 1, true)
            ->andReturn([(string) $alex->id => 500]);
        Redis::shouldReceive('zrevrange')
            ->once()
            ->with(LeaderboardService::KEY, 3, 3, true)
            ->andReturn([(string) $bob->id => 400]);

        $this->getJson("/api/users/{$john->id}/neighbors")
            ->assertOk()
            ->assertExactJson([
                'user_id' => $john->id,
                'score' => 450,
                'rank' => 3,
                'above' => [
                    [
                        'rank' => 2,
                        'user' => ['id' => $alex->id, 'name' => 'Alex'],
                        'score' => 500,
                    ],
                ],
                'below' => [
                    [
                        'rank' => 4,
                        'user' => ['id' => $bob->id, 'name' => 'Bob'],
                        'score' => 400,
                    ],
                ],
            ]);
    }

    /**
     * Параметр limit задаёт количество соседей сверху и снизу.
     */
    public function test_neighbors_respects_limit(): void
    {
        $john = User::factory()->create(['name' => 'John']);
        $alex = User::factory()->create(['name' => 'Alex']);
        $bob = User::factory()->create(['name' => 'Bob']);

        Redis::shouldReceive('zrevrank')->once()->andReturn(2);
        Redis::shouldReceive('zscore')->once()->andReturn(450);
        Redis::shouldReceive('zrevrange')
            ->once()
            ->with(LeaderboardService::KEY, 0, 1, true)
            ->andReturn([(string) $alex->id => 500]);
        Redis::shouldReceive('zrevrange')
            ->once()
            ->with(LeaderboardService::KEY, 3, 4, true)
            ->andReturn([(string) $bob->id => 400]);

        $this->getJson("/api/users/{$john->id}/neighbors?limit=2")
            ->assertOk()
            ->assertJsonPath('above.0.rank', 1)
            ->assertJsonPath('below.0.rank', 4);
    }

    /**
     * Лидер рейтинга не имеет соседей выше.
     */
    public function test_top_user_has_no_above_neighbors(): void
    {
        $leader = User::factory()->create(['name' => 'Leader']);
        $bob = User::factory()->create(['name' => 'Bob']);

        Redis::shouldReceive('zrevrank')->once()->andReturn(0);
        Redis::shouldReceive('zscore')->once()->andReturn(500);
        Redis::shouldReceive('zrevrange')
            ->once()
            ->with(LeaderboardService::KEY, 1, 1, true)
            ->andReturn([(string) $bob->id => 400]);

        $this->getJson("/api/users/{$leader->id}/neighbors")
            ->assertOk()
            ->assertJson([
                'user_id' => $leader->id,
                'rank' => 1,
                'above' => [],
                'below' => [
                    ['rank' => 2, 'user' => ['name' => 'Bob'], 'score' => 400],
                ],
            ]);
    }

    /**
     * Если у пользователя нет баллов, соседи не возвращаются.
     */
    public function test_unranked_user_has_no_neighbors(): void
    {
        $user = User::factory()->create();

        Redis::shouldReceive('zrevrank')->once()->andReturn(false);
        Redis::shouldReceive('zscore')->once()->andReturn(false);

        $this->getJson("/api/users/{$user->id}/neighbors")
            ->assertOk()
            ->assertExactJson([
                'user_id' => $user->id,
                'score' => null,
                'rank' => null,
                'above' => [],
                'below' => [],
            ]);
    }
}
