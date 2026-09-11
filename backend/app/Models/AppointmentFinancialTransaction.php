<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class AppointmentFinancialTransaction extends Model
{
    use Auditable;

    protected $guarded = [];
    protected $casts = ['amount' => 'integer', 'metadata' => 'array', 'due_date' => 'date', 'occurred_at' => 'datetime'];

    public function activitySection(): string { return 'مالی نوبت'; }
    public function appointment() { return $this->belongsTo(Appointment::class); }
    public function patient() { return $this->belongsTo(Patient::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function allocations() { return $this->hasMany(AppointmentFinancialAllocation::class, 'transaction_id'); }
}
