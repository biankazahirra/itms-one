<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('version_id');
            $table->string('document_format', 10);
            $table->string('document_kind', 40);
            $table->string('original_filename', 255);
            $table->string('storage_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->char('checksum_sha256', 64);
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('generated_at');

            $table->index(['entity_type', 'entity_id', 'version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_documents');
    }
};
