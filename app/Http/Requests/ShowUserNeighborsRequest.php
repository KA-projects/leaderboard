<?php

namespace App\Http\Requests;

use App\Services\LeaderboardService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowUserNeighborsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', Rule::in(LeaderboardService::PERIODS)],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
