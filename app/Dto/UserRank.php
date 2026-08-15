<?php

namespace App\Dto;

final readonly class UserRank
{
    public function __construct(
        /** ID пользователя. */
        public int $userId,
        /** Количество баллов или null, если пользователя нет в рейтинге. */
        public ?int $score,
        /** Позиция в рейтинге (начиная с 1) или null, если пользователя нет в рейтинге. */
        public ?int $rank,
    ) {}

    /**
     * @return array{user_id: int, score: ?int, rank: ?int}
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'score' => $this->score,
            'rank' => $this->rank,
        ];
    }
}
