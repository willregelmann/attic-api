<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Cache TTL for token lookups (5 minutes)
     */
    protected const TOKEN_CACHE_TTL = 300;

    /**
     * Minimum seconds between last_used_at updates (60 seconds)
     */
    protected const LAST_USED_UPDATE_INTERVAL = 60;

    /**
     * Get the tokenable model that the access token belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function tokenable()
    {
        // Explicitly tell Laravel to use UUID for the morph relationship
        return $this->morphTo('tokenable', 'tokenable_type', 'tokenable_id', 'id');
    }

    /**
     * Find the token instance matching the given token.
     * Overrides parent to add caching.
     *
     * @param  string  $token
     * @return static|null
     */
    public static function findToken($token)
    {
        if (strpos($token, '|') === false) {
            // Non-prefixed tokens: can't cache efficiently since we'd need to hash
            return static::where('token', hash('sha256', $token))->first();
        }

        [$id, $plainToken] = explode('|', $token, 2);

        // Try to get from cache first
        $cacheKey = "sanctum_token:{$id}";
        $cached = Cache::get($cacheKey);

        if ($cached) {
            // Verify the hash still matches (in case token was changed)
            if (hash_equals($cached['token_hash'], hash('sha256', $plainToken))) {
                // Reconstruct the model from cached data
                $instance = new static();
                $instance->exists = true;
                $instance->setRawAttributes($cached['attributes'], true);
                return $instance;
            }
            // Hash mismatch - clear invalid cache
            Cache::forget($cacheKey);
        }

        // Not in cache or invalid - fetch from database
        if ($instance = static::find($id)) {
            if (hash_equals($instance->token, hash('sha256', $plainToken))) {
                // Cache the token data
                Cache::put($cacheKey, [
                    'token_hash' => $instance->token,
                    'attributes' => $instance->getAttributes(),
                ], static::TOKEN_CACHE_TTL);

                return $instance;
            }
        }

        return null;
    }

    /**
     * Override save to throttle last_used_at updates and selectively invalidate cache.
     *
     * @param  array  $options
     * @return bool
     */
    public function save(array $options = [])
    {
        // Check if only last_used_at is being updated
        $dirty = $this->getDirty();
        $onlyLastUsedAt = count($dirty) === 1 && isset($dirty['last_used_at']);

        if ($onlyLastUsedAt) {
            // Throttle last_used_at updates - don't invalidate cache for these
            $throttleKey = "sanctum_last_used:{$this->id}";

            if (Cache::has($throttleKey)) {
                // Skip the update, too soon
                return true;
            }

            // Allow the update but set throttle
            Cache::put($throttleKey, true, static::LAST_USED_UPDATE_INTERVAL);

            // Don't invalidate token cache for last_used_at updates
            return parent::save($options);
        }

        // For any other change, invalidate token cache
        if ($this->exists) {
            Cache::forget("sanctum_token:{$this->id}");
        }

        return parent::save($options);
    }

    /**
     * Override delete to invalidate cache.
     *
     * @return bool|null
     */
    public function delete()
    {
        Cache::forget("sanctum_token:{$this->id}");
        Cache::forget("sanctum_last_used:{$this->id}");

        return parent::delete();
    }
}
