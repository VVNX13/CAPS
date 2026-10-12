<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Every owner type this account may see.
     *
     * @return list<string>
     */
    public function ownerTypes(): array
    {
        return match ($this->role) {
            'owner_minuman' => ['minuman'],
            'owner_makanan' => ['makanan'],
            default => ['makanan', 'minuman'],
        };
    }

    public function canManage(string $ownerType): bool
    {
        return in_array($ownerType, $this->ownerTypes(), true);
    }

    /**
     * Primary owner type, used where a single scope has to be picked.
     */
    public function getOwnerTypeAttribute(): string
    {
        return $this->ownerTypes()[0];
    }

    /**
     * Human readable scope, e.g. "Makanan", "Minuman", "Semua Kategori".
     */
    public function scopeLabel(): string
    {
        return match ($this->role) {
            'owner_minuman' => 'Minuman',
            'owner_makanan' => 'Makanan',
            default => 'Semua Kategori',
        };
    }

    /**
     * Badge text, e.g. "MAKANAN", "MINUMAN", "ADMIN".
     */
    public function scopeBadge(): string
    {
        return $this->isAdmin() ? 'ADMIN' : strtoupper($this->ownerType);
    }

    /**
     * Label for the button that moves a pending item into production.
     */
    public function startWorkingLabel(): string
    {
        return match ($this->role) {
            'owner_minuman' => 'Mulai Buat Minuman',
            'owner_makanan' => 'Mulai Masak',
            default => 'Mulai Proses',
        };
    }
}
