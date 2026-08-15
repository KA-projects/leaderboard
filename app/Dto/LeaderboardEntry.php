<?php

namespace App\Dto;

final readonly class LeaderboardEntry
{
    public function __construct(
        /** Позиция пользователя в рейтинге, начиная с 1. */
        public int $rank,
        /** ID пользователя. */
        public int $userId,
        /** Имя пользователя. */
        public string $userName,
        /** Количество набранных баллов. */
        public int $score,
    ) {}

    /**
     * @return array{rank: int, user: array{id: int, name: string}, score: int}
     */
    public function toArray(): array
    {
        return [
            'rank' => $this->rank,
            'user' => [
                'id' => $this->userId,
                'name' => $this->userName,
            ],
            'score' => $this->score,
        ];
    }
}
