<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('rounds', 'group_id')) {
            Schema::table('rounds', function (Blueprint $table) {
                $table->unsignedBigInteger('group_id')
                    ->nullable()
                    ->after('round_template_id');
            });
        }
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('rounds', 'group_id')) {
            Schema::table('rounds', function (Blueprint $table) {
                $table->dropColumn('group_id');
            });
        }
    }
};
