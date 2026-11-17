<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('source_pr_version_id')->nullable()->constrained('purchase_request_versions')->restrictOnDelete();
            $table->string('work_name', 255);
            $table->date('rab_date');
            $table->string('location', 120)->default('Cilegon');
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['budget_id', 'version_number'], 'budget_versions_number_unique');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('budget_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('budget_versions');
    }
};
