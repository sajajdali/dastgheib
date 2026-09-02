<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Expense extends Model { protected $fillable=['occurred_on','category','title','amount','type','payment_status','party','invoice_number','payment_method','campaign_id','note','attachment_path','created_by']; protected $casts=['amount'=>'decimal:2']; public function campaign(){return $this->belongsTo(Campaign::class);} }
