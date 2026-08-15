<?php

namespace Tests\Feature;

use App\Enums\UserActionType;
use App\Jobs\UpdateLeaderboardJob;
use App\Models\User;
use App\Models\UserAction;
use App\Services\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Интеграционные тесты идемпотентности требуют рабочий Redis
 * (REDIS_HOST=redis, REDIS_DB=15 заданы в phpunit.xml).
 */
class UpdateLeaderboardIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushdb();
    }

    /**
     * Повторный запуск одного и того же джоба не начисляет баллы повторно.
     */
    public function test_job_is_idempotent(): void
    {
        $user = User::factory()->create();
        $action = UserAction::create([
            'user_id' => $user->id,
            'type' => UserActionType::Purchase,
            'points' => UserActionType::Purchase->points(),
        ]);

        UpdateLeaderboardJob::dispatchSync($action->id);
        UpdateLeaderboardJob::dispatchSync($action->id);

        $this->assertSame(
            100.0,
            (float) Redis::zscore(LeaderboardService::KEY, (string) $user->id),
        );

        $this->assertSame(
            '1',
            Redis::get(UpdateLeaderboardJob::processedKey($action->id)),
        );
    }

    /**
     * Два одновременных выполнения одного джоба начисляют баллы ровно один раз.
     */
    public function test_concurrent_job_executions_award_points_once(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Расширение pcntl недоступно');
        }

        $user = User::factory()->create();
        $action = UserAction::create([
            'user_id' => $user->id,
            'type' => UserActionType::Purchase,
            'points' => UserActionType::Purchase->points(),
        ]);

        $pid1 = pcntl_fork();
        $this->assertNotSame(-1, $pid1, 'Не удалось создать первый дочерний процесс');

        if ($pid1 === 0) {
            $this->runJobInChild($action->id);
        }

        $pid2 = pcntl_fork();
        $this->assertNotSame(-1, $pid2, 'Не удалось создать второй дочерний процесс');

        if ($pid2 === 0) {
            $this->runJobInChild($action->id);
        }

        pcntl_waitpid($pid1, $status1);
        pcntl_waitpid($pid2, $status2);

        $this->assertSame(0, pcntl_wexitstatus($status1), 'Первый джоб завершился с ошибкой');
        $this->assertSame(0, pcntl_wexitstatus($status2), 'Второй джоб завершился с ошибкой');

        $this->assertSame(
            100.0,
            (float) Redis::zscore(LeaderboardService::KEY, (string) $user->id),
        );
    }

    private function runJobInChild(int $actionId): never
    {
        Redis::purge('default');

        UpdateLeaderboardJob::dispatchSync($actionId);

        exit(0);
    }
}
