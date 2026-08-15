<?php

namespace App\Jobs;

use App\Models\UserAction;
use App\Services\LeaderboardService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

class UpdateLeaderboardJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $actionId,
    ) {
    }

    public function handle(): void
    {
        $action = UserAction::findOrFail($this->actionId);

        $keys = [
            LeaderboardService::KEY,
            LeaderboardService::keyForPeriod('daily', $action->created_at),
            LeaderboardService::keyForPeriod('weekly', $action->created_at),
            LeaderboardService::keyForPeriod('monthly', $action->created_at),
        ];

        $connection = Redis::connection();

        foreach ($keys as $key) {
            $connection->zincrby($key, $action->points, (string) $action->user_id);
        }
    }
}
