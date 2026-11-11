<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Role and is_active are deliberately not mass assignable. */
    protected $fillable = ['name', 'email', 'password', 'phone'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isStaff(): bool
    {
        return $this->hasPermission('admin.access');
    }

    public function hasPermission(string $permission): bool
    {
        // Permission names contain dots, so read the map directly rather than via dot-notation.
        $roles = config('store.permissions')[$permission] ?? [];

        return (bool) $this->is_active && in_array($this->role, $roles, true);
    }

    public function roleLabel(): string
    {
        return config("store.roles.{$this->role}", ucfirst($this->role));
    }

    public function scopeCustomers(Builder $query): void
    {
        $query->where('role', 'customer');
    }

    public function scopeStaff(Builder $query): void
    {
        $query->where('role', '!=', 'customer');
    }
}
