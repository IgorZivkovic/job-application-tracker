<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\IgnoreParam;
use Illuminate\Foundation\Http\FormRequest;

#[IgnoreParam('type', 'body')]
#[IgnoreParam('metadata', 'body')]
#[IgnoreParam('actor_auth_user_id', 'body')]
#[IgnoreParam('occurred_at', 'body')]
#[IgnoreParam('job_application_id', 'body')]
class StoreApplicationActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('comment'))) {
            $this->merge(['comment' => trim($this->input('comment'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'max:2000'],
            'type' => ['prohibited'],
            'metadata' => ['prohibited'],
            'actor_auth_user_id' => ['prohibited'],
            'occurred_at' => ['prohibited'],
            'job_application_id' => ['prohibited'],
        ];
    }
}
