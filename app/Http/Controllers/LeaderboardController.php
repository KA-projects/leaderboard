<?php

namespace App\Http\Controllers;

use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaderboardController extends Controller
{
    public function index(Request $request, LeaderboardService $service): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['sometimes', Rule::in(LeaderboardService::PERIODS)],
        ]);

        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 10);

        return response()->json($service->paginate($validated['period'] ?? 'all', $page, $perPage));
    }
}
