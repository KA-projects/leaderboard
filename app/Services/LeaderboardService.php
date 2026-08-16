<?php

namespace App\Services;

use App\Dto\LeaderboardEntry;
use App\Dto\LeaderboardPage;
use App\Dto\UserNeighbors;
use App\Dto\UserRank;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;

class LeaderboardService
{
    public const KEY = 'ranking:all';

    public const PERIODS = ['all', 'daily', 'weekly', 'monthly'];

    /**
     * Возвращает ключ Redis для заданного периода.
     *
     * @param  string  $period  период ('all', 'daily', 'weekly', 'monthly')
     * @param  Carbon|null  $date  дата, для которой строится ключ периода (по умолчанию сейчас)
     * @param  string|null  $namespace  префикс временного namespace вида 'rebuild:{uuid}';
     *                                  позволяет строить ключи без затрагивания рабочих
     * @return string ключ Redis
     */
    public static function keyForPeriod(string $period, ?Carbon $date = null, ?string $namespace = null): string
    {
        $date ??= Carbon::now();

        $prefix = $namespace === null ? '' : $namespace.':';

        return match ($period) {
            'daily' => 'ranking:'.$prefix.'daily:'.$date->format('Y-m-d'),
            'weekly' => 'ranking:'.$prefix.'weekly:'.$date->format('o-\WW'),
            'monthly' => 'ranking:'.$prefix.'monthly:'.$date->format('Y-m'),
            default => 'ranking:'.$prefix.'all',
        };
    }

    public function paginate(string $period = 'all', int $page = 1, int $perPage = 10): LeaderboardPage
    {
        $key = self::keyForPeriod($period);
        $start = ($page - 1) * $perPage;
        $stop = $start + $perPage - 1;

        $entries = Redis::zrevrange($key, $start, $stop, true);

        return new LeaderboardPage(
            data: $this->formatEntries($entries, $start),
            page: $page,
            perPage: $perPage,
            period: $period,
        );
    }

    public function rank(int $userId, string $period = 'all'): UserRank
    {
        $key = self::keyForPeriod($period);
        $member = (string) $userId;

        $rank = Redis::zrevrank($key, $member);
        $score = Redis::zscore($key, $member);

        if ($rank === null || $rank === false) {
            return new UserRank($userId, null, null);
        }

        return new UserRank($userId, (int) $score, $rank + 1);
    }

    public function neighbors(int $userId, int $limit = 1, string $period = 'all'): UserNeighbors
    {
        $key = self::keyForPeriod($period);
        $member = (string) $userId;

        $rank = Redis::zrevrank($key, $member);
        $score = Redis::zscore($key, $member);

        if ($rank === null || $rank === false) {
            return new UserNeighbors($userId, null, null, [], []);
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

        return new UserNeighbors(
            userId: $userId,
            score: (int) $score,
            rank: $rank + 1,
            above: $this->formatEntries($above, max($rank - $limit, 0)),
            below: $this->formatEntries($below, $belowStart),
        );
    }

    /**
     * @param  array<int|string, int|string>  $entries  member => score
     * @return list<LeaderboardEntry>
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

            $data[] = new LeaderboardEntry(
                rank: $rank,
                userId: $user->id,
                userName: $user->name,
                score: (int) $score,
            );

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
