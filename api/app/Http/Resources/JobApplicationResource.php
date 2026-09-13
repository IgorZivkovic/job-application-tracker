<?php

namespace App\Http\Resources;

use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JobApplication */
class JobApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'company' => [
                'id' => $this->company->id,
                'name' => $this->company->name,
            ],
            'position' => $this->position,
            'status' => $this->status->value,
            'board_order' => $this->board_order,
            'work_mode' => $this->work_mode->value,
            'employment_type' => $this->employment_type,
            'source_url' => $this->source_url,
            'applied_at' => $this->applied_at?->format('Y-m-d'),
            'next_action_at' => $this->next_action_at?->toISOString(),
            'salary_min' => $this->salary_min === null ? null : (float) $this->salary_min,
            'salary_max' => $this->salary_max === null ? null : (float) $this->salary_max,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'interviews' => InterviewResource::collection($this->whenLoaded('interviews')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
