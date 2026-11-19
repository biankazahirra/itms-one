<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lhstp_versions', function (Blueprint $table) {
            $table->index('lhstp_date');
        });
    }

    public function down(): void
    {
        Schema::table('lhstp_versions', function (Blueprint $table) {
            $table->dropIndex(['lhstp_date']);
        });
    }
};
