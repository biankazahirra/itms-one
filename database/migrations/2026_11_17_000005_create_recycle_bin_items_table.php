<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recycle_bin_items', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('deleted_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('deleted_at');
            $table->dateTime('expires_at');
            $table->json('restore_metadata')->nullable();

            $table->unique(['entity_type', 'entity_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recycle_bin_items');
    }
};
