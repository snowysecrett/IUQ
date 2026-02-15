<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('round_templates')) {
            Schema::table('round_templates', function (Blueprint $table) {
                if (!Schema::hasColumn('round_templates', 'has_fever')) {
                    $table->boolean('has_fever')->default(false);
                }
                if (!Schema::hasColumn('round_templates', 'has_ultimate_fever')) {
                    $table->boolean('has_ultimate_fever')->default(false);
                }
                if (!Schema::hasColumn('round_templates', 'default_lightning_score_deltas')) {
                    $table->json('default_lightning_score_deltas')->nullable();
                }
                if (!Schema::hasColumn('round_templates', 'default_buzzer_normal_score_deltas')) {
                    $table->json('default_buzzer_normal_score_deltas')->nullable();
                }
                if (!Schema::hasColumn('round_templates', 'default_buzzer_fever_score_deltas')) {
                    $table->json('default_buzzer_fever_score_deltas')->nullable();
                }
                if (!Schema::hasColumn('round_templates', 'default_buzzer_ultimate_score_deltas')) {
                    $table->json('default_buzzer_ultimate_score_deltas')->nullable();
                }
            });

            DB::table('round_templates')->orderBy('id')->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $legacy = json_decode($row->default_score_deltas ?? '[]', true);
                    $base = is_array($legacy) && count($legacy) > 0 ? array_values($legacy) : [20, 10, -10];
                    $hasFever = (bool) ($row->has_fever ?? false);
                    $hasUltimate = (bool) ($row->has_ultimate_fever ?? false);
                    if ($hasUltimate && !$hasFever) {
                        $hasFever = true;
                    }

                    DB::table('round_templates')
                        ->where('id', $row->id)
                        ->update([
                            'has_fever' => $hasFever,
                            'has_ultimate_fever' => $hasUltimate,
                            'default_lightning_score_deltas' => $row->default_lightning_score_deltas
                                ? $row->default_lightning_score_deltas
                                : json_encode($base),
                            'default_buzzer_normal_score_deltas' => $row->default_buzzer_normal_score_deltas
                                ? $row->default_buzzer_normal_score_deltas
                                : json_encode($base),
                            'default_buzzer_fever_score_deltas' => $hasFever
                                ? ($row->default_buzzer_fever_score_deltas ?: json_encode($base))
                                : null,
                            'default_buzzer_ultimate_score_deltas' => $hasUltimate
                                ? ($row->default_buzzer_ultimate_score_deltas ?: json_encode($base))
                                : null,
                        ]);
                }
            });
        }

        if (Schema::hasTable('rounds')) {
            Schema::table('rounds', function (Blueprint $table) {
                if (!Schema::hasColumn('rounds', 'has_fever')) {
                    $table->boolean('has_fever')->default(false);
                }
                if (!Schema::hasColumn('rounds', 'has_ultimate_fever')) {
                    $table->boolean('has_ultimate_fever')->default(false);
                }
                if (!Schema::hasColumn('rounds', 'lightning_score_deltas')) {
                    $table->json('lightning_score_deltas')->nullable();
                }
                if (!Schema::hasColumn('rounds', 'buzzer_normal_score_deltas')) {
                    $table->json('buzzer_normal_score_deltas')->nullable();
                }
                if (!Schema::hasColumn('rounds', 'buzzer_fever_score_deltas')) {
                    $table->json('buzzer_fever_score_deltas')->nullable();
                }
                if (!Schema::hasColumn('rounds', 'buzzer_ultimate_score_deltas')) {
                    $table->json('buzzer_ultimate_score_deltas')->nullable();
                }
            });

            DB::table('rounds')->where('phase', 'buzzer')->update(['phase' => 'buzzer_normal']);

            DB::table('rounds')->orderBy('id')->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $legacy = json_decode($row->score_deltas ?? '[]', true);
                    $base = is_array($legacy) && count($legacy) > 0 ? array_values($legacy) : [20, 10, -10];
                    $hasFever = (bool) ($row->has_fever ?? false);
                    $hasUltimate = (bool) ($row->has_ultimate_fever ?? false);
                    if ($hasUltimate && !$hasFever) {
                        $hasFever = true;
                    }

                    DB::table('rounds')
                        ->where('id', $row->id)
                        ->update([
                            'has_fever' => $hasFever,
                            'has_ultimate_fever' => $hasUltimate,
                            'lightning_score_deltas' => $row->lightning_score_deltas
                                ? $row->lightning_score_deltas
                                : json_encode($base),
                            'buzzer_normal_score_deltas' => $row->buzzer_normal_score_deltas
                                ? $row->buzzer_normal_score_deltas
                                : json_encode($base),
                            'buzzer_fever_score_deltas' => $hasFever
                                ? ($row->buzzer_fever_score_deltas ?: json_encode($base))
                                : null,
                            'buzzer_ultimate_score_deltas' => $hasUltimate
                                ? ($row->buzzer_ultimate_score_deltas ?: json_encode($base))
                                : null,
                        ]);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rounds')) {
            Schema::table('rounds', function (Blueprint $table) {
                foreach ([
                    'has_fever',
                    'has_ultimate_fever',
                    'lightning_score_deltas',
                    'buzzer_normal_score_deltas',
                    'buzzer_fever_score_deltas',
                    'buzzer_ultimate_score_deltas',
                ] as $column) {
                    if (Schema::hasColumn('rounds', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('round_templates')) {
            Schema::table('round_templates', function (Blueprint $table) {
                foreach ([
                    'has_fever',
                    'has_ultimate_fever',
                    'default_lightning_score_deltas',
                    'default_buzzer_normal_score_deltas',
                    'default_buzzer_fever_score_deltas',
                    'default_buzzer_ultimate_score_deltas',
                ] as $column) {
                    if (Schema::hasColumn('round_templates', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};

