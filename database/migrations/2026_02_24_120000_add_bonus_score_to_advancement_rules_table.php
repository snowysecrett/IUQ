<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('advancement_rules')) {
            return;
        }

        if (!Schema::hasColumn('advancement_rules', 'bonus_score')) {
            Schema::table('advancement_rules', function (Blueprint $table) {
                $table->integer('bonus_score')->default(0)->after('target_slot');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('advancement_rules') || !Schema::hasColumn('advancement_rules', 'bonus_score')) {
            return;
        }

        Schema::table('advancement_rules', function (Blueprint $table) {
            $table->dropColumn('bonus_score');
        });
    }
};

