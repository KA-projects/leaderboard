<?php

namespace App\Jobs;

use App\Models\UserAction;
use App\Services\LeaderboardService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class UpdateLeaderboardJob implements ShouldQueue
{
    use Queueable;

    /**
     * Префикс ключей-маркеров, отмечающих уже обработанные действия.
     */
    public const PROCESSED_PREFIX = 'leaderboard:processed:';

    private const ALREADY_PROCESSED = 'already_processed';

    /**
     * Количество попыток выполнения до перевода джоба в failed jobs.
     */
    public $tries = 3;

    public function __construct(
        public int $actionId,
    ) {}

    /**
     * Задержка между попытками: после 1-й — 5 сек, после 2-й — 30 сек, после 3-й — 120 сек.
     * При репликации БД или временная недоступность Redis
     *
     * @return int[]
     */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function handle(): void
    {
        try {
            $action = UserAction::findOrFail($this->actionId);

            $keys = [
                self::processedKey($action->id),
                LeaderboardService::KEY,
                LeaderboardService::keyForPeriod('daily', $action->created_at),
                LeaderboardService::keyForPeriod('weekly', $action->created_at),
                LeaderboardService::keyForPeriod('monthly', $action->created_at),
            ];

            $result = Redis::connection()->eval(
                $this->script(),
                count($keys),
                ...[
                    ...$keys,
                    (string) $action->points,
                    (string) $action->user_id,
                ],
            );

            if ($result === self::ALREADY_PROCESSED) {
                Log::info('Leaderboard action already processed', [
                    'action_id' => $action->id,
                ]);

                return;
            }

            Log::info('Leaderboard action processed', [
                'action_id' => $action->id,
                'user_id' => $action->user_id,
                'points' => $action->points,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to update leaderboard', [
                'action_id' => $this->actionId,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public static function processedKey(int $actionId): string
    {
        return self::PROCESSED_PREFIX.$actionId;
    }

    private function script(): string
    {
        // Маркер хранится без TTL: безопасный срок жизни нельзя определить,
        // т.к. retry задания может произойти через произвольное время,
        // а истёкший маркер привёл бы к повторному начислению баллов.
        return <<<'LUA'
            if redis.call('EXISTS', KEYS[1]) == 1 then
                return 'already_processed'
            end

            for i = 2, #KEYS do
                redis.call('ZINCRBY', KEYS[i], ARGV[1], ARGV[2])
            end

            redis.call('SET', KEYS[1], '1')

            return 'processed'
        LUA;
    }
}
