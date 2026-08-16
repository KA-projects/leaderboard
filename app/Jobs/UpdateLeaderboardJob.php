<?php

namespace App\Jobs;

use App\Models\UserAction;
use App\Services\LeaderboardService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateLeaderboardJob implements ShouldQueue
{
    use Queueable;

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

    public function handle(LeaderboardService $leaderboardService): void
    {
        try {
            $action = UserAction::findOrFail($this->actionId);

            $leaderboardService->processAction($action);
        } catch (Throwable $e) {
            Log::error('Failed to update leaderboard', [
                'action_id' => $this->actionId,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
