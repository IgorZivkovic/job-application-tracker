<?php

namespace App\Http\Resources;

use App\Models\ApplicationActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ApplicationActivity */
class ApplicationActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_application_id' => $this->job_application_id,
            'type' => $this->type->value,
            'comment' => $this->comment,
            'metadata' => $this->metadata,
            'actor' => $this->actor === null ? null : [
                'id' => $this->actor->id,
                'email' => $this->actor->email,
            ],
            'occurred_at' => $this->occurred_at->toISOString(),
        ];
    }
}
