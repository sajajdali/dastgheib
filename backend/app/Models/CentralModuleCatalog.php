<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralModuleCatalog extends Model
{
    protected $table = 'central_module_catalog';

    protected $fillable = [
        'module_key',
        'name',
        'price',
        'periods',
        'enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'periods' => 'array',
            'enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
