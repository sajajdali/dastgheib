<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ServiceFollowup extends Model {
    protected $fillable = ['appointment_id','inventory_id','service_name','patient_name','patient_phone','completed_at','due_date','followup_days','status','action_note','actioned_at','actioned_by','source_key'];
    protected $casts = ['completed_at'=>'datetime','due_date'=>'date','actioned_at'=>'datetime'];
}
