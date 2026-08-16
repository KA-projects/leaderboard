<?php

namespace Tests\Feature;

use App\Enums\UserActionType;
use App\Models\User;
use App\Services\LeaderboardService;
use App\Services\UserActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class CheckLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushdb();
    }

    /**
     * Команда leaderboard:check находит расхождение между PostgreSQL и Redis.
     */
    public function test_check_detects_mismatch_between_postgresql_and_redis(): void
    {
        $user = User::factory()->create(['name' => 'User 1']);

        $actions = app(UserActionService::class);
        $actions->create($user->id, UserActionType::Purchase); // +100
        $actions->create($user->id, UserActionType::Comment); // +20

        // имитируем расхождение: в Redis баллы меньше, чем в PostgreSQL (120)
        Redis::zadd(LeaderboardService::KEY, [(string) $user->id => 100]);

        $this->artisan('leaderboard:check')
            ->expectsOutputToContain('User #'.$user->id.': MISMATCH')
            ->assertFailed();
    }

    /**
     * После leaderboard:rebuild команда leaderboard:check не находит расхождений.
     */
    public function test_check_reports_no_mismatches_after_rebuild(): void
    {
        $user = User::factory()->create(['name' => 'User 1']);

        $actions = app(UserActionService::class);
        $actions->create($user->id, UserActionType::Purchase); // +100
        $actions->create($user->id, UserActionType::Comment); // +20

        Redis::zadd(LeaderboardService::KEY, [(string) $user->id => 100]);

        $this->artisan('leaderboard:rebuild')->assertSuccessful();

        $this->artisan('leaderboard:check')
            ->expectsOutputToContain('No mismatches found.')
            ->assertSuccessful();
    }
}
