<?php

namespace App\Models;

use App\Enums\ApplicationActivityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'job_application_id',
    'actor_auth_user_id',
    'type',
    'comment',
    'metadata',
    'occurred_at',
])]
class ApplicationActivity extends Model
{
    public $timestamps = false;

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'actor_auth_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ApplicationActivityType::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
