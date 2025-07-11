<?php

namespace App\GraphQL\Queries;

use App\Models\Collection;
use Illuminate\Support\Facades\Auth;

class Collections
{
    public function __invoke()
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            throw new \Exception('Unauthenticated');
        }

        // Return all collections
        return Collection::all();
    }
}