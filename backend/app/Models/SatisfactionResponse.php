<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SatisfactionResponse extends Model { protected $fillable=['patient_id','appointment_id','answered_on']; public function answers(){return $this->hasMany(SatisfactionAnswer::class);} }
