<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_version_id')->constrained()->cascadeOnDelete();
            $table->string('budget_name', 500);
            $table->text('description')->nullable();
            $table->decimal('quantity', 15, 3);
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->string('unit_name_snapshot', 80)->nullable();
            $table->decimal('unit_price', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['budget_version_id', 'sort_order'], 'budget_items_version_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_items');
    }
};
