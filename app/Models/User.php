<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasUuids, HasApiTokens;

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
     * Get items owned by this user
     */
    public function items()
    {
        return $this->belongsToMany(Item::class, 'user_items')
            ->withPivot('metadata')
            ->withTimestamps()
            ->using(UserItem::class);
    }

    /**
     * Get collections favorited by this user
     */
    public function favoriteCollections()
    {
        return $this->belongsToMany(Item::class, 'user_collection_favorites', 'user_id', 'collection_id')
            ->select('items.*')
            ->withTimestamps()
            ->using(UserCollectionFavorite::class);
    }

    /**
     * Get images uploaded by this user
     */
    public function uploadedImages()
    {
        return $this->hasMany(ItemImage::class);
    }
}
