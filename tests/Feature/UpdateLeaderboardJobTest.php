<?php

namespace Tests\Feature;

use App\Enums\UserActionType;
use App\Jobs\UpdateLeaderboardJob;
use App\Models\User;
use App\Models\UserAction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class UpdateLeaderboardJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Джоб увеличивает счёт пользователя в Redis sorted set ranking:all.
     */
    public function test_job_increments_user_score_in_redis(): void
    {
        $user = User::factory()->create();
        $action = UserAction::create([
            'user_id' => $user->id,
            'type' => UserActionType::Purchase,
            'points' => UserActionType::Purchase->points(),
        ]);

        $connection = Mockery::mock();
        $connection->shouldReceive('zincrby')
            ->once()
            ->with('ranking:all', 100, (string) $user->id);
        Redis::shouldReceive('connection')->andReturn($connection);

        UpdateLeaderboardJob::dispatchSync($action->id);
    }

    /**
     * Несколько действий одного пользователя суммируются в Redis.
     */
    public function test_multiple_actions_accumulate_score(): void
    {
        $user = User::factory()->create();
        $action = UserAction::create([
            'user_id' => $user->id,
            'type' => UserActionType::Purchase,
            'points' => UserActionType::Purchase->points(),
        ]);

        $connection = Mockery::mock();
        $connection->shouldReceive('zincrby')
            ->twice()
            ->with('ranking:all', 100, (string) $user->id);
        Redis::shouldReceive('connection')->andReturn($connection);

        UpdateLeaderboardJob::dispatchSync($action->id);
        UpdateLeaderboardJob::dispatchSync($action->id);
    }

    /**
     * Джоб завершается с ошибкой, если действие не существует.
     */
    public function test_job_fails_for_missing_action(): void
    {
        $this->expectException(ModelNotFoundException::class);

        UpdateLeaderboardJob::dispatchSync(9999);
    }
}
