<?php

namespace App\Jobs;

use App\Models\UserAction;
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

        Redis::connection()->zincrby('ranking:all', $action->points, (string) $action->user_id);
    }
}
