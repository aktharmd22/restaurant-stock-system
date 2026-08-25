<?php

namespace App\Models;

use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'branch_id',
        'name',
        'email',
        'phone',
        'password',
        'is_active',
        'sound_enabled',
        'sound_volume',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'sound_enabled' => 'boolean',
            'sound_volume' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Admin-side people see every branch. Branch-side people see only their own.
     * Everything else in the app keys off this one distinction.
     */
    public function isAdminSide(): bool
    {
        return $this->hasAnyRole([RoleName::SuperAdmin->value, RoleName::MainAdmin->value]);
    }

    public function isBranchSide(): bool
    {
        return ! $this->isAdminSide();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleName::SuperAdmin->value);
    }

    /**
     * First name only - every greeting in the branch app uses this.
     */
    public function firstName(): string
    {
        return trim(explode(' ', trim($this->name))[0]);
    }

    /**
     * Never the password, never the remember token - only the things someone
     * might later need to ask "who changed that?" about.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'branch_id', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
