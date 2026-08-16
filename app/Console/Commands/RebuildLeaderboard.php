<?php

namespace App\Console\Commands;

use App\Services\LeaderboardService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class RebuildLeaderboard extends Command
{
    protected $signature = 'leaderboard:rebuild';

    protected $description = 'Восстановить leaderboard в Redis из PostgreSQL';

    public function handle(LeaderboardService $leaderboard): int
    {
        $namespace = 'rebuild:'.(string) Str::uuid();
        $now = Carbon::now();

        $tempKeys = [];
        $finalKeys = [];

        foreach (LeaderboardService::PERIODS as $period) {
            $tempKeys[$period] = LeaderboardService::keyForPeriod($period, $now, $namespace);
            $finalKeys[$period] = LeaderboardService::keyForPeriod($period, $now);
        }

        try {
            $this->info('Сборка нового leaderboard в namespace '.$namespace.'...');

            $sums = $leaderboard->sumsFromPostgres($now);

            foreach (LeaderboardService::PERIODS as $period) {
                $bucket = $sums->forPeriod($period);

                if ($bucket === []) {
                    Redis::del($finalKeys[$period]);

                    continue;
                }

                Redis::zadd($tempKeys[$period], $bucket);
                Redis::rename($tempKeys[$period], $finalKeys[$period]);
            }
        } catch (Throwable $e) {
            Redis::del(array_values($tempKeys));

            $this->error('Rebuild завершился с ошибкой: '.$e->getMessage());
            $this->error('Старый leaderboard остался рабочим.');

            return self::FAILURE;
        }

        $this->info('Leaderboard восстановлен из PostgreSQL.');

        return self::SUCCESS;
    }
}
