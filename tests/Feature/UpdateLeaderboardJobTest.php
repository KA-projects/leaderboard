<?php

namespace Tests\Feature;

use App\Enums\UserActionType;
use App\Jobs\UpdateLeaderboardJob;
use App\Models\User;
use App\Models\UserAction;
use App\Services\LeaderboardService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class UpdateLeaderboardJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Джоб вызывает атомарный Lua-скрипт с маркером действия и ключами всех рейтингов.
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
        $connection->shouldReceive('eval')
            ->once()
            ->withArgs(function (string $script, int $numberOfKeys, ...$args) use ($action, $user) {
                return $numberOfKeys === 5
                    && str_contains($script, "redis.call('ZINCRBY', KEYS[i]")
                    && $args[0] === LeaderboardService::processedKey($action->id)
                    && $args[1] === 'ranking:all'
                    && $args[2] === 'ranking:daily:'.$action->created_at->format('Y-m-d')
                    && $args[3] === 'ranking:weekly:'.$action->created_at->format('o-\WW')
                    && $args[4] === 'ranking:monthly:'.$action->created_at->format('Y-m')
                    && $args[5] === '100'
                    && $args[6] === (string) $user->id;
            })
            ->andReturn('processed');
        Redis::shouldReceive('connection')->andReturn($connection);

        UpdateLeaderboardJob::dispatchSync($action->id);
    }

    /**
     * Каждое действие обрабатывается отдельным вызовом скрипта.
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
        $connection->shouldReceive('eval')
            ->times(2)
            ->with(Mockery::type('string'), 5, Mockery::type('string'), Mockery::type('string'), Mockery::type('string'), Mockery::type('string'), Mockery::type('string'), '100', (string) $user->id)
            ->andReturn('processed');
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
        $connection->shouldReceive('eval')
            ->once()
            ->withArgs(function (string $script, int $numberOfKeys, ...$args) use ($action) {
                return $numberOfKeys === 5
                    && $args[0] === LeaderboardService::processedKey($action->id)
                    && $args[1] === 'ranking:all'
                    && $args[2] === 'ranking:daily:2026-08-14'
                    && $args[3] === 'ranking:weekly:2026-W33'
                    && $args[4] === 'ranking:monthly:2026-08';
            })
            ->andReturn('processed');
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
