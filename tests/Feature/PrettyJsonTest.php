<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class PrettyJsonTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Без параметра pretty ответ остаётся компактным однострочным JSON.
     */
    public function test_default_response_is_compact(): void
    {
        $this->get('/api/health')
            ->assertOk()
            ->assertContent('{"status":"ok"}');
    }

    /**
     * Параметр pretty=1 переформатирует JSON-ответ с отступами.
     */
    public function test_pretty_param_formats_json_with_indentation(): void
    {
        $response = $this->get('/api/health?pretty=1');

        $response->assertOk();
        $this->assertStringContainsString("\n", $response->getContent());
        $this->assertStringContainsString('    "status": "ok"', $response->getContent());
    }

    /**
     * Параметр pretty=1 применим и к лидерборду, вывод становится читаемым.
     */
    public function test_pretty_param_works_for_leaderboard(): void
    {
        $user = User::factory()->create(['name' => 'Анна']);

        Redis::shouldReceive('zrevrange')
            ->once()
            ->with(LeaderboardService::KEY, 0, 9, true)
            ->andReturn([(string) $user->id => 250]);

        $content = $this->get('/api/leaderboard?pretty=1')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("\n", $content);
        $this->assertStringContainsString('"Анна"', $content);
    }
}
