<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tariff extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'supply_group_id', 'name', 'code', 'rate_per_kwh',
        'currency', 'max_power_limit_w', 'is_active',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'rate_per_kwh'      => 'decimal:4',
        'max_power_limit_w' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function supplyGroup(): BelongsTo
    {
        return $this->belongsTo(SupplyGroup::class);
    }

    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }
}

