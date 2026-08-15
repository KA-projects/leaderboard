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
     * Джоб увеличивает счёт пользователя во всех рейтингах: all, daily, weekly, monthly.
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
        $connection->shouldReceive('zincrby')
            ->once()
            ->with('ranking:daily:'.$action->created_at->format('Y-m-d'), 100, (string) $user->id);
        $connection->shouldReceive('zincrby')
            ->once()
            ->with('ranking:weekly:'.$action->created_at->format('o-\WW'), 100, (string) $user->id);
        $connection->shouldReceive('zincrby')
            ->once()
            ->with('ranking:monthly:'.$action->created_at->format('Y-m'), 100, (string) $user->id);
        Redis::shouldReceive('connection')->andReturn($connection);

        UpdateLeaderboardJob::dispatchSync($action->id);
    }

    /**
     * Несколько действий одного пользователя суммируются во всех рейтингах.
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
            ->times(8)
            ->with(Mockery::type('string'), 100, (string) $user->id);
        Redis::shouldReceive('connection')->andReturn($connection);

        UpdateLeaderboardJob::dispatchSync($action->id);
        UpdateLeaderboardJob::dispatchSync($action->id);
    }

    /**
     * Джоб использует дату действия для ключей периодических рейтингов.
     */
    public function test_job_uses_action_date_for_period_keys(): void
    {
        $user = User::factory()->create();
        $action = UserAction::create([
            'user_id' => $user->id,
            'type' => UserActionType::Purchase,
            'points' => UserActionType::Purchase->points(),
        ]);
        $action->created_at = '2026-08-14 10:00:00';
        $action->save();

        $connection = Mockery::mock();
        $connection->shouldReceive('zincrby')
            ->once()
            ->with('ranking:all', 100, (string) $user->id);
        $connection->shouldReceive('zincrby')
            ->once()
            ->with('ranking:daily:2026-08-14', 100, (string) $user->id);
        $connection->shouldReceive('zincrby')
            ->once()
            ->with('ranking:weekly:2026-W33', 100, (string) $user->id);
        $connection->shouldReceive('zincrby')
            ->once()
            ->with('ranking:monthly:2026-08', 100, (string) $user->id);
        Redis::shouldReceive('connection')->andReturn($connection);

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
