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
use App\Services\AdvancementEngine;
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

    public function test_last_seen_updates_on_authenticated_request_even_after_recent_guest_ping(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'approved_at' => now(),
            'last_seen_at' => now()->subDay(),
        ]);

        // Simulate recent guest heartbeat in same browser session.
        $this->withSession([
            'last_seen_ping_at_guest' => now()->timestamp,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();

        $admin->refresh();
        $this->assertNotNull($admin->last_seen_at);
        $this->assertTrue($admin->last_seen_at->greaterThan(now()->subMinutes(2)));
    }

    public function test_clear_resets_scores_to_default_or_bonus_baseline_and_resets_to_draft_lightning_state(): void
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
            'bonus_score' => 25,
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
        $this->assertSame(25, (int) $clonedRule->bonus_score);
    }

    public function test_clone_rules_can_optionally_clone_teams_round_start_times_and_non_dependent_participants(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'approved_at' => now(),
        ]);

        $source = Tournament::query()->create([
            'name' => 'IUQ 2032',
            'year' => 2032,
            'status' => 'draft',
            'timezone' => 'UTC',
        ]);

        $teamA = Team::query()->create(['university_name' => 'A', 'team_name' => 'Team A']);
        $teamB = Team::query()->create(['university_name' => 'B', 'team_name' => 'Team B']);
        $teamC = Team::query()->create(['university_name' => 'C', 'team_name' => 'Team C']);

        $source->tournamentTeams()->create([
            'team_id' => $teamA->id,
            'display_name_snapshot' => 'Team A Snap',
            'icon_snapshot_path' => 'teams/a.png',
        ]);
        $source->tournamentTeams()->create([
            'team_id' => $teamB->id,
            'display_name_snapshot' => 'Team B Snap',
            'icon_snapshot_path' => 'teams/b.png',
        ]);
        $source->tournamentTeams()->create([
            'team_id' => $teamC->id,
            'display_name_snapshot' => 'Team C Snap',
            'icon_snapshot_path' => 'teams/c.png',
        ]);

        $sourceRound = Round::query()->create([
            'tournament_id' => $source->id,
            'name' => 'Prelim A1',
            'teams_per_round' => 3,
            'default_score' => 100,
            'status' => 'draft',
            'phase' => 'lightning',
            'scheduled_start_at' => now()->addDay(),
            'score_deltas' => [20, 10, -10],
            'sort_order' => 0,
        ]);

        $sourceRound->participants()->create([
            'slot' => 1,
            'team_id' => $teamA->id,
            'display_name_snapshot' => 'Team A Snap',
            'icon_snapshot_path' => 'teams/a.png',
        ]);
        $sourceRound->participants()->create([
            'slot' => 2,
            'team_id' => $teamB->id,
            'display_name_snapshot' => 'Team B Snap',
            'icon_snapshot_path' => 'teams/b.png',
        ]);
        $sourceRound->participants()->create([
            'slot' => 3,
            'team_id' => $teamC->id,
            'display_name_snapshot' => 'Team C Snap',
            'icon_snapshot_path' => 'teams/c.png',
        ]);
        for ($slot = 1; $slot <= 3; $slot++) {
            $sourceRound->scores()->create(['slot' => $slot, 'score' => 100]);
        }

        AdvancementRule::query()->create([
            'tournament_id' => $source->id,
            'source_type' => 'round',
            'source_round_id' => $sourceRound->id,
            'source_group_id' => null,
            'source_rank' => 1,
            'action_type' => 'advance',
            'target_round_id' => $sourceRound->id,
            'target_slot' => 2,
            'bonus_score' => 10,
            'is_active' => true,
            'priority' => 0,
            'created_by_user_id' => $superAdmin->id,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.tournaments.clone-rules'), [
                'source_tournament_id' => $source->id,
                'name' => 'IUQ 2033',
                'year' => 2033,
                'scheduled_start_at' => null,
                'clone_tournament_teams' => true,
                'clone_round_start_times' => true,
                'clone_eligible_round_participants' => true,
            ])
            ->assertRedirect();

        $clone = Tournament::query()->where('name', 'IUQ 2033')->firstOrFail();
        $clonedRound = $clone->rounds()->firstOrFail();

        $this->assertSame(3, $clone->tournamentTeams()->count());
        $this->assertDatabaseHas('tournament_teams', [
            'tournament_id' => $clone->id,
            'team_id' => $teamA->id,
            'display_name_snapshot' => 'Team A Snap',
        ]);

        $this->assertNotNull($clonedRound->scheduled_start_at);
        $this->assertTrue($clonedRound->scheduled_start_at->equalTo($sourceRound->scheduled_start_at));

        $clonedParticipants = $clonedRound->participants()->orderBy('slot')->get();
        $this->assertSame($teamA->id, $clonedParticipants[0]->team_id);
        $this->assertNull($clonedParticipants[1]->team_id); // slot 2 is advancement-dependent
        $this->assertSame($teamC->id, $clonedParticipants[2]->team_id);
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

    public function test_advancement_bonus_sets_target_draft_score_to_default_plus_bonus_and_replaces_on_override(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'approved_at' => now(),
        ]);

        $tournament = Tournament::query()->create([
            'name' => 'Bonus Test',
            'year' => 2030,
            'status' => 'draft',
            'timezone' => 'UTC',
        ]);

        $teamA = Team::query()->create(['university_name' => 'A', 'team_name' => 'Team A']);
        $teamB = Team::query()->create(['university_name' => 'B', 'team_name' => 'Team B']);

        $sourceRound = Round::query()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Source',
            'teams_per_round' => 3,
            'default_score' => 100,
            'status' => 'completed',
            'phase' => 'buzzer_normal',
            'score_deltas' => [20, 10, -10],
            'sort_order' => 1,
        ]);

        $targetRound = Round::query()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Target',
            'teams_per_round' => 3,
            'default_score' => 120,
            'status' => 'draft',
            'phase' => 'lightning',
            'score_deltas' => [20, 10, -10],
            'sort_order' => 2,
        ]);

        for ($slot = 1; $slot <= 3; $slot++) {
            $sourceRound->participants()->create(['slot' => $slot]);
            $sourceRound->scores()->create(['slot' => $slot, 'score' => 100]);
            $targetRound->participants()->create(['slot' => $slot]);
            $targetRound->scores()->create(['slot' => $slot, 'score' => 999]); // should be reset by auto-advance bonus logic
        }

        $sourceResult = RoundResult::query()->create([
            'round_id' => $sourceRound->id,
            'finalized_by_user_id' => $superAdmin->id,
            'finalized_at' => now(),
            'is_overridden' => false,
            'is_stale' => false,
        ]);
        $sourceResult->entries()->createMany([
            ['slot' => 1, 'team_id' => $teamA->id, 'display_name_snapshot' => 'Team A', 'score' => 200, 'rank' => 1],
            ['slot' => 2, 'team_id' => $teamB->id, 'display_name_snapshot' => 'Team B', 'score' => 190, 'rank' => 2],
        ]);

        AdvancementRule::query()->create([
            'tournament_id' => $tournament->id,
            'source_type' => 'round',
            'source_round_id' => $sourceRound->id,
            'source_rank' => 1,
            'action_type' => 'advance',
            'target_round_id' => $targetRound->id,
            'target_slot' => 1,
            'bonus_score' => 30,
            'priority' => 0,
            'is_active' => true,
            'created_by_user_id' => $superAdmin->id,
        ]);

        app(AdvancementEngine::class)->recomputeFromRound($sourceRound, $superAdmin, false, false);

        $targetParticipant = $targetRound->participants()->where('slot', 1)->firstOrFail();
        $targetScore = $targetRound->scores()->where('slot', 1)->firstOrFail();

        $this->assertSame($teamA->id, $targetParticipant->team_id);
        $this->assertSame(150, (int) $targetScore->score); // 120 default + 30 bonus
        $this->assertDatabaseHas('advancement_logs', [
            'tournament_id' => $tournament->id,
            'target_round_id' => $targetRound->id,
            'target_slot' => 1,
            'status' => 'bonus_applied',
        ]);

        // Override source winner to Team B; target score should still be reset to default + bonus.
        $entry = $sourceResult->entries()->where('slot', 1)->firstOrFail();
        $entry->update([
            'team_id' => $teamB->id,
            'display_name_snapshot' => 'Team B',
            'score' => 210,
            'rank' => 1,
        ]);

        app(AdvancementEngine::class)->recomputeFromRound($sourceRound->fresh(), $superAdmin, true, false);

        $targetParticipant->refresh();
        $targetScore->refresh();
        $this->assertSame($teamB->id, $targetParticipant->team_id);
        $this->assertSame(150, (int) $targetScore->score);
    }

    public function test_advancement_bonus_on_live_or_completed_target_is_blocked_and_logged_once(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'approved_at' => now(),
        ]);

        $tournament = Tournament::query()->create([
            'name' => 'Bonus Blocked Test',
            'year' => 2030,
            'status' => 'draft',
            'timezone' => 'UTC',
        ]);

        $team = Team::query()->create(['university_name' => 'A', 'team_name' => 'Team A']);

        $sourceRound = Round::query()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Source',
            'teams_per_round' => 3,
            'default_score' => 100,
            'status' => 'completed',
            'phase' => 'buzzer_normal',
            'score_deltas' => [20, 10, -10],
            'sort_order' => 1,
        ]);

        $targetRound = Round::query()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Target',
            'teams_per_round' => 3,
            'default_score' => 100,
            'status' => 'live',
            'phase' => 'lightning',
            'score_deltas' => [20, 10, -10],
            'sort_order' => 2,
        ]);

        for ($slot = 1; $slot <= 3; $slot++) {
            $sourceRound->participants()->create(['slot' => $slot]);
            $sourceRound->scores()->create(['slot' => $slot, 'score' => 100]);
            $targetRound->participants()->create(['slot' => $slot]);
            $targetRound->scores()->create(['slot' => $slot, 'score' => 777]);
        }

        $result = RoundResult::query()->create([
            'round_id' => $sourceRound->id,
            'finalized_by_user_id' => $superAdmin->id,
            'finalized_at' => now(),
            'is_overridden' => false,
            'is_stale' => false,
        ]);
        $result->entries()->create([
            'slot' => 1,
            'team_id' => $team->id,
            'display_name_snapshot' => 'Team A',
            'score' => 200,
            'rank' => 1,
        ]);

        AdvancementRule::query()->create([
            'tournament_id' => $tournament->id,
            'source_type' => 'round',
            'source_round_id' => $sourceRound->id,
            'source_rank' => 1,
            'action_type' => 'advance',
            'target_round_id' => $targetRound->id,
            'target_slot' => 1,
            'bonus_score' => -10,
            'priority' => 0,
            'is_active' => true,
            'created_by_user_id' => $superAdmin->id,
        ]);

        app(AdvancementEngine::class)->recomputeFromRound($sourceRound, $superAdmin, false, false);
        app(AdvancementEngine::class)->recomputeFromRound($sourceRound->fresh(), $superAdmin, false, false);

        $this->assertSame(777, (int) $targetRound->scores()->where('slot', 1)->firstOrFail()->score);

        $blockedLogs = AdvancementLog::query()
            ->where('tournament_id', $tournament->id)
            ->where('target_round_id', $targetRound->id)
            ->where('target_slot', 1)
            ->where('status', 'blocked_round_state')
            ->get();

        $this->assertCount(1, $blockedLogs);
    }

    public function test_clear_uses_default_plus_bonus_for_auto_assigned_slots(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'approved_at' => now(),
        ]);

        $tournament = Tournament::query()->create([
            'name' => 'Clear Bonus Baseline',
            'year' => 2031,
            'status' => 'draft',
            'timezone' => 'UTC',
        ]);

        $team = Team::query()->create([
            'university_name' => 'Cambridge',
            'team_name' => 'Cam 1',
        ]);

        $sourceRound = Round::query()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Source',
            'teams_per_round' => 3,
            'default_score' => 100,
            'status' => 'completed',
            'phase' => 'buzzer_normal',
            'score_deltas' => [20, 10, -10],
            'sort_order' => 1,
        ]);

        $targetRound = Round::query()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Target',
            'teams_per_round' => 3,
            'default_score' => 120,
            'status' => 'live',
            'phase' => 'buzzer_normal',
            'score_deltas' => [20, 10, -10],
            'sort_order' => 2,
        ]);

        for ($slot = 1; $slot <= 3; $slot++) {
            $sourceRound->participants()->create(['slot' => $slot]);
            $sourceRound->scores()->create(['slot' => $slot, 'score' => 100]);
            $targetRound->participants()->create([
                'slot' => $slot,
                'team_id' => null,
                'assignment_mode' => 'manual',
            ]);
            $targetRound->scores()->create([
                'slot' => $slot,
                'score' => 999,
            ]);
        }

        $result = RoundResult::query()->create([
            'round_id' => $sourceRound->id,
            'finalized_by_user_id' => $superAdmin->id,
            'finalized_at' => now(),
            'is_overridden' => false,
            'is_stale' => false,
        ]);
        $result->entries()->create([
            'slot' => 1,
            'team_id' => $team->id,
            'display_name_snapshot' => 'Cam 1',
            'score' => 220,
            'rank' => 1,
        ]);

        AdvancementRule::query()->create([
            'tournament_id' => $tournament->id,
            'source_type' => 'round',
            'source_round_id' => $sourceRound->id,
            'source_rank' => 1,
            'action_type' => 'advance',
            'target_round_id' => $targetRound->id,
            'target_slot' => 1,
            'bonus_score' => 25,
            'priority' => 0,
            'is_active' => true,
            'created_by_user_id' => $superAdmin->id,
        ]);

        // Target is live, so bonus is blocked at advance-time, but participant auto-assignment still occurs.
        app(AdvancementEngine::class)->recomputeFromRound($sourceRound, $superAdmin, false, false);

        $this->actingAs($superAdmin)
            ->post(route('control.round.action', $targetRound), ['action' => 'clear'])
            ->assertRedirect();

        $targetRound->refresh();
        $this->assertSame('draft', $targetRound->status);
        $this->assertSame('lightning', $targetRound->phase);
        $this->assertEquals([145, 120, 120], $targetRound->scores()->orderBy('slot')->pluck('score')->all());
    }
}
