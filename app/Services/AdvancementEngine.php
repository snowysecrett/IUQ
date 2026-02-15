<?php

namespace App\Services;

use App\Models\AdvancementLog;
use App\Models\AdvancementRule;
use App\Models\Group;
use App\Models\Round;
use App\Models\RoundParticipant;
use App\Models\RoundScore;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;

class AdvancementEngine
{
    public function recomputeFromRound(Round $round, ?User $actor = null, bool $dueToOverride = false, bool $forceApply = false): array
    {
        $round->loadMissing('tournament');

        $summary = [
            'applied' => 0,
            'blocked_manual' => 0,
            'skipped' => 0,
            'eliminated' => 0,
            'stale_marked' => 0,
            'changed_round_ids' => [],
        ];

        $queue = collect([
            ['type' => 'round', 'id' => $round->id],
        ]);

        if ($round->group_id) {
            $queue->push(['type' => 'group', 'id' => $round->group_id]);
        }

        $processed = [];

        while ($queue->isNotEmpty()) {
            $item = $queue->shift();
            $key = $item['type'].':'.$item['id'];
            if (isset($processed[$key])) {
                continue;
            }
            $processed[$key] = true;

            if ($item['type'] === 'round') {
                $sourceRound = Round::query()
                    ->with(['result.entries'])
                    ->find($item['id']);

                if (!$sourceRound || $sourceRound->status !== 'completed' || !$sourceRound->result) {
                    continue;
                }

                $roundResult = $this->applyRulesForRound($sourceRound, $actor, $dueToOverride, $forceApply);
                $summary = $this->mergeSummary($summary, $roundResult);

                foreach ($roundResult['changed_round_ids'] as $changedRoundId) {
                    $queue->push(['type' => 'round', 'id' => $changedRoundId]);

                    $changedRound = Round::query()->with('result')->find($changedRoundId);
                    if ($changedRound?->group_id) {
                        $queue->push(['type' => 'group', 'id' => $changedRound->group_id]);
                    }

                    if ($changedRound && $changedRound->status === 'completed' && $changedRound->result && !$changedRound->result->is_stale) {
                        $changedRound->result->update(['is_stale' => true]);
                        $this->log(
                            tournamentId: $changedRound->tournament_id,
                            rule: null,
                            actor: $actor,
                            sourceType: 'system',
                            sourceRoundId: $sourceRound->id,
                            sourceGroupId: null,
                            targetRoundId: $changedRound->id,
                            targetSlot: null,
                            beforeTeamId: null,
                            afterTeamId: null,
                            status: 'stale_marked',
                            message: 'Completed round marked stale because upstream advancement changed participants.',
                            context: [
                                'due_to_override' => $dueToOverride,
                                'force_apply' => $forceApply,
                            ],
                        );
                        $summary['stale_marked']++;
                    }
                }
            }

            if ($item['type'] === 'group') {
                $group = Group::query()->with(['rounds.result.entries', 'rounds.participants.team', 'rounds.scores'])->find($item['id']);
                if (!$group) {
                    continue;
                }

                $groupResult = $this->applyRulesForGroup($group, $actor, $dueToOverride, $forceApply);
                $summary = $this->mergeSummary($summary, $groupResult);

                foreach ($groupResult['changed_round_ids'] as $changedRoundId) {
                    $queue->push(['type' => 'round', 'id' => $changedRoundId]);

                    $changedRound = Round::query()->with('result')->find($changedRoundId);
                    if ($changedRound?->group_id) {
                        $queue->push(['type' => 'group', 'id' => $changedRound->group_id]);
                    }

                    if ($changedRound && $changedRound->status === 'completed' && $changedRound->result && !$changedRound->result->is_stale) {
                        $changedRound->result->update(['is_stale' => true]);
                        $this->log(
                            tournamentId: $changedRound->tournament_id,
                            rule: null,
                            actor: $actor,
                            sourceType: 'system',
                            sourceRoundId: null,
                            sourceGroupId: $group->id,
                            targetRoundId: $changedRound->id,
                            targetSlot: null,
                            beforeTeamId: null,
                            afterTeamId: null,
                            status: 'stale_marked',
                            message: 'Completed round marked stale because upstream group advancement changed participants.',
                            context: [
                                'due_to_override' => $dueToOverride,
                                'force_apply' => $forceApply,
                            ],
                        );
                        $summary['stale_marked']++;
                    }
                }
            }
        }

        $summary['changed_round_ids'] = array_values(array_unique($summary['changed_round_ids']));

        return $summary;
    }

    private function applyRulesForRound(Round $round, ?User $actor, bool $dueToOverride, bool $forceApply): array
    {
        $rankings = $this->roundRankings($round);
        if ($rankings->isEmpty()) {
            return $this->emptySummary();
        }

        $rules = AdvancementRule::query()
            ->where('tournament_id', $round->tournament_id)
            ->where('source_type', 'round')
            ->where('source_round_id', $round->id)
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        return $this->applyRules(
            tournamentId: $round->tournament_id,
            rules: $rules,
            rankings: $rankings,
            actor: $actor,
            sourceType: 'round',
            sourceRoundId: $round->id,
            sourceGroupId: null,
            dueToOverride: $dueToOverride,
            forceApply: $forceApply,
        );
    }

    private function applyRulesForGroup(Group $group, ?User $actor, bool $dueToOverride, bool $forceApply): array
    {
        if ($group->rounds->isEmpty() || $group->rounds->contains(fn (Round $round) => $round->status !== 'completed')) {
            return $this->emptySummary();
        }

        $rankings = $this->groupRankings($group);
        if ($rankings->isEmpty()) {
            return $this->emptySummary();
        }

        $rules = AdvancementRule::query()
            ->where('tournament_id', $group->tournament_id)
            ->where('source_type', 'group')
            ->where('source_group_id', $group->id)
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        return $this->applyRules(
            tournamentId: $group->tournament_id,
            rules: $rules,
            rankings: $rankings,
            actor: $actor,
            sourceType: 'group',
            sourceRoundId: null,
            sourceGroupId: $group->id,
            dueToOverride: $dueToOverride,
            forceApply: $forceApply,
        );
    }

    private function applyRules(
        int $tournamentId,
        Collection $rules,
        Collection $rankings,
        ?User $actor,
        string $sourceType,
        ?int $sourceRoundId,
        ?int $sourceGroupId,
        bool $dueToOverride,
        bool $forceApply,
    ): array {
        $summary = $this->emptySummary();
        $teamCache = [];

        foreach ($rules as $rule) {
            $ranked = $rankings->get((int) $rule->source_rank);

            if ($rule->action_type === 'eliminate') {
                $this->log(
                    tournamentId: $tournamentId,
                    rule: $rule,
                    actor: $actor,
                    sourceType: $sourceType,
                    sourceRoundId: $sourceRoundId,
                    sourceGroupId: $sourceGroupId,
                    targetRoundId: null,
                    targetSlot: null,
                    beforeTeamId: null,
                    afterTeamId: $ranked['team_id'] ?? null,
                    status: 'eliminated',
                    message: $ranked
                        ? "Rank {$rule->source_rank} team eliminated by rule."
                        : "No team at rank {$rule->source_rank}; eliminate rule had no effect.",
                    context: [
                        'due_to_override' => $dueToOverride,
                        'force_apply' => $forceApply,
                    ],
                );
                $summary['eliminated']++;
                continue;
            }

            if (!$ranked || !$ranked['team_id']) {
                $this->log(
                    tournamentId: $tournamentId,
                    rule: $rule,
                    actor: $actor,
                    sourceType: $sourceType,
                    sourceRoundId: $sourceRoundId,
                    sourceGroupId: $sourceGroupId,
                    targetRoundId: $rule->target_round_id,
                    targetSlot: $rule->target_slot,
                    beforeTeamId: null,
                    afterTeamId: null,
                    status: 'skipped',
                    message: "No eligible team at rank {$rule->source_rank}.",
                    context: [
                        'due_to_override' => $dueToOverride,
                        'force_apply' => $forceApply,
                    ],
                );
                $summary['skipped']++;
                continue;
            }

            if (!$rule->target_round_id || !$rule->target_slot) {
                $this->log(
                    tournamentId: $tournamentId,
                    rule: $rule,
                    actor: $actor,
                    sourceType: $sourceType,
                    sourceRoundId: $sourceRoundId,
                    sourceGroupId: $sourceGroupId,
                    targetRoundId: null,
                    targetSlot: null,
                    beforeTeamId: null,
                    afterTeamId: $ranked['team_id'],
                    status: 'skipped',
                    message: 'Advance rule missing target round or slot.',
                    context: [
                        'due_to_override' => $dueToOverride,
                        'force_apply' => $forceApply,
                    ],
                );
                $summary['skipped']++;
                continue;
            }

            $targetRound = Round::query()->where('tournament_id', $tournamentId)->find($rule->target_round_id);
            if (!$targetRound) {
                $this->log(
                    tournamentId: $tournamentId,
                    rule: $rule,
                    actor: $actor,
                    sourceType: $sourceType,
                    sourceRoundId: $sourceRoundId,
                    sourceGroupId: $sourceGroupId,
                    targetRoundId: $rule->target_round_id,
                    targetSlot: $rule->target_slot,
                    beforeTeamId: null,
                    afterTeamId: $ranked['team_id'],
                    status: 'skipped',
                    message: 'Target round not found in tournament.',
                    context: [
                        'due_to_override' => $dueToOverride,
                        'force_apply' => $forceApply,
                    ],
                );
                $summary['skipped']++;
                continue;
            }

            if ((int) $rule->target_slot > (int) $targetRound->teams_per_round) {
                $this->log(
                    tournamentId: $tournamentId,
                    rule: $rule,
                    actor: $actor,
                    sourceType: $sourceType,
                    sourceRoundId: $sourceRoundId,
                    sourceGroupId: $sourceGroupId,
                    targetRoundId: $rule->target_round_id,
                    targetSlot: $rule->target_slot,
                    beforeTeamId: null,
                    afterTeamId: $ranked['team_id'],
                    status: 'skipped',
                    message: 'Target slot exceeds target round team slots.',
                    context: [
                        'due_to_override' => $dueToOverride,
                        'force_apply' => $forceApply,
                    ],
                );
                $summary['skipped']++;
                continue;
            }

            $participant = RoundParticipant::query()
                ->where('round_id', $targetRound->id)
                ->where('slot', (int) $rule->target_slot)
                ->lockForUpdate()
                ->first();

            if (!$participant) {
                try {
                    RoundParticipant::query()->create([
                        'round_id' => $targetRound->id,
                        'slot' => (int) $rule->target_slot,
                        'assignment_mode' => 'manual',
                    ]);
                } catch (QueryException) {
                    // Another transaction created this slot first.
                }

                $participant = RoundParticipant::query()
                    ->where('round_id', $targetRound->id)
                    ->where('slot', (int) $rule->target_slot)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $beforeTeamId = $participant->team_id;
            $isManualLocked = $participant->assignment_mode === 'manual' && $participant->team_id !== null;
            if ($isManualLocked && !$forceApply) {
                $this->log(
                    tournamentId: $tournamentId,
                    rule: $rule,
                    actor: $actor,
                    sourceType: $sourceType,
                    sourceRoundId: $sourceRoundId,
                    sourceGroupId: $sourceGroupId,
                    targetRoundId: $targetRound->id,
                    targetSlot: (int) $rule->target_slot,
                    beforeTeamId: $beforeTeamId,
                    afterTeamId: $ranked['team_id'],
                    status: 'blocked_manual',
                    message: 'Target slot is manually locked.',
                    context: [
                        'due_to_override' => $dueToOverride,
                        'force_apply' => $forceApply,
                    ],
                );
                $summary['blocked_manual']++;
                continue;
            }

            if (
                !$forceApply
                && $sourceType === 'group'
                && $participant->assignment_mode === 'auto'
                && $participant->assignment_source_type === 'round'
                && $participant->team_id !== null
            ) {
                $this->log(
                    tournamentId: $tournamentId,
                    rule: $rule,
                    actor: $actor,
                    sourceType: $sourceType,
                    sourceRoundId: $sourceRoundId,
                    sourceGroupId: $sourceGroupId,
                    targetRoundId: $targetRound->id,
                    targetSlot: (int) $rule->target_slot,
                    beforeTeamId: $participant->team_id,
                    afterTeamId: $ranked['team_id'],
                    status: 'skipped',
                    message: 'Skipped because slot already auto-assigned by round-based rule (higher priority).',
                    context: [
                        'due_to_override' => $dueToOverride,
                        'force_apply' => $forceApply,
                    ],
                );
                $summary['skipped']++;
                continue;
            }

            if (!isset($teamCache[$ranked['team_id']])) {
                $teamCache[$ranked['team_id']] = Team::query()->find($ranked['team_id']);
            }
            $team = $teamCache[$ranked['team_id']];
            if (!$team) {
                $summary['skipped']++;
                continue;
            }

            $participant->update([
                'team_id' => $team->id,
                'display_name_snapshot' => $team->team_name,
                'icon_snapshot_path' => $team->icon_path,
                'assignment_mode' => 'auto',
                'assignment_source_type' => $sourceType,
                'assignment_source_id' => $sourceType === 'round' ? $sourceRoundId : $sourceGroupId,
                'assignment_source_rank' => (int) $rule->source_rank,
                'assignment_reason' => $dueToOverride ? 'override' : 'round_completion',
                'assignment_updated_at' => now(),
            ]);

            $score = RoundScore::query()
                ->where('round_id', $targetRound->id)
                ->where('slot', (int) $rule->target_slot)
                ->lockForUpdate()
                ->first();

            if (!$score) {
                try {
                    RoundScore::query()->create([
                        'round_id' => $targetRound->id,
                        'slot' => (int) $rule->target_slot,
                        'score' => (int) ($targetRound->default_score ?? 100),
                    ]);
                } catch (QueryException) {
                    // Another transaction created this score row first.
                }
            }

            $this->log(
                tournamentId: $tournamentId,
                rule: $rule,
                actor: $actor,
                sourceType: $sourceType,
                sourceRoundId: $sourceRoundId,
                sourceGroupId: $sourceGroupId,
                targetRoundId: $targetRound->id,
                targetSlot: (int) $rule->target_slot,
                beforeTeamId: $beforeTeamId,
                afterTeamId: $team->id,
                status: 'applied',
                message: 'Team advanced into target slot.',
                context: [
                    'due_to_override' => $dueToOverride,
                    'force_apply' => $forceApply,
                    'source_rank' => (int) $rule->source_rank,
                    'target_round_name' => $targetRound->name,
                ],
            );

            $summary['applied']++;
            if ($beforeTeamId !== (int) $team->id) {
                $summary['changed_round_ids'][] = $targetRound->id;
            }
        }

        $summary['changed_round_ids'] = array_values(array_unique($summary['changed_round_ids']));

        return $summary;
    }

    private function roundRankings(Round $round): Collection
    {
        $round->loadMissing(['result.entries']);
        if (!$round->result || $round->result->entries->isEmpty()) {
            return collect();
        }

        $entries = $round->result->entries;
        $hasRank = $entries->contains(fn ($entry) => $entry->rank !== null);

        if ($hasRank) {
            $ordered = $entries->sortBy(fn ($entry) => [$entry->rank ?? 999999, $entry->slot])->values();
        } else {
            $ordered = $entries->sort(function ($a, $b) {
                if ((int) $a->score === (int) $b->score) {
                    $aTeamRank = $a->team_id ?? (1_000_000 + $a->slot);
                    $bTeamRank = $b->team_id ?? (1_000_000 + $b->slot);

                    return $aTeamRank <=> $bTeamRank;
                }

                return (int) $b->score <=> (int) $a->score;
            })->values();
        }

        $rankings = collect();
        foreach ($ordered as $index => $entry) {
            $rankings->put($index + 1, [
                'team_id' => $entry->team_id,
                'slot' => $entry->slot,
                'score' => (int) $entry->score,
            ]);
        }

        return $rankings;
    }

    private function groupRankings(Group $group): Collection
    {
        $totals = [];

        foreach ($group->rounds as $round) {
            if ($round->result && $round->result->entries->isNotEmpty()) {
                foreach ($round->result->entries as $entry) {
                    if (!$entry->team_id) {
                        continue;
                    }

                    if (!isset($totals[$entry->team_id])) {
                        $totals[$entry->team_id] = [
                            'team_id' => $entry->team_id,
                            'score' => 0,
                        ];
                    }

                    $totals[$entry->team_id]['score'] += (int) $entry->score;
                }

                continue;
            }

            $scoreBySlot = $round->scores->keyBy('slot');
            foreach ($round->participants as $participant) {
                if (!$participant->team_id) {
                    continue;
                }

                if (!isset($totals[$participant->team_id])) {
                    $totals[$participant->team_id] = [
                        'team_id' => $participant->team_id,
                        'score' => 0,
                    ];
                }

                $totals[$participant->team_id]['score'] += (int) ($scoreBySlot[$participant->slot]->score ?? 0);
            }
        }

        $ordered = collect($totals)->sort(function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return $a['team_id'] <=> $b['team_id'];
            }

            return $b['score'] <=> $a['score'];
        })->values();

        $rankings = collect();
        foreach ($ordered as $index => $entry) {
            $rankings->put($index + 1, $entry);
        }

        return $rankings;
    }

    private function emptySummary(): array
    {
        return [
            'applied' => 0,
            'blocked_manual' => 0,
            'skipped' => 0,
            'eliminated' => 0,
            'stale_marked' => 0,
            'changed_round_ids' => [],
        ];
    }

    private function mergeSummary(array $base, array $delta): array
    {
        foreach (['applied', 'blocked_manual', 'skipped', 'eliminated', 'stale_marked'] as $key) {
            $base[$key] += $delta[$key] ?? 0;
        }

        $base['changed_round_ids'] = array_values(array_unique(array_merge(
            $base['changed_round_ids'] ?? [],
            $delta['changed_round_ids'] ?? [],
        )));

        return $base;
    }

    private function log(
        int $tournamentId,
        ?AdvancementRule $rule,
        ?User $actor,
        string $sourceType,
        ?int $sourceRoundId,
        ?int $sourceGroupId,
        ?int $targetRoundId,
        ?int $targetSlot,
        ?int $beforeTeamId,
        ?int $afterTeamId,
        string $status,
        ?string $message,
        array $context = [],
    ): void {
        AdvancementLog::query()->create([
            'tournament_id' => $tournamentId,
            'rule_id' => $rule?->id,
            'user_id' => $actor?->id,
            'source_type' => $sourceType,
            'source_round_id' => $sourceRoundId,
            'source_group_id' => $sourceGroupId,
            'target_round_id' => $targetRoundId,
            'target_slot' => $targetSlot,
            'team_id_before' => $beforeTeamId,
            'team_id_after' => $afterTeamId,
            'status' => $status,
            'message' => $message,
            'context' => $context,
        ]);
    }
}
