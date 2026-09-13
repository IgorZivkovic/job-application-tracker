<?php

namespace App\Http\Requests;

use App\Enums\JobApplicationStatus;
use App\Enums\WorkMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListJobApplicationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'search' => ['sometimes', 'string', 'max:160'],
            'status' => ['sometimes', Rule::enum(JobApplicationStatus::class)],
            'work_mode' => ['sometimes', Rule::enum(WorkMode::class)],
            'company_id' => ['sometimes', 'integer', 'min:1'],
            'sort' => [
                'sometimes',
                Rule::in([
                    'position',
                    'status',
                    'board_order',
                    'work_mode',
                    'applied_at',
                    'next_action_at',
                    'created_at',
                ]),
            ],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
