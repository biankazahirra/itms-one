<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_stage_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->restrictOnDelete();
            $table->string('stage', 60);
            $table->text('note')->nullable();
            $table->dateTime('occurred_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at');

            $table->index(['purchase_request_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_stage_updates');
    }
};
