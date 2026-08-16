<?php

namespace App\Console\Commands;

use App\Models\UserAction;
use App\Services\LeaderboardService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class RebuildLeaderboard extends Command
{
    protected $signature = 'leaderboard:rebuild';

    protected $description = 'Восстановить leaderboard в Redis из PostgreSQL';

    public function handle(): int
    {
        $namespace = 'rebuild:'.(string) Str::uuid();
        $now = Carbon::now();

        $periods = [
            'all' => null,
            'daily' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'weekly' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'monthly' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        ];

        $tempKeys = [];
        $finalKeys = [];

        foreach (array_keys($periods) as $period) {
            $tempKeys[$period] = LeaderboardService::keyForPeriod($period, $now, $namespace);
            $finalKeys[$period] = LeaderboardService::keyForPeriod($period, $now);
        }

        $buckets = array_fill_keys(array_keys($periods), []);

        try {
            $this->info('Сборка нового leaderboard в namespace '.$namespace.'...');

            UserAction::query()
                ->select('id', 'user_id', 'points', 'created_at')
                ->chunkById(1000, function (Collection $chunk) use (&$buckets, $periods): void {
                    foreach ($chunk as $action) {
                        $userId = (string) $action->user_id;
                        $points = (int) $action->points;

                        $buckets['all'][$userId] = ($buckets['all'][$userId] ?? 0) + $points;

                        foreach (['daily', 'weekly', 'monthly'] as $period) {
                            [$start, $end] = $periods[$period];

                            if ($action->created_at->betweenIncluded($start, $end)) {
                                $buckets[$period][$userId] = ($buckets[$period][$userId] ?? 0) + $points;
                            }
                        }
                    }
                });

            foreach ($tempKeys as $period => $tempKey) {
                if ($buckets[$period] === []) {
                    Redis::del($finalKeys[$period]);

                    continue;
                }

                Redis::zadd($tempKey, $buckets[$period]);
                Redis::rename($tempKey, $finalKeys[$period]);
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
