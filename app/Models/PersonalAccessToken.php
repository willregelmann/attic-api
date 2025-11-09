<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
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
}
