<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResourceEarningLine extends Model
{
    protected $fillable = [
        'appointment_id',
        'month',
        'day_num',
        'earned_at',
        'resource_type',
        'resource_id',
        'resource_name',
        'earning_type',
        'inventory_id',
        'inventory_name',
        'service_name',
        'service_line_index',
        'is_addon',
        'quantity',
        'gross_amount',
        'discount_amount',
        'net_amount',
        'material_cost',
        'commission_base',
        'commission_type',
        'commission_value',
        'amount',
        'status',
        'manually_edited',
        'edited_by_user_id',
        'edited_by_name',
        'edited_at',
        'deleted_by_user_id',
        'deleted_by_name',
        'deleted_at',
        'commission_after_materials',
        'commission_customer_scope',
        'appointment_new_customer',
        'calculation_snapshot',
        'audit_events',
        'description',
    ];

    protected $casts = [
        'earned_at' => 'datetime',
        'is_addon' => 'boolean',
        'quantity' => 'decimal:3',
        'gross_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'material_cost' => 'decimal:2',
        'commission_base' => 'decimal:2',
        'commission_value' => 'decimal:2',
        'amount' => 'decimal:2',
        'manually_edited' => 'boolean',
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
        'commission_after_materials' => 'boolean',
        'appointment_new_customer' => 'boolean',
        'calculation_snapshot' => 'array',
        'audit_events' => 'array',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
