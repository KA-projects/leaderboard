<?php

namespace App\Console\Commands;

use App\Services\LeaderboardService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

class CheckLeaderboard extends Command
{
    protected $signature = 'leaderboard:check';

    protected $description = 'Сравнить leaderboard в Redis с источником истины в PostgreSQL';

    public function handle(LeaderboardService $leaderboard): int
    {
        $now = Carbon::now();
        $sums = $leaderboard->sumsFromPostgres($now);

        $totalMismatches = 0;

        $this->line('Leaderboard consistency check');

        foreach (LeaderboardService::PERIODS as $period) {
            $key = LeaderboardService::keyForPeriod($period, $now);
            $redis = Redis::zrange($key, 0, -1, true);
            $pg = $sums->forPeriod($period);

            $userIdSet = array_merge(
                array_map('intval', array_keys($pg)),
                array_map('intval', array_keys($redis)),
            );
            $userIds = array_unique($userIdSet);
            sort($userIds);

            $this->newLine();
            $this->line('Period: '.$period);

            if ($userIds === []) {
                $this->line('  no users');

                continue;
            }

            foreach ($userIds as $userId) {
                $pgScore = $pg[$userId] ?? 0;
                $redisScore = (int) ($redis[(string) $userId] ?? 0);

                $this->line('User #'.$userId.': '.($pgScore === $redisScore ? 'OK' : 'MISMATCH'));

                if ($pgScore !== $redisScore) {
                    $totalMismatches++;
                    $this->line('  '.sprintf('%-12s', 'PostgreSQL:').$pgScore);
                    $this->line('  '.sprintf('%-12s', 'Redis:').$redisScore);
                }
            }
        }

        $this->newLine();

        if ($totalMismatches > 0) {
            $this->error('Total mismatches: '.$totalMismatches);

            return self::FAILURE;
        }

        $this->info('No mismatches found.');

        return self::SUCCESS;
    }
}
