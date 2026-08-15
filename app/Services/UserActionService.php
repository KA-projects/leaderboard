<?php

namespace App\Services;

use App\Enums\UserActionType;
use App\Jobs\UpdateLeaderboardJob;
use App\Models\UserAction;
use Illuminate\Support\Carbon;

class UserActionService
{
    /**
     * Записывает действие пользователя и отправляет задание на обновление рейтинга.
     *
     * @param  int  $userId  ID пользователя, совершившего действие
     * @param  UserActionType  $type  тип действия (определяет количество баллов)
     * @param  Carbon|null  $createdAt  только для демонстрации тестов: дата действия; определяет, в какие периоды
     *                                  рейтинга (daily/weekly/monthly) попадут баллы,
     *                                  null означает «сейчас»
     * @return UserAction созданное действие
     */
    public function create(int $userId, UserActionType $type, ?Carbon $createdAt = null): UserAction
    {
        $action = UserAction::create([
            'user_id' => $userId,
            'type' => $type,
            'points' => $type->points(),
        ]);

        if ($createdAt !== null) {
            $action->forceFill(['created_at' => $createdAt])->save();
        }

        UpdateLeaderboardJob::dispatch($action->id);

        return $action;
    }
}
