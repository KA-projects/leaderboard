<?php

namespace App\Http\Controllers;

use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function index(Request $request, LeaderboardService $service): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 10);

        return response()->json($service->paginate($page, $perPage));
    }
}
