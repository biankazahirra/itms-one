<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->restrictOnDelete();
            $table->foreignId('contract_termin_id')->nullable()->constrained('contract_termins')->restrictOnDelete();
            $table->foreignId('corrects_payment_record_id')->nullable()->constrained('payment_records')->restrictOnDelete();
            $table->string('status', 20)->default('Draft');
            $table->date('payment_date');
            $table->decimal('payment_amount', 18, 2);
            $table->string('payment_reference', 150);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('contract_id');
            $table->index('contract_termin_id');
            $table->index('corrects_payment_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_records');
    }
};
