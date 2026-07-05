<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * MongoDB model for trips (Phase 1 will expand this model).
 */
class Trip extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'trips';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'status',
    ];
}
