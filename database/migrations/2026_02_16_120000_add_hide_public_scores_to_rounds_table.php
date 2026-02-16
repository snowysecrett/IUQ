<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rounds') && !Schema::hasColumn('rounds', 'hide_public_scores')) {
            Schema::table('rounds', function (Blueprint $table) {
                $table->boolean('hide_public_scores')->default(false)->after('phase');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rounds') && Schema::hasColumn('rounds', 'hide_public_scores')) {
            Schema::table('rounds', function (Blueprint $table) {
                $table->dropColumn('hide_public_scores');
            });
        }
    }
};
