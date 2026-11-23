<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Expense extends Model
{
    protected $fillable = [
        'occurred_on',
        'category',
        'title',
        'amount',
        'type',
        'payment_status',
        'party',
        'invoice_number',
        'payment_method',
        'account_type',
        'campaign_id',
        'note',
        'attachment_path',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected $appends = [
        'attachment_url',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return '/api/expenses/'.$this->id.'/attachment';
    }

    public function deleteAttachmentFile(): void
    {
        if ($this->attachment_path && Storage::disk('public')->exists($this->attachment_path)) {
            Storage::disk('public')->delete($this->attachment_path);
        }
    }
}
