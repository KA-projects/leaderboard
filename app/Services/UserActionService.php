<?php

namespace App\Services;

use App\Enums\UserActionType;
use App\Models\UserAction;

class UserActionService
{
    public function create(int $userId, UserActionType $type): UserAction
    {
        return UserAction::create([
            'user_id' => $userId,
            'type' => $type,
            'points' => $type->points(),
        ]);
    }
}
