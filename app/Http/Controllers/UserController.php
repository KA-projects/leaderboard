<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowUserNeighborsRequest;
use App\Http\Requests\ShowUserRankRequest;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;

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

    public function rank(User $user, ShowUserRankRequest $request, LeaderboardService $service): JsonResponse
    {
        return response()->json($service->rank($user->id, $request->validated('period') ?? 'all')->toArray());
    }

    public function neighbors(User $user, ShowUserNeighborsRequest $request, LeaderboardService $service): JsonResponse
    {
        return response()->json($service->neighbors(
            $user->id,
            $request->integer('limit', 1),
            $request->validated('period') ?? 'all',
        )->toArray());
    }
}
