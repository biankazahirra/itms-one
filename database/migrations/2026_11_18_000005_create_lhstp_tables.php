<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lhstps', function (Blueprint $table) {
            $table->id();
            $table->string('lhstp_number', 100)->unique();
            $table->foreignId('contract_id')->constrained('contracts')->restrictOnDelete();
            $table->string('status', 20)->default('Draft');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('contract_id');
            $table->index('finalized_at');
        });

        Schema::create('lhstp_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lhstp_id')->constrained('lhstps')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('po_number_snapshot', 100);
            $table->date('contract_date_snapshot');
            $table->string('vendor_name_snapshot', 200);
            $table->text('vendor_address_snapshot')->nullable();
            $table->string('work_name_snapshot', 255);
            $table->decimal('contract_value_snapshot', 18, 2);
            $table->date('contract_start_snapshot');
            $table->date('contract_end_snapshot');
            $table->string('representative_name_snapshot', 200);
            $table->string('representative_position_snapshot', 200);
            $table->string('payment_method_snapshot', 30);
            $table->string('ppn_status_snapshot', 30);
            $table->date('lhstp_date');
            $table->string('work_period', 150);
            $table->date('realization_start');
            $table->date('realization_end');
            $table->unsignedInteger('realization_days');
            $table->string('reference_document_type', 40);
            $table->string('reference_document_name', 200)->nullable();
            $table->string('reference_document_number', 150);
            $table->date('reference_document_date');
            $table->text('work_conclusion');
            $table->text('payment_terms');
            $table->decimal('payment_amount', 18, 2);
            $table->decimal('ppn_rate', 7, 4);
            $table->decimal('dpp_lain', 18, 2);
            $table->decimal('ppn_amount', 18, 2);
            $table->decimal('total_payment', 18, 2);
            $table->text('terbilang');
            $table->string('cost_center', 40);
            $table->date('opname_date');
            $table->date('signature_date');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['lhstp_id', 'version_number']);
            $table->index('finalized_at');
            $table->index('reference_document_number');
        });

        Schema::create('lhstp_version_termins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lhstp_version_id')->unique();
            $table->foreign('lhstp_version_id')->references('id')->on('lhstp_versions')->cascadeOnDelete();
            $table->foreignId('contract_termin_id')->constrained('contract_termins')->restrictOnDelete();
            $table->string('termin_name_snapshot', 120);
            $table->decimal('percentage_snapshot', 7, 4);
            $table->decimal('nominal_snapshot', 18, 2);
            $table->dateTime('created_at');

            $table->index('contract_termin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhstp_version_termins');
        Schema::dropIfExists('lhstp_versions');
        Schema::dropIfExists('lhstps');
    }
};
