<?php

namespace App\GraphQL\Queries;

class Health
{
    public function __invoke(): string
    {
        return 'GraphQL endpoint is healthy';
    }
}