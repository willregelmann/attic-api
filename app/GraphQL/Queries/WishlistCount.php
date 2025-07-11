<?php

namespace App\GraphQL\Queries;

class WishlistCount
{
    public function __invoke($rootValue, array $args, $context, $resolveInfo)
    {
        // For now, return a random number for demo purposes
        // In the future, this would query actual wishlist data
        return rand(100, 2000);
    }
}