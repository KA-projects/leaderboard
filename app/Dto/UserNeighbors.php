<?php

namespace App\Dto;

final readonly class UserNeighbors
{
    /**
     * @param  list<LeaderboardEntry>  $above
     * @param  list<LeaderboardEntry>  $below
     */
    public function __construct(
        /** ID пользователя. */
        public int $userId,
        /** Количество баллов или null, если пользователя нет в рейтинге. */
        public ?int $score,
        /** Позиция в рейтинге (начиная с 1) или null, если пользователя нет в рейтинге. */
        public ?int $rank,
        /** Записи пользователей, стоящих выше, ближайшие к текущему. */
        public array $above,
        /** Записи пользователей, стоящих ниже, ближайшие к текущему. */
        public array $below,
    ) {}

    /**
     * @return array{user_id: int, score: ?int, rank: ?int, above: list<array{rank: int, user: array{id: int, name: string}, score: int}>, below: list<array{rank: int, user: array{id: int, name: string}, score: int}>}
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'score' => $this->score,
            'rank' => $this->rank,
            'above' => array_map(fn (LeaderboardEntry $entry) => $entry->toArray(), $this->above),
            'below' => array_map(fn (LeaderboardEntry $entry) => $entry->toArray(), $this->below),
        ];
    }
}
