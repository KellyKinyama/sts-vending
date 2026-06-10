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
        Schema::create('tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->string('token_no', 20)->index()->comment('20-digit STS token');
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vending_key_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('token_class')->comment('0,1,2');
            $table->unsignedTinyInteger('token_sub_class')->nullable();
            $table->string('token_kind')->comment('e.g. TransferElectricityCredit, ClearCredit, etc.');
            $table->decimal('amount_kwh', 12, 4)->nullable();
            $table->decimal('amount_currency', 14, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('issued_at')->index();
            $table->json('payload')->nullable()->comment('Full token-gen request as sent to engine');
            $table->json('engine_response')->nullable();
            $table->enum('status', ['issued', 'reversed', 'failed'])->default('issued');
            $table->string('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tokens');
    }
};
