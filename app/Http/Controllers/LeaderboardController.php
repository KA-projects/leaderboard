<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexLeaderboardRequest;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;

class LeaderboardController extends Controller
{
    public function index(IndexLeaderboardRequest $request, LeaderboardService $service): JsonResponse
    {
        return response()->json($service->paginate(
            period: $request->validated('period') ?? 'all',
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 10),
        )->toArray());
    }
}
