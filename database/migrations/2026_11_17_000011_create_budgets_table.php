<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('rab_number', 30)->unique();
            $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->nullOnDelete();
            $table->string('status', 20)->default('Draft');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('purchase_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
