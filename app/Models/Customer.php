<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Customer extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'account_number', 'name', 'phone', 'email',
        'address', 'city', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }
}

