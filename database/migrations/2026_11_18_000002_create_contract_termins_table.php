<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_termins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('termin_name', 120);
            $table->decimal('percentage', 7, 4);
            $table->decimal('nominal_amount', 18, 2);
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->index(['contract_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_termins');
    }
};
