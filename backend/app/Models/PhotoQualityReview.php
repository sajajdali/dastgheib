<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PhotoQualityReview extends Model { protected $fillable=['patient_media_id','service_tag','is_qualified','reviewed_by','reviewed_on']; protected $casts=['is_qualified'=>'boolean']; }
