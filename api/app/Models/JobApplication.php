<?php

namespace App\Models;

use App\Enums\JobApplicationStatus;
use App\Enums\WorkMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'position',
    'status',
    'board_order',
    'work_mode',
    'employment_type',
    'source_url',
    'applied_at',
    'next_action_at',
    'salary_min',
    'salary_max',
    'currency',
    'notes',
])]
class JobApplication extends Model
{
    use HasFactory;

    public function scopeOwnedBy(Builder $query, AuthUser $authUser): Builder
    {
        return $query->whereHas(
            'company',
            fn (Builder $companyQuery) => $companyQuery->where('auth_user_id', $authUser->getKey()),
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ApplicationActivity::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JobApplicationStatus::class,
            'board_order' => 'integer',
            'work_mode' => WorkMode::class,
            'applied_at' => 'date:Y-m-d',
            'next_action_at' => 'datetime',
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
        ];
    }
}
