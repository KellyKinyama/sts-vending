<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SupplyGroup extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['code', 'name', 'utility', 'region', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function vendingKeys(): HasMany
    {
        return $this->hasMany(VendingKey::class);
    }

    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    public function tariffs(): HasMany
    {
        return $this->hasMany(Tariff::class);
    }
}

