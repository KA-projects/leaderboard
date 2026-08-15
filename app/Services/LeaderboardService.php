<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Redis;

class LeaderboardService
{
    public const KEY = 'ranking:all';

    /**
     * @return array{data: array<int, array{rank: int, user: array{id: int, name: string}, score: int}>, meta: array{page: int, per_page: int}}
     */
    public function paginate(int $page = 1, int $perPage = 10): array
    {
        $start = ($page - 1) * $perPage;
        $stop = $start + $perPage - 1;

        $entries = Redis::zrevrange(self::KEY, $start, $stop, true);

        $users = User::whereIn('id', array_map('intval', array_keys($entries)))
            ->get()
            ->keyBy('id');

        $data = [];
        $rank = $start + 1;

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

        return [
            'data' => $data,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
            ],
        ];
    }
}
