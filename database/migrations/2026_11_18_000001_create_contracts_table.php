<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 100)->unique();
            $table->date('contract_date');
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->string('work_name', 255);
            $table->string('representative_name', 200);
            $table->string('representative_position', 200);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('contract_value', 18, 2);
            $table->string('ppn_status', 30);
            $table->string('payment_method', 30);
            $table->decimal('monthly_payment_amount', 18, 2)->nullable();
            $table->decimal('full_financing_amount', 18, 2)->nullable();
            $table->string('status', 20)->default('Aktif');
            $table->dateTime('archived_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('vendor_id');
            $table->index('status');
            $table->index('end_date');
            $table->index('work_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
