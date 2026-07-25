<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeautyAnnotation extends Model
{
    protected $fillable = [
        'patient_id',
        'patient_media_id',
        'created_by',
        'x_percent',
        'y_percent',
        'area',
        'problem',
        'note',
        'status',
        'annotation_date',
    ];

    protected $casts = [
        'x_percent' => 'float',
        'y_percent' => 'float',
        'annotation_date' => 'date',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function media()
    {
        return $this->belongsTo(PatientMedia::class, 'patient_media_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
