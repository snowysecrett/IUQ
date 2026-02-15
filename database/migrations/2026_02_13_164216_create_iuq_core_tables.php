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
        if (!Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table) {
                $table->id();
                $table->string('university_name');
                $table->string('team_name');
                $table->string('short_name')->nullable();
                $table->string('icon_path')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tournaments')) {
            Schema::create('tournaments', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedInteger('year');
                $table->string('status')->default('draft');
                $table->timestamp('scheduled_start_at')->nullable();
                $table->string('timezone')->default('UTC');
                $table->string('logo_path')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tournament_teams')) {
            Schema::create('tournament_teams', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->string('display_name_snapshot');
                $table->string('icon_snapshot_path')->nullable();
                $table->timestamps();

                $table->unique(['tournament_id', 'team_id']);
            });
        }

        if (!Schema::hasTable('round_templates')) {
            Schema::create('round_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code')->nullable();
                $table->unsignedInteger('teams_per_round')->default(3);
                $table->json('default_score_deltas')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('rounds')) {
            Schema::create('rounds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->foreignId('round_template_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('code')->nullable();
                $table->unsignedInteger('teams_per_round')->default(3);
                $table->string('status')->default('draft');
                $table->string('phase')->default('lightning');
                $table->timestamp('scheduled_start_at')->nullable();
                $table->json('score_deltas')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('round_participants')) {
            Schema::create('round_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('round_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('slot');
                $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
                $table->string('display_name_snapshot')->nullable();
                $table->string('icon_snapshot_path')->nullable();
                $table->timestamps();

                $table->unique(['round_id', 'slot']);
            });
        }

        if (!Schema::hasTable('round_scores')) {
            Schema::create('round_scores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('round_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('slot');
                $table->integer('score')->default(0);
                $table->timestamps();

                $table->unique(['round_id', 'slot']);
            });
        }

        if (!Schema::hasTable('round_actions')) {
            Schema::create('round_actions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('round_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action_type');
                $table->json('payload');
                $table->timestamp('rolled_back_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('round_actions');
        Schema::dropIfExists('round_scores');
        Schema::dropIfExists('round_participants');
        Schema::dropIfExists('rounds');
        Schema::dropIfExists('round_templates');
        Schema::dropIfExists('tournament_teams');
        Schema::dropIfExists('tournaments');
        Schema::dropIfExists('teams');
    }
};
