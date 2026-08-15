<?php

namespace App\Http\Controllers;

use App\Enums\UserActionType;
use App\Http\Requests\StoreUserActionRequest;
use App\Services\UserActionService;
use Illuminate\Http\JsonResponse;

class UserActionController extends Controller
{
    public function __construct(
        private readonly UserActionService $userActionService,
    ) {
    }

    public function store(StoreUserActionRequest $request): JsonResponse
    {
        $action = $this->userActionService->create(
            userId: $request->validated('user_id'),
            type: UserActionType::from($request->validated('type')),
        );

        return response()->json($action, 201);
    }
}
