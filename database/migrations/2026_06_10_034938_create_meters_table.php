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
        Schema::create('meters', function (Blueprint $table) {
            $table->id();
            $table->string('pan', 18)->unique()->comment('Full 18-digit Meter PAN');
            $table->string('iin', 6)->index();
            $table->string('iain', 12)->index()->comment('11 or 13 digit IAIN');
            $table->string('manufacturer_code', 4)->nullable();
            $table->string('decoder_serial_number', 8)->nullable();
            $table->foreignId('supply_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vending_key_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tariff_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location')->nullable();
            $table->decimal('balance_kwh', 12, 4)->default(0);
            $table->timestamp('installed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meters');
    }
};
