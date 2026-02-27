<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tournaments') && !Schema::hasColumn('tournaments', 'is_publicly_visible')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->boolean('is_publicly_visible')->default(false)->after('logo_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tournaments') && Schema::hasColumn('tournaments', 'is_publicly_visible')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->dropColumn('is_publicly_visible');
            });
        }
    }
};

