<?php

namespace Tests\Feature;

use App\Models\AdvancementLog;
use App\Models\AdvancementRule;
use App\Models\Group;
use App\Models\Round;
use App\Models\RoundResult;
use App\Models\RoundTemplate;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IuqSafetyChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_round_creation_sets_success_flash_for_manual_and_template_flows(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'approved_at' => now(),
        ]);

        $tournament = Tournament::query()->create([
            'name' => 'IUQ Test',
            'year' => 2030,
            'status' => 'draft',
            'timezone' => 'UTC',
        ]);

        $template = RoundTemplate::query()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Default 3 Team',
            'teams_per_round' => 3,
            'default_score' => 120,
            'default_score_deltas' => [20, 10, -10],
            'default_lightning_score_deltas' => [20],
            'default_buzzer_normal_score_deltas' => [20, 10, -10],
            'default_buzzer_fever_score_deltas' => null,
            'default_buzzer_ultimate_score_deltas' => null,
            'has_fever' => false,
            'has_ultimate_fever' => false,
            'sort_order' => 0,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.rounds.store', $tournament), [
                'name' => 'Manual Round',
                'teams_per_round' => 3,
                'default_score' => 100,
                'has_fever' => false,
                'has_ultimate_fever' => false,
                'lightning_score_deltas' => [20],
                'buzzer_normal_score_deltas' => [20, 10, -10],
                'buzzer_fever_score_deltas' => null,
                'buzzer_ultimate_score_deltas' => null,
                'score_deltas' => [20, 10, -10],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Round created.');

        $this->actingAs($superAdmin)
            ->post(route('admin.rounds.store', $tournament), [
                'name' => 'Template Round',
                'round_template_id' => $template->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Round created.');

        $this->assertDatabaseCount('rounds', 2);
        $this->assertSame(3, Round::query()->where('name', 'Manual Round')->firstOrFail()->participants()->count());
        $this->assertSame(3, Round::query()->where('name', 'Template Round')->firstOrFail()->participants()->count());
    }

    public function test_clear_resets_scores_to_round_default_score_and_draft_state(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'approved_at' => now(),
        ]);

        $tournament = Tournament::query()->create([
            'name' => 'Test Tournament',
            'year' => 2030,
            'status' => 'draft',
            'timezone' => 'UTC',
        ]);

        $round = Round::query()->create([
            'tournament_id' => $tournament->id,
            'name' => 'R1',
            'teams_per_round' => 3,
            'default_score' => 150,
            'status' => 'live',
            'phase' => 'buzzer',
            'score_deltas' => [20, 10, -10],
            'sort_order' => 0,
        ]);

        for ($slot = 1; $slot <= 3; $slot++) {
            $round->participants()->create(['slot' => $slot]);
            $round->scores()->create(['slot' => $slot, 'score' => 37 + $slot]);
        }

        $this->actingAs($superAdmin)
            ->post(route('control.round.action', $round), [
                'action' => 'clear',
            ])
            ->assertRedirect();

        $round->refresh();
        $this->assertSame('draft', $round->status);
        $this->assertSame('lightning', $round->phase);
        $this->assertEquals([150, 150, 150], $round->scores()->orderBy('slot')->pluck('score')->all());
    }

    public function test_archived_team_cannot_be_added_or_assigned_again(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'approved_at' => now(),
        ]);

        $tournament = Tournament::query()->create([
            'name' => 'T1',
            'year' => 2030,
            'status' => 'draft',
            'timezone' => 'UTC',
        ]);

        $team = Team::query()->create([
            'university_name' => 'Oxford',
            'team_name' => 'Oxford 1',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.tournaments.teams.add', $tournament), ['team_id' => $team->id])
            ->assertRedirect();

        $this->actingAs($superAdmin)
            ->delete(route('admin.teams.destroy', $team))
            ->assertRedirect();

        $this->assertSoftDeleted('teams', ['id' => $team->id]);

        $this->actingAs($superAdmin)
            ->post(route('admin.tournaments.teams.add', $tournament), ['team_id' => $team->id])
            ->assertSessionHasErrors('team_id');

        $round = Round::query()->create([
            'tournament_id' => $tournament->id,
            'name' => 'R1',
            'teams_per_round' => 3,
            'default_score' => 100,
            'status' => 'draft',
            'phase' => 'lightning',
            'score_deltas' => [20, 10, -10],
            'sort_order' => 0,
        ]);

        for ($slot = 1; $slot <= 3; $slot++) {
            $round->participants()->create(['slot' => $slot]);
            $round->scores()->create(['slot' => $slot, 'score' => 100]);
        }

        $this->actingAs($superAdmin)
            ->post(route('admin.rounds.participants.update', $round), [
                'participants' => [
                    ['slot' => 1, 'team_id' => $team->id],
                    ['slot' => 2, 'team_id' => null],
                    ['slot' => 3, 'team_id' => null],
                ],
            ])
            ->assertSessionHasErrors('participants.0.team_id');
    }

    public function test_superadmin_can_clone_rules_only_tournament_with_clean_runtime_data(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'approved_at' => now(),
        ]);

        $source = Tournament::query()->create([
            'name' => 'IUQ 2030',
            'year' => 2030,
            'status' => 'live',
            'timezone' => 'Europe/London',
            'logo_path' => 'tournament-logos/logo.png',
        ]);

        $template = RoundTemplate::query()->create([
            'tournament_id' => $source->id,
            'name' => 'Template 3T',
            'code' => 'TMP3',
            'teams_per_round' => 3,
            'default_score' => 120,
            'default_score_deltas' => [20, 10, -10],
            'has_fever' => true,
            'has_ultimate_fever' => true,
            'default_lightning_score_deltas' => [15, 5, -5],
            'default_buzzer_normal_score_deltas' => [20, 10, -10],
            'default_buzzer_fever_score_deltas' => [30, 10, -10],
            'default_buzzer_ultimate_score_deltas' => [40, 20, -20],
            'sort_order' => 1,
        ]);

        $group = Group::query()->create([
            'tournament_id' => $source->id,
            'name' => 'Group A',
            'code' => 'GA',
            'sort_order' => 1,
        ]);

        $team = Team::query()->create([
            'university_name' => 'Cambridge',
            'team_name' => 'Cam 1',
        ]);

        $source->teams()->attach($team->id, [
            'display_name_snapshot' => 'Cam 1',
            'icon_snapshot_path' => null,
        ]);

        $round = Round::query()->create([
            'tournament_id' => $source->id,
            'round_template_id' => $template->id,
            'group_id' => $group->id,
            'name' => 'Prelim A1',
            'code' => 'PA1',
            'teams_per_round' => 3,
            'default_score' => 120,
            'status' => 'completed',
            'phase' => 'buzzer_fever',
            'score_deltas' => [20, 10, -10],
            'has_fever' => true,
            'has_ultimate_fever' => true,
            'lightning_score_deltas' => [15, 5, -5],
            'buzzer_normal_score_deltas' => [20, 10, -10],
            'buzzer_fever_score_deltas' => [30, 10, -10],
            'buzzer_ultimate_score_deltas' => [40, 20, -20],
            'sort_order' => 10,
            'scheduled_start_at' => now(),
        ]);

        for ($slot = 1; $slot <= 3; $slot++) {
            $round->participants()->create([
                'slot' => $slot,
                'team_id' => $slot === 1 ? $team->id : null,
                'display_name_snapshot' => $slot === 1 ? 'Cam 1' : null,
            ]);
            $round->scores()->create([
                'slot' => $slot,
                'score' => 120 + $slot,
            ]);
        }

        $result = RoundResult::query()->create([
            'round_id' => $round->id,
            'finalized_by_user_id' => $superAdmin->id,
            'finalized_at' => now(),
            'is_overridden' => false,
            'is_stale' => false,
        ]);

        $result->entries()->create([
            'slot' => 1,
            'team_id' => $team->id,
            'display_name_snapshot' => 'Cam 1',
            'score' => 200,
            'rank' => 1,
        ]);

        $rule = AdvancementRule::query()->create([
            'tournament_id' => $source->id,
            'source_type' => 'round',
            'source_round_id' => $round->id,
            'source_group_id' => null,
            'source_rank' => 1,
            'action_type' => 'advance',
            'target_round_id' => $round->id,
            'target_slot' => 1,
            'is_active' => true,
            'priority' => 0,
            'created_by_user_id' => $superAdmin->id,
        ]);

        AdvancementLog::query()->create([
            'tournament_id' => $source->id,
            'rule_id' => $rule->id,
            'user_id' => $superAdmin->id,
            'source_type' => 'round',
            'source_round_id' => $round->id,
            'status' => 'applied',
            'message' => 'seed log',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.tournaments.clone-rules'), [
                'source_tournament_id' => $source->id,
                'name' => 'IUQ 2031',
                'year' => 2031,
                'scheduled_start_at' => null,
            ])
            ->assertRedirect();

        $clone = Tournament::query()->where('name', 'IUQ 2031')->firstOrFail();

        $this->assertSame('draft', $clone->status);
        $this->assertSame('Europe/London', $clone->timezone);
        $this->assertSame('tournament-logos/logo.png', $clone->logo_path);

        $this->assertDatabaseCount('tournament_teams', 1);
        $this->assertSame(0, $clone->tournamentTeams()->count());

        $this->assertSame(1, $clone->roundTemplates()->count());
        $this->assertSame(1, $clone->groups()->count());
        $this->assertSame(1, $clone->rounds()->count());

        $clonedTemplate = $clone->roundTemplates()->firstOrFail();
        $this->assertTrue((bool) $clonedTemplate->has_fever);
        $this->assertTrue((bool) $clonedTemplate->has_ultimate_fever);
        $this->assertSame([15, 5, -5], $clonedTemplate->default_lightning_score_deltas);
        $this->assertSame([20, 10, -10], $clonedTemplate->default_buzzer_normal_score_deltas);
        $this->assertSame([30, 10, -10], $clonedTemplate->default_buzzer_fever_score_deltas);
        $this->assertSame([40, 20, -20], $clonedTemplate->default_buzzer_ultimate_score_deltas);

        $clonedRound = $clone->rounds()->firstOrFail();
        $this->assertSame('draft', $clonedRound->status);
        $this->assertSame('lightning', $clonedRound->phase);
        $this->assertNull($clonedRound->scheduled_start_at);
        $this->assertSame(120, $clonedRound->default_score);
        $this->assertTrue((bool) $clonedRound->has_fever);
        $this->assertTrue((bool) $clonedRound->has_ultimate_fever);
        $this->assertSame([15, 5, -5], $clonedRound->lightning_score_deltas);
        $this->assertSame([20, 10, -10], $clonedRound->buzzer_normal_score_deltas);
        $this->assertSame([30, 10, -10], $clonedRound->buzzer_fever_score_deltas);
        $this->assertSame([40, 20, -20], $clonedRound->buzzer_ultimate_score_deltas);

        $this->assertCount(3, $clonedRound->participants);
        $this->assertTrue($clonedRound->participants->every(fn ($p) => $p->team_id === null));
        $this->assertEquals([120, 120, 120], $clonedRound->scores()->orderBy('slot')->pluck('score')->all());

        $this->assertSame(0, RoundResult::query()->where('round_id', $clonedRound->id)->count());
        $this->assertSame(1, $clone->advancementRules()->count());
        $this->assertSame(0, $clone->advancementLogs()->count());

        $clonedRule = $clone->advancementRules()->firstOrFail();
        $this->assertNotNull($clonedRule->source_round_id);
        $this->assertSame($clonedRound->id, $clonedRule->source_round_id);
    }

    public function test_only_superadmin_can_create_advancement_rules(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'approved_at' => now(),
        ]);

        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'approved_at' => now(),
        ]);

        $tournament = Tournament::query()->create([
            'name' => 'T1',
            'year' => 2030,
            'status' => 'draft',
            'timezone' => 'UTC',
        ]);

        $round = Round::query()->create([
            'tournament_id' => $tournament->id,
            'name' => 'R1',
            'teams_per_round' => 3,
            'default_score' => 100,
            'status' => 'draft',
            'phase' => 'lightning',
            'score_deltas' => [20, 10, -10],
            'sort_order' => 0,
        ]);

        $group = Group::query()->create([
            'tournament_id' => $tournament->id,
            'name' => 'G1',
            'sort_order' => 0,
        ]);

        $payload = [
            'source_type' => 'round',
            'source_round_id' => $round->id,
            'source_rank' => 1,
            'action_type' => 'advance',
            'target_round_id' => $round->id,
            'target_slot' => 1,
            'priority' => 0,
            'is_active' => true,
            'source_group_id' => $group->id,
        ];

        $this->actingAs($admin)
            ->post(route('admin.advancement-rules.store', $tournament), $payload)
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->post(route('admin.advancement-rules.store', $tournament), $payload)
            ->assertRedirect();

        $this->assertSame(1, AdvancementRule::query()->where('tournament_id', $tournament->id)->count());
    }
}
