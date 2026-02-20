<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('visitor_presences')) {
            Schema::create('visitor_presences', function (Blueprint $table): void {
                $table->id();
                $table->string('session_id', 255)->unique();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->boolean('is_authenticated')->default(false);
                $table->timestamp('last_seen_at')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_presences');
    }
};

