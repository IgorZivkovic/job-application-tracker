<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['email', 'password', 'role'])]
#[Hidden(['password'])]
class AuthUser extends Authenticatable
{
    use HasFactory;

    public $timestamps = false;

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function applicationActivities(): HasMany
    {
        return $this->hasMany(ApplicationActivity::class, 'actor_auth_user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }
}
