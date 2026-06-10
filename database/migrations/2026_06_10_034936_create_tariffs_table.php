<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->decimal('rate_per_kwh', 12, 4)->comment('Currency / kWh');
            $table->string('currency', 3)->default('ZMW');
            $table->unsignedInteger('max_power_limit_w')->nullable()
                ->comment('Class 2/0 MaximumPowerLimit, watts');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tariffs');
    }
};
