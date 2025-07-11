<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait UsesUuid
{
    /**
     * Boot function for the trait.
     */
    protected static function bootUsesUuid()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
    }

    /**
     * Get the primary key type.
     */
    public function getKeyType()
    {
        return 'string';
    }

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public function getIncrementing()
    {
        return false;
    }
}