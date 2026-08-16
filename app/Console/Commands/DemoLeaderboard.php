<?php

namespace App\Console\Commands;

use App\Dto\LeaderboardEntry;
use App\Enums\UserActionType;
use App\Models\User;
use App\Models\UserAction;
use App\Services\LeaderboardService;
use App\Services\UserActionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class DemoLeaderboard extends Command
{
    protected $signature = 'demo {--fresh : Удалить всех пользователей и действия перед запуском} {--wait=30 : Сколько секунд ждать обработку очереди}';

    protected $description = 'Создать демо-данные и показать весь функционал приложения';

    public function handle(UserActionService $actions, LeaderboardService $leaderboard): int
    {
        if ($this->option('fresh')) {
            $this->fresh();
        }

        $this->info('Создание пользователей...');
        $users = $this->createUsers();

        $this->info('Создание действий пользователей...');
        $expected = $this->createActions($actions, $users);

        $this->info('Ожидание обработки заданий в очереди...');
        if (! $this->waitForQueue($expected, (int) $this->option('wait'))) {
            $this->warn('Очередь не успела обработать задания. Проверьте: docker compose up -d queue');
        }

        $this->showUsers($users);
        $this->showActions($users);
        $this->showLeaderboard($leaderboard);
        $this->showScoresByPeriod($leaderboard, $users);
        $this->showRanks($leaderboard, $users);
        $this->showNeighbors($leaderboard, $users);

        $this->newLine();
        $this->info('Готово. Данные сохранены в PostgreSQL и Redis.');
        $this->info('Попробуйте: curl -s "localhost:8000/api/leaderboard?pretty=1"');

        return self::SUCCESS;
    }

    private function fresh(): void
    {
        $this->warn('Очистка базы и рейтингов...');

        UserAction::query()->delete();
        User::query()->delete();

        $keys = [LeaderboardService::KEY];

        foreach (['daily', 'weekly', 'monthly'] as $period) {
            $keys[] = LeaderboardService::keyForPeriod($period);
        }

        Redis::del($keys);
    }

    /**
     * Создаёт демо-пользователей: имена и email генерируются через faker.
     *
     * @return list<User>
     */
    private function createUsers(): array
    {
        $users = [];

        for ($i = 0; $i < 6; $i++) {
            $users[] = User::create([
                'name' => fake()->unique()->name(),
                'email' => fake()->unique()->safeEmail(),
            ]);
        }

        return $users;
    }

    /**
     * Создаёт случайные действия: количество, типы и даты выбираются через faker
     * для каждого пользователя отдельно, поэтому баллы сильно различаются.
     *
     * @param  list<User>  $users
     * @return array<int, int> userId => сумма баллов
     */
    private function createActions(UserActionService $actions, array $users): array
    {
        $expected = [];

        foreach ($users as $user) {
            // У каждого пользователя свой уровень активности
            $actionCount = fake()->numberBetween(5, 15);

            for ($i = 0; $i < $actionCount; $i++) {
                $type = fake()->randomElement(UserActionType::cases());
                // Часть действий — сегодня, остальные — в прошлые периоды
                $offsetDays = fake()->randomElement([0, 0, 1, 8, 35]);
                $createdAt = $offsetDays === 0 ? null : now()->subDays($offsetDays);

                $actions->create($user->id, $type, $createdAt);

                $expected[$user->id] = ($expected[$user->id] ?? 0) + $type->points();
            }
        }

        return $expected;
    }

    /**
     * @param  array<int, int>  $expected
     */
    private function waitForQueue(array $expected, int $seconds): bool
    {
        $deadline = now()->addSeconds($seconds);
        $total = array_sum($expected);

        while (now()->lessThan($deadline)) {
            $sum = 0;
            $ready = true;

            foreach ($expected as $userId => $score) {
                $current = (int) Redis::zscore(LeaderboardService::KEY, (string) $userId);
                $sum += $current;

                if ($current < $score) {
                    $ready = false;
                }
            }

            if ($ready && $sum >= $total) {
                return true;
            }

            usleep(500_000);
        }

        return false;
    }

    /**
     * @param  list<User>  $users
     */
    private function showUsers(array $users): void
    {
        $this->newLine();
        $this->info('Пользователи (сохранены в PostgreSQL)');
        $this->table(
            ['ID', 'Имя', 'Email'],
            array_map(fn (User $user) => [$user->id, $user->name, $user->email], $users),
        );
    }

    /**
     * @param  list<User>  $users
     */
    private function showActions(array $users): void
    {
        $actions = UserAction::whereIn('user_id', array_column($users, 'id'))->get();

        $rows = [];

        foreach ($users as $user) {
            $userActions = $actions->where('user_id', $user->id);

            $summary = $userActions
                ->groupBy(fn (UserAction $action) => $action->type->value)
                ->map(fn ($group) => $group->count().'x '.$group->first()->type->value)
                ->values()
                ->implode(', ');

            $rows[] = [
                $user->name,
                $summary ?: '-',
                $userActions->count(),
                $userActions->sum('points'),
            ];
        }

        $this->newLine();
        $this->info('Действия (сохранены в PostgreSQL, баллы учтены через очередь)');
        $this->table(['Пользователь', 'Типы действий', 'Всего действий', 'Баллы'], $rows);
    }

    private function showLeaderboard(LeaderboardService $leaderboard): void
    {
        foreach (['all', 'daily', 'weekly', 'monthly'] as $period) {
            $this->newLine();
            $this->info('Лидерборд (период: '.$period.')');
            $this->renderLeaderboardTable($leaderboard->paginate($period, 1, 10)->data);
        }
    }

    /**
     * @param  list<LeaderboardEntry>  $entries
     */
    private function renderLeaderboardTable(array $entries): void
    {
        if ($entries === []) {
            $this->warn('  нет данных');

            return;
        }

        $this->table(
            ['Место', 'Пользователь', 'Баллы'],
            array_map(fn (LeaderboardEntry $entry) => [$entry->rank, $entry->userName, $entry->score], $entries),
        );
    }

    /**
     * @param  list<User>  $users
     */
    private function showScoresByPeriod(LeaderboardService $leaderboard, array $users): void
    {
        $this->newLine();
        $this->info('Баллы каждого пользователя по периодам');

        $rows = [];

        foreach ($users as $user) {
            $scores = [];

            foreach (['daily', 'weekly', 'monthly', 'all'] as $period) {
                $scores[] = (string) ($leaderboard->rank($user->id, $period)->score ?? 0);
            }

            $rows[] = array_merge([$user->name], $scores);
        }

        $this->table(['Пользователь', 'daily', 'weekly', 'monthly', 'all'], $rows);
    }

    /**
     * @param  list<User>  $users
     */
    private function showRanks(LeaderboardService $leaderboard, array $users): void
    {
        $this->newLine();
        $this->info('Ранг каждого пользователя (период: all)');
        $this->table(
            ['Пользователь', 'Место', 'Баллы'],
            array_map(fn (User $user) => $this->rankRow($leaderboard, $user), $users),
        );
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    private function rankRow(LeaderboardService $leaderboard, User $user): array
    {
        $rank = $leaderboard->rank($user->id);

        return [$user->name, (string) ($rank->rank ?? '-'), $rank->score ?? 0];
    }

    /**
     * @param  list<User>  $users
     */
    private function showNeighbors(LeaderboardService $leaderboard, array $users): void
    {
        $this->newLine();
        $this->info('Соседи каждого пользователя (2 выше и 2 ниже, период: all)');

        foreach ($users as $user) {
            $neighbors = $leaderboard->neighbors($user->id, 2);

            $around = [];

            foreach (array_merge($neighbors->above, $neighbors->below) as $entry) {
                $around[] = '#'.$entry->rank.' '.$entry->userName.' ('.$entry->score.')';
            }

            $this->line(sprintf(
                '  %s (ID %d): место #%s, баллы %d — соседи: %s',
                $user->name,
                $user->id,
                $neighbors->rank ?? '-',
                $neighbors->score ?? 0,
                $around === [] ? 'нет' : implode(', ', $around),
            ));
        }
    }
}
