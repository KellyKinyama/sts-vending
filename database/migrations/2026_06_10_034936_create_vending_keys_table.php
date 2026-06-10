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
        Schema::create('vending_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Human-readable label, e.g. VUDK_KE_2025');
            $table->foreignId('supply_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('key_type')->default(2)->comment('STS KeyType (1..7)');
            $table->string('tariff_index', 2)->default('01');
            $table->unsignedTinyInteger('key_revision_number')->default(1);
            $table->unsignedTinyInteger('key_expiry_number')->default(255);
            $table->enum('algorithm', ['DKGA02', 'DKGA04'])->default('DKGA02');
            $table->enum('encryption_algorithm', ['EA07', 'EA11'])->default('EA07')
                ->comment('EA07=STA (DES); EA11=MISTY1');
            $table->unsignedSmallInteger('base_date')->default(1993);
            $table->binary('vudk_blob')->comment('8 bytes for DKGA02, 20 bytes for DKGA04 (encrypted at rest)');
            $table->boolean('is_active')->default(true);
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vending_keys');
    }
};
