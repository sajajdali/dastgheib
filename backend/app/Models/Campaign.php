<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Campaign extends Model { protected $fillable=['name','channel_id','starts_on','ends_on','budget','note','active','ui_payload']; protected $casts=['budget'=>'decimal:2','active'=>'boolean','ui_payload'=>'array']; public function expenses(){return $this->hasMany(Expense::class);} }
