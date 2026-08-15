<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return response()->json($user, 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function rank(User $user, Request $request, LeaderboardService $service): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['sometimes', Rule::in(LeaderboardService::PERIODS)],
        ]);

        return response()->json($service->rank($user->id, $validated['period'] ?? 'all'));
    }

    public function neighbors(User $user, Request $request, LeaderboardService $service): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['sometimes', Rule::in(LeaderboardService::PERIODS)],
        ]);

        $limit = max(1, (int) $request->query('limit', 1));

        return response()->json($service->neighbors($user->id, $limit, $validated['period'] ?? 'all'));
    }
}
