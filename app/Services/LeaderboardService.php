<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;

class LeaderboardService
{
    public const KEY = 'ranking:all';

    public const PERIODS = ['all', 'daily', 'weekly', 'monthly'];

    public static function keyForPeriod(string $period, ?Carbon $date = null): string
    {
        $date ??= Carbon::now();

        return match ($period) {
            'daily' => 'ranking:daily:'.$date->format('Y-m-d'),
            'weekly' => 'ranking:weekly:'.$date->format('o-\WW'),
            'monthly' => 'ranking:monthly:'.$date->format('Y-m'),
            default => self::KEY,
        };
    }

    /**
     * @return array{data: array<int, array{rank: int, user: array{id: int, name: string}, score: int}>, meta: array{page: int, per_page: int, period: string}}
     */
    public function paginate(string $period = 'all', int $page = 1, int $perPage = 10): array
    {
        $key = self::keyForPeriod($period);
        $start = ($page - 1) * $perPage;
        $stop = $start + $perPage - 1;

        $entries = Redis::zrevrange($key, $start, $stop, true);

        return [
            'data' => $this->formatEntries($entries, $start),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'period' => $period,
            ],
        ];
    }

    /**
     * @return array{user_id: int, score: ?int, rank: ?int}
     */
    public function rank(int $userId, string $period = 'all'): array
    {
        $key = self::keyForPeriod($period);
        $member = (string) $userId;

        $rank = Redis::zrevrank($key, $member);
        $score = Redis::zscore($key, $member);

        if ($rank === null || $rank === false) {
            return [
                'user_id' => $userId,
                'score' => null,
                'rank' => null,
            ];
        }

        return [
            'user_id' => $userId,
            'score' => (int) $score,
            'rank' => $rank + 1,
        ];
    }

    /**
     * @return array{user_id: int, score: ?int, rank: ?int, above: array<int, array{rank: int, user: array{id: int, name: string}, score: int}>, below: array<int, array{rank: int, user: array{id: int, name: string}, score: int}>}
     */
    public function neighbors(int $userId, int $limit = 1, string $period = 'all'): array
    {
        $key = self::keyForPeriod($period);
        $member = (string) $userId;

        $rank = Redis::zrevrank($key, $member);
        $score = Redis::zscore($key, $member);

        if ($rank === null || $rank === false) {
            return [
                'user_id' => $userId,
                'score' => null,
                'rank' => null,
                'above' => [],
                'below' => [],
            ];
        }

        $above = [];
        if ($rank > 0) {
            $aboveStart = max($rank - $limit, 0);
            $aboveStop = $rank - 1;
            $above = Redis::zrevrange($key, $aboveStart, $aboveStop, true);
        }

        $belowStart = $rank + 1;
        $belowStop = $rank + $limit;
        $below = Redis::zrevrange($key, $belowStart, $belowStop, true);

        return [
            'user_id' => $userId,
            'score' => (int) $score,
            'rank' => $rank + 1,
            'above' => $this->formatEntries($above, max($rank - $limit, 0)),
            'below' => $this->formatEntries($below, $belowStart),
        ];
    }

    /**
     * @param  array<int|string, int|string>  $entries  member => score
     * @return array<int, array{rank: int, user: array{id: int, name: string}, score: int}>
     */
    private function formatEntries(array $entries, int $startRank): array
    {
        $users = $this->usersByIds($entries);

        $data = [];
        $rank = $startRank + 1;

        foreach ($entries as $userId => $score) {
            $user = $users->get((int) $userId);

            if ($user === null) {
                continue;
            }

            $data[] = [
                'rank' => $rank,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
                'score' => (int) $score,
            ];

            $rank++;
        }

        return $data;
    }

    /**
     * @param  array<int|string, int|string>  $entries
     */
    private function usersByIds(array $entries): Collection
    {
        return User::whereIn('id', array_map('intval', array_keys($entries)))
            ->get()
            ->keyBy('id');
    }
}
