<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ResourceAdjustment extends Model { protected $fillable=['month','resource_type','resource_id','amount','reason','created_by']; protected $casts=['amount'=>'decimal:2']; }
