<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_summary_revisions', function (Blueprint $table) {
            $table->json('reference_snapshots')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('daily_summary_revisions', function (Blueprint $table) {
            $table->dropColumn('reference_snapshots');
        });
    }
};
