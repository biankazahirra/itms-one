<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('recipient_department_id')->constrained('departments')->restrictOnDelete();
            $table->string('subject', 255);
            $table->date('pr_date');
            $table->string('category', 40)->nullable();
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['purchase_request_id', 'version_number'], 'pr_versions_number_unique');
        });

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('purchase_request_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('purchase_request_versions');
    }
};
