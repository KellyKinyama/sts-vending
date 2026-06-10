<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Token extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'request_id', 'token_no', 'meter_id', 'vending_key_id', 'issued_by',
        'token_class', 'token_sub_class', 'token_kind',
        'amount_kwh', 'amount_currency', 'currency',
        'issued_at', 'payload', 'engine_response',
        'status', 'failure_reason',
    ];

    protected $casts = [
        'issued_at'       => 'datetime',
        'payload'         => 'array',
        'engine_response' => 'array',
        'amount_kwh'      => 'decimal:4',
        'amount_currency' => 'decimal:2',
        'token_class'     => 'integer',
        'token_sub_class' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['payload', 'engine_response'])
            ->dontSubmitEmptyLogs();
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function vendingKey(): BelongsTo
    {
        return $this->belongsTo(VendingKey::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'issued_by');
    }
}

