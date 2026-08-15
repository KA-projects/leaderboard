<?php

namespace App\Dto;

final readonly class LeaderboardPage
{
    /**
     * @param  list<LeaderboardEntry>  $data
     */
    public function __construct(
        /** Записи лидерборда на текущей странице. */
        public array $data,
        /** Номер текущей страницы, начиная с 1. */
        public int $page,
        /** Количество записей на странице. */
        public int $perPage,
        /** Период рейтинга: all, daily, weekly или monthly. */
        public string $period,
    ) {
    }

    /**
     * @return array{data: list<array{rank: int, user: array{id: int, name: string}, score: int}>, meta: array{page: int, per_page: int, period: string}}
     */
    public function toArray(): array
    {
        return [
            'data' => array_map(fn (LeaderboardEntry $entry) => $entry->toArray(), $this->data),
            'meta' => [
                'page' => $this->page,
                'per_page' => $this->perPage,
                'period' => $this->period,
            ],
        ];
    }
}
