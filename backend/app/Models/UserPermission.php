<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPermission extends Model
{
    // آرایه باید تک لایه باشد
    protected $fillable = [
        'user_id', 
        'module_id', 
        'permissions'
    ];

    // این بخش بسیار مهم است تا آرایه جی‌سان راحت در فرانت لود و ذخیره شود
    protected $casts = [
        'permissions' => 'array'
    ];
}