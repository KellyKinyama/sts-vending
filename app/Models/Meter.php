<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Meter extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'pan', 'iin', 'iain', 'manufacturer_code', 'decoder_serial_number',
        'supply_group_id', 'vending_key_id', 'tariff_id', 'customer_id',
        'location', 'balance_kwh', 'installed_at', 'is_active',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'installed_at' => 'datetime',
        'balance_kwh'  => 'decimal:4',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function supplyGroup(): BelongsTo
    {
        return $this->belongsTo(SupplyGroup::class);
    }

    public function vendingKey(): BelongsTo
    {
        return $this->belongsTo(VendingKey::class);
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(Token::class);
    }
}

