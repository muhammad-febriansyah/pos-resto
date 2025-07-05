<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];

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

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function pemasukans()
    {
        return $this->hasMany(Pemasukan::class, 'user_id');
    }

    public function pengeluaran()
    {
        return $this->hasMany(Pengeluaran::class, 'user_id');
    }

    public function hutang()
    {
        return $this->hasMany(Hutang::class, 'user_id');
    }

    public function piutang()
    {
        return $this->hasMany(Piutang::class, 'user_id');
    }

    public function penjualan()
    {
        return $this->hasMany(Penjualan::class);
    }

    public function rating()
    {
        return $this->hasMany(Rating::class);
    }
}
