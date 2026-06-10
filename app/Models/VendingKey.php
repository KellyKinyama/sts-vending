<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VendingKey extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'supply_group_id',
        'key_type',
        'tariff_index',
        'key_revision_number',
        'key_expiry_number',
        'algorithm',
        'encryption_algorithm',
        'base_date',
        'vudk_blob',
        'is_active',
        'rotated_at',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'rotated_at'  => 'datetime',
        'key_type'    => 'integer',
        'key_revision_number' => 'integer',
        'key_expiry_number'   => 'integer',
        'base_date'   => 'integer',
    ];

    protected $hidden = ['vudk_blob'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['vudk_blob'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** Encrypt-at-rest. Accepts hex string when writing; returns hex when reading. */
    protected function vudkBlob(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? bin2hex(Crypt::decryptString($value)) : null,
            set: fn ($value) => $value ? Crypt::encryptString(hex2bin($value)) : null,
        );
    }

    public function supplyGroup(): BelongsTo
    {
        return $this->belongsTo(SupplyGroup::class);
    }

    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(Token::class);
    }
}

