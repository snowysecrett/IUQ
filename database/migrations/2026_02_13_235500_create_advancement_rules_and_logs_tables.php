<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('advancement_rules')) {
            Schema::create('advancement_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->string('source_type'); // round|group
                $table->foreignId('source_round_id')->nullable()->constrained('rounds')->cascadeOnDelete();
                $table->foreignId('source_group_id')->nullable()->constrained('groups')->cascadeOnDelete();
                $table->unsignedInteger('source_rank');
                $table->string('action_type')->default('advance'); // advance|eliminate
                $table->foreignId('target_round_id')->nullable()->constrained('rounds')->nullOnDelete();
                $table->unsignedInteger('target_slot')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('priority')->default(0);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tournament_id', 'source_type', 'source_round_id']);
                $table->index(['tournament_id', 'source_type', 'source_group_id']);
                $table->index(['target_round_id', 'target_slot']);
            });
        }

        if (!Schema::hasTable('advancement_logs')) {
            Schema::create('advancement_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->foreignId('rule_id')->nullable()->constrained('advancement_rules')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('source_type'); // round|group|system
                $table->foreignId('source_round_id')->nullable()->constrained('rounds')->nullOnDelete();
                $table->foreignId('source_group_id')->nullable()->constrained('groups')->nullOnDelete();
                $table->foreignId('target_round_id')->nullable()->constrained('rounds')->nullOnDelete();
                $table->unsignedInteger('target_slot')->nullable();
                $table->foreignId('team_id_before')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('team_id_after')->nullable()->constrained('teams')->nullOnDelete();
                $table->string('status'); // applied|blocked_manual|skipped|eliminated|stale_marked
                $table->string('message')->nullable();
                $table->json('context')->nullable();
                $table->timestamps();

                $table->index(['tournament_id', 'created_at']);
                $table->index(['target_round_id', 'target_slot']);
            });
        }

        Schema::table('round_participants', function (Blueprint $table) {
            $table->string('assignment_mode')->default('manual')->after('icon_snapshot_path'); // manual|auto
            $table->string('assignment_source_type')->nullable()->after('assignment_mode'); // round|group
            $table->unsignedBigInteger('assignment_source_id')->nullable()->after('assignment_source_type');
            $table->unsignedInteger('assignment_source_rank')->nullable()->after('assignment_source_id');
            $table->string('assignment_reason')->nullable()->after('assignment_source_rank'); // round_completion|override
            $table->timestamp('assignment_updated_at')->nullable()->after('assignment_reason');
            $table->index(['round_id', 'assignment_mode']);
        });

        Schema::table('round_results', function (Blueprint $table) {
            $table->boolean('is_stale')->default(false)->after('is_overridden');
        });
    }

    public function down(): void
    {
        Schema::table('round_results', function (Blueprint $table) {
            $table->dropColumn('is_stale');
        });

        Schema::table('round_participants', function (Blueprint $table) {
            $table->dropIndex(['round_id', 'assignment_mode']);
            $table->dropColumn([
                'assignment_mode',
                'assignment_source_type',
                'assignment_source_id',
                'assignment_source_rank',
                'assignment_reason',
                'assignment_updated_at',
            ]);
        });

        Schema::dropIfExists('advancement_logs');
        Schema::dropIfExists('advancement_rules');
    }
};
