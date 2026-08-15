<?php

namespace Tests\Feature;

use App\Enums\UserActionType;
use App\Jobs\UpdateLeaderboardJob;
use App\Models\User;
use App\Models\UserAction;
use App\Services\LeaderboardService;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

/**
 * Тесты retry и failed jobs требуют рабочий Redis
 * (REDIS_HOST=redis, REDIS_DB=15 заданы в phpunit.xml).
 */
class UpdateLeaderboardRetryTest extends TestCase
{
    use RefreshDatabase;

    private mixed $realConnection;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushdb();

        $this->realConnection = Redis::connection();

        // Как и queue:work, складываем упавшие джобы в failed jobs
        $this->app['events']->listen(JobFailed::class, function (JobFailed $event) {
            $this->app['queue.failer']->log(
                $event->connectionName,
                $event->job->getQueue(),
                $event->job->getRawBody(),
                $event->exception,
            );
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Временная недоступность Redis не теряет действие: джоб уходит в retry,
     * а после восстановления Redis начисляет баллы.
     */
    public function test_job_retries_while_redis_is_down_and_succeeds_after_recovery(): void
    {
        $user = User::factory()->create();
        $action = $this->createAction($user);

        $queue = app('queue')->connection('redis');
        $queue->push(new UpdateLeaderboardJob($action->id));

        // Redis недоступен: операция обновления рейтинга бросает исключение
        $this->simulateRedisFailure();

        $worker = app('queue.worker');

        // Попытка #1 — падение; джоб возвращается в очередь на повторный запуск
        $job = $queue->pop('default');
        $this->assertNotNull($job, 'Джоб не найден в очереди');

        try {
            $worker->process('redis', $job, new WorkerOptions);
            $this->fail('Джоб должен был завершиться с ошибкой');
        } catch (Exception $e) {
            $this->assertSame('Redis unavailable', $e->getMessage());
        }

        // Баллы не начислены, джоб не потерян и не в failed jobs, а ждёт retry
        $this->assertNull($this->score($user));
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, $queue->delayedSize());

        // Redis восстановился, backoff истёк
        $this->restoreRedis();
        Carbon::setTestNow(now()->addSeconds(10));

        // Попытка #2 — успех
        $job = $queue->pop('default');
        $this->assertNotNull($job, 'Джоб не вернулся в очередь после retry');

        $worker->process('redis', $job, new WorkerOptions);

        $this->assertSame(100.0, $this->score($user));
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    /**
     * После исчерпания всех попыток джоб уходит в failed jobs, а повторный
     * запуск через queue:retry после восстановления Redis начисляет баллы.
     */
    public function test_job_goes_to_failed_jobs_after_exhausting_attempts_and_retry_awards_points(): void
    {
        $user = User::factory()->create();
        $action = $this->createAction($user);

        $queue = app('queue')->connection('redis');
        $queue->push(new UpdateLeaderboardJob($action->id));

        $this->simulateRedisFailure();
        $worker = app('queue.worker');

        // Все три попытки проваливаются
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $job = $queue->pop('default');
            $this->assertNotNull($job, "Джоб не найден на попытке {$attempt}");

            try {
                $worker->process('redis', $job, new WorkerOptions);
                $this->fail('Джоб должен был завершиться с ошибкой');
            } catch (Exception) {
                // ожидаемое падение
            }

            Carbon::setTestNow(now()->addSeconds(200));
        }

        // Баллы не начислены, джоб перешёл в failed jobs
        $this->assertNull($this->score($user));

        $failed = DB::table('failed_jobs')->first();
        $this->assertNotNull($failed, 'Джоб не попал в failed jobs');
        $this->assertStringContainsString('UpdateLeaderboardJob', $failed->payload);
        $this->assertStringContainsString('Redis unavailable', $failed->exception);

        // Redis восстановился, повторный запуск джоба начисляет баллы
        $this->restoreRedis();

        Artisan::call('queue:retry', ['id' => [$failed->uuid]]);

        $job = $queue->pop('default');
        $this->assertNotNull($job, 'Джоб не вернулся в очередь после queue:retry');

        $worker->process('redis', $job, new WorkerOptions);

        $this->assertSame(100.0, $this->score($user));
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    private function createAction(User $user): UserAction
    {
        return UserAction::create([
            'user_id' => $user->id,
            'type' => UserActionType::Purchase,
            'points' => UserActionType::Purchase->points(),
        ]);
    }

    private function simulateRedisFailure(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('eval')->andThrow(new Exception('Redis unavailable'));

        $manager = Mockery::mock('Illuminate\Redis\RedisManager');
        $manager->shouldReceive('connection')->andReturn($connection);

        Redis::swap($manager);
    }

    private function restoreRedis(): void
    {
        $real = $this->realConnection;
        $connection = Mockery::mock();
        $connection->shouldReceive('eval')
            ->andReturnUsing(fn (...$args) => $real->eval(...$args));

        $manager = Mockery::mock('Illuminate\Redis\RedisManager');
        $manager->shouldReceive('connection')->andReturn($connection);

        Redis::swap($manager);
    }

    private function score(User $user): ?float
    {
        $score = $this->realConnection->zscore(LeaderboardService::KEY, (string) $user->id);

        return $score === null || $score === false ? null : (float) $score;
    }
}
