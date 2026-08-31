<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'two_factor_secret',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function assignedCases()
    {
        return $this->hasMany(CaseModel::class, 'assigned_expert_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function avatarUrl(): ?string
    {
        if (!$this->avatar) {
            return null;
        }
        if (str_starts_with($this->avatar, 'http')) {
            return $this->avatar;
        }
        return asset('storage/'.$this->avatar);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/u', trim($this->name ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: ['؟'];
        $a = mb_substr($parts[0], 0, 1);
        $b = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '';
        return mb_strtoupper($a.$b);
    }
}
