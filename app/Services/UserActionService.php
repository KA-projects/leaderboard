<?php

namespace App\Services;

use App\Enums\UserActionType;
use App\Jobs\UpdateLeaderboardJob;
use App\Models\UserAction;

class UserActionService
{
    public function create(int $userId, UserActionType $type): UserAction
    {
        $action = UserAction::create([
            'user_id' => $userId,
            'type' => $type,
            'points' => $type->points(),
        ]);

        UpdateLeaderboardJob::dispatch($action->id);

        return $action;
    }
}
