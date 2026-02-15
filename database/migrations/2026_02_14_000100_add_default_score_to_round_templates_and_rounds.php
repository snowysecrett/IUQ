<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('round_templates', function (Blueprint $table) {
            $table->integer('default_score')->default(100)->after('teams_per_round');
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->integer('default_score')->default(100)->after('teams_per_round');
        });

        DB::table('round_templates')->whereNull('default_score')->update(['default_score' => 100]);
        DB::table('rounds')->whereNull('default_score')->update(['default_score' => 100]);
    }

    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn('default_score');
        });

        Schema::table('round_templates', function (Blueprint $table) {
            $table->dropColumn('default_score');
        });
    }
};
