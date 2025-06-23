<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'google_id',
        'google_avatar',
        'email_verified_at',
        'profile',
        'preferences',
        'trade_rating',
        'subscription',
        'last_active_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'google_id',
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
            'profile' => 'array',
            'preferences' => 'array',
            'trade_rating' => 'array',
            'subscription' => 'array',
            'last_active_at' => 'datetime',
        ];
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class, 'contributed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function collectibles(): HasMany
    {
        return $this->hasMany(Collectible::class, 'contributed_by');
    }
}
