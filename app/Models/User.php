<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasRoles;
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
    'name',
    'email',
    'password',
    'company_id',
    'role',
];

protected $casts = [
    'email_verified_at' => 'datetime',
    'password'          => 'hashed',
];

// Relaciones
public function company()
{
    return $this->belongsTo(Company::class);
}

// Helpers


public function canAccessPanel(Panel $panel): bool
{
    return $this->roles()->exists();
}

    public function isSuperAdmin(): bool
{
    return $this->role === 'superadmin';
}

public function isAdmin(): bool
{
    return in_array($this->role, ['superadmin', 'admin']);
}

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
