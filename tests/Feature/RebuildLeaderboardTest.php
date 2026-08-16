<?php

namespace Tests\Feature;

use App\Enums\UserActionType;
use App\Models\User;
use App\Services\LeaderboardService;
use App\Services\UserActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RebuildLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushdb();
    }

    /**
     * Команда leaderboard:rebuild полностью восстанавливает рейтинг из PostgreSQL после потери данных в Redis.
     */
    public function test_rebuild_restores_leaderboard_from_postgresql(): void
    {
        $user1 = User::factory()->create(['name' => 'User 1']);
        $user2 = User::factory()->create(['name' => 'User 2']);

        $actions = app(UserActionService::class);
        $actions->create($user1->id, UserActionType::Purchase); // +100
        $actions->create($user1->id, UserActionType::Comment); // +20
        $actions->create($user2->id, UserActionType::Purchase); // +100

        Redis::del(LeaderboardService::KEY);
        $this->assertFalse(Redis::zscore(LeaderboardService::KEY, (string) $user1->id));

        $this->artisan('leaderboard:rebuild')->assertSuccessful();

        $this->assertSame(
            120.0,
            (float) Redis::zscore(LeaderboardService::KEY, (string) $user1->id),
        );
        $this->assertSame(
            100.0,
            (float) Redis::zscore(LeaderboardService::KEY, (string) $user2->id),
        );
    }

    /**
     * После rebuild временные ключи namespace не остаются в Redis.
     */
    public function test_rebuild_cleans_up_temporary_namespace_keys(): void
    {
        $user = User::factory()->create();

        $actions = app(UserActionService::class);
        $actions->create($user->id, UserActionType::Purchase);

        $this->artisan('leaderboard:rebuild')->assertSuccessful();

        $this->assertSame(
            [],
            Redis::keys('ranking:rebuild:*'),
        );
    }

    /**
     * Если rebuild падает, старый leaderboard остаётся рабочим.
     */
    public function test_failed_rebuild_keeps_old_leaderboard_intact(): void
    {
        $user = User::factory()->create(['name' => 'User']);

        $actions = app(UserActionService::class);
        $actions->create($user->id, UserActionType::Purchase);

        $realConnection = Redis::connection();

        Redis::shouldReceive('zadd')
            ->andThrow(new \RuntimeException('Redis недоступен'));
        Redis::shouldReceive('del')
            ->andReturn(0);

        $this->artisan('leaderboard:rebuild')->assertFailed();

        $this->assertSame(
            100.0,
            (float) $realConnection->zscore(LeaderboardService::KEY, (string) $user->id),
        );
    }
}
