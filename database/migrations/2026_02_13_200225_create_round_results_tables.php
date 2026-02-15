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
        Schema::create('round_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->boolean('is_overridden')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('round_result_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_result_id')->constrained('round_results')->cascadeOnDelete();
            $table->unsignedInteger('slot');
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name_snapshot')->nullable();
            $table->integer('score')->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->timestamps();

            $table->unique(['round_result_id', 'slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('round_result_entries');
        Schema::dropIfExists('round_results');
    }
};
