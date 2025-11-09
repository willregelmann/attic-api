<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

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

    /**
     * Get user items (references to Supabase entities)
     * Note: entity_id references Supabase entity UUID - no local Item relationship
     */
    public function userItems(): HasMany
    {
        return $this->hasMany(UserItem::class);
    }

    /**
     * Get favorite collections for this user
     * Note: collection_id references Supabase collection UUID
     */
    public function favoriteCollections(): HasMany
    {
        return $this->hasMany(UserCollectionFavorite::class);
    }

    /**
     * Get API tokens belonging to this user
     */
    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    /**
     * Get wishlist entries for this user
     * Note: entity_id references Supabase entity UUID
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Get user's custom collections
     */
    public function userCollections(): HasMany
    {
        return $this->hasMany(UserCollection::class);
    }
}
