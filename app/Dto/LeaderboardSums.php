<?php

namespace App\Dto;

final readonly class LeaderboardSums
{
    /**
     * @param  array<int, int>  $all  суммарные баллы пользователей за всё время
     * @param  array<int, int>  $daily  суммарные баллы пользователей за текущий день
     * @param  array<int, int>  $weekly  суммарные баллы пользователей за текущую неделю
     * @param  array<int, int>  $monthly  суммарные баллы пользователей за текущий месяц
     */
    public function __construct(
        public array $all = [],
        public array $daily = [],
        public array $weekly = [],
        public array $monthly = [],
    ) {}

    /**
     * Возвращает баллы пользователей для заданного периода.
     *
     * @return array<int, int>
     */
    public function forPeriod(string $period): array
    {
        return match ($period) {
            'daily' => $this->daily,
            'weekly' => $this->weekly,
            'monthly' => $this->monthly,
            default => $this->all,
        };
    }
}
