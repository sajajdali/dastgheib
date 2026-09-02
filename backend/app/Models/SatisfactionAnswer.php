<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SatisfactionAnswer extends Model { protected $fillable=['satisfaction_response_id','question_key','question_label','score']; public function response(){return $this->belongsTo(SatisfactionResponse::class,'satisfaction_response_id');} }
