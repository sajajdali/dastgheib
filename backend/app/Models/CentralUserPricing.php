<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralUserPricing extends Model
{
    protected $table = 'central_user_pricing';

    protected $fillable = [
        'included_users',
        'extra_user_price',
    ];

    protected function casts(): array
    {
        return [
            'included_users' => 'integer',
            'extra_user_price' => 'integer',
        ];
    }
}
