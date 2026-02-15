<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Round;
use App\Models\RoundResult;
use App\Models\RoundScore;
use App\Models\RoundTemplate;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\AdvancementEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class RoundController extends Controller
{
    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        abort_unless($request->user()?->role === User::ROLE_SUPER_ADMIN, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'round_template_id' => ['nullable', Rule::exists('round_templates', 'id')],
            'group_id' => ['nullable', Rule::exists('groups', 'id')->where('tournament_id', $tournament->id)],
            'teams_per_round' => ['required', 'integer', 'min:2', 'max:8'],
            'default_score' => ['nullable', 'integer', 'min:0'],
            'scheduled_start_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'score_deltas' => ['nullable', 'array'],
            'score_deltas.*' => ['integer'],
        ]);

        $template = null;
        if (!empty($data['round_template_id'])) {
            $template = RoundTemplate::query()->where('tournament_id', $tournament->id)->find($data['round_template_id']);
            if (!$template) {
                return back()->with('error', 'Selected template does not belong to this tournament.');
            }
        }

        $defaultScore = array_key_exists('default_score', $data) && $data['default_score'] !== null
            ? (int) $data['default_score']
            : (int) ($template?->default_score ?? 100);

        $round = $tournament->rounds()->create([
            ...$data,
            'default_score' => $defaultScore,
            'status' => 'draft',
            'phase' => 'lightning',
            'sort_order' => $data['sort_order'] ?? 0,
            'score_deltas' => $data['score_deltas'] ?? [20, 10, -10],
        ]);

        for ($slot = 1; $slot <= (int) $data['teams_per_round']; $slot++) {
            $round->participants()->create(['slot' => $slot]);
            $round->scores()->create(['slot' => $slot, 'score' => $defaultScore]);
        }

        return back()->with('success', 'Round created.');
    }

    public function update(Request $request, Round $round): RedirectResponse
    {
        abort_unless($request->user()?->role === User::ROLE_SUPER_ADMIN, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'group_id' => ['nullable', Rule::exists('groups', 'id')->where('tournament_id', $round->tournament_id)],
            'status' => ['required', Rule::in(['draft', 'live', 'completed'])],
            'phase' => ['required', Rule::in(['lightning', 'buzzer'])],
            'teams_per_round' => ['required', 'integer', 'min:2', 'max:8'],
            'default_score' => ['nullable', 'integer', 'min:0'],
            'scheduled_start_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'score_deltas' => ['nullable', 'array'],
            'score_deltas.*' => ['integer'],
        ]);

        $newDefaultScore = $data['default_score'] ?? $round->default_score;
        $defaultScoreChanged = (int) $newDefaultScore !== (int) $round->default_score;

        if ($data['status'] === 'live') {
            Round::query()
                ->where('tournament_id', $round->tournament_id)
                ->where('id', '!=', $round->id)
                ->where('status', 'live')
                ->update(['status' => 'completed']);
        }

        $round->update([
            ...$data,
            'default_score' => $newDefaultScore,
            'sort_order' => $data['sort_order'] ?? $round->sort_order,
            'score_deltas' => $data['score_deltas'] ?? $round->score_deltas,
        ]);

        $maxSlot = (int) $round->participants()->max('slot');
        $targetSlots = (int) $data['teams_per_round'];

        if ($targetSlots > $maxSlot) {
            for ($slot = $maxSlot + 1; $slot <= $targetSlots; $slot++) {
                $round->participants()->create(['slot' => $slot]);
                $round->scores()->create(['slot' => $slot, 'score' => (int) $round->default_score]);
            }
        } elseif ($targetSlots < $maxSlot) {
            $round->participants()->where('slot', '>', $targetSlots)->delete();
            $round->scores()->where('slot', '>', $targetSlots)->delete();
        }

        if ($defaultScoreChanged && $round->status === 'draft') {
            $round->scores()->update(['score' => (int) $round->default_score]);
        }

        return back()->with('success', 'Round updated.');
    }

    public function updateParticipants(Request $request, Round $round): RedirectResponse
    {
        $data = $request->validate([
            'participants' => ['required', 'array'],
            'participants.*.slot' => ['required', 'integer', 'min:1'],
            'participants.*.team_id' => ['nullable', Rule::exists('teams', 'id')->whereNull('deleted_at')],
        ]);

        DB::transaction(function () use ($data, $round) {
            $lockedRound = Round::query()->whereKey($round->id)->lockForUpdate()->firstOrFail();

            foreach ($data['participants'] as $participantData) {
                $team = null;
                if (!empty($participantData['team_id'])) {
                    $team = Team::query()->find($participantData['team_id']);
                }

                $lockedRound->participants()->updateOrCreate(
                    ['slot' => (int) $participantData['slot']],
                    [
                        'team_id' => $team?->id,
                        'display_name_snapshot' => $team?->team_name,
                        'icon_snapshot_path' => $team?->icon_path,
                        'assignment_mode' => 'manual',
                        'assignment_source_type' => null,
                        'assignment_source_id' => null,
                        'assignment_source_rank' => null,
                        'assignment_reason' => null,
                        'assignment_updated_at' => now(),
                    ],
                );

                RoundScore::query()->firstOrCreate([
                    'round_id' => $lockedRound->id,
                    'slot' => (int) $participantData['slot'],
                ], [
                    'score' => (int) $lockedRound->default_score,
                ]);
            }

            if ($lockedRound->status === 'completed' && $lockedRound->result) {
                $lockedRound->result()->update(['is_stale' => true]);
            }
        });

        return back()->with('success', 'Round participants updated.');
    }

    public function updateGroup(Request $request, Round $round): RedirectResponse
    {
        abort_unless($request->user()?->role === User::ROLE_SUPER_ADMIN, 403);

        $data = $request->validate([
            'group_id' => ['nullable', Rule::exists('groups', 'id')->where('tournament_id', $round->tournament_id)],
        ]);

        $round->update([
            'group_id' => $data['group_id'] ?? null,
        ]);

        return back()->with('success', 'Round group updated.');
    }

    public function destroy(Request $request, Round $round): RedirectResponse
    {
        abort_unless($request->user()?->role === User::ROLE_SUPER_ADMIN, 403);

        $round->delete();

        return back()->with('success', 'Round deleted.');
    }

    public function overwriteResult(Request $request, Round $round): RedirectResponse
    {
        $data = $request->validate([
            'results' => ['required', 'array', 'min:1'],
            'results.*.slot' => ['required', 'integer', 'min:1'],
            'results.*.score' => ['required', 'integer', 'min:0'],
            'results.*.rank' => ['nullable', 'integer', 'min:1'],
            'force_apply' => ['nullable', 'boolean'],
        ]);

        try {
            $advancementSummary = DB::transaction(function () use ($request, $round, $data) {
                $lockedRound = Round::query()->whereKey($round->id)->lockForUpdate()->firstOrFail();

            if ($lockedRound->status !== 'completed') {
                throw new \RuntimeException('Only completed rounds can have results overwritten.');
            }

            $lockedRound->load(['participants.team']);
            $participantsBySlot = $lockedRound->participants->keyBy('slot');
            $entries = collect($data['results'])
                ->filter(fn ($entry) => $participantsBySlot->has((int) $entry['slot']))
                ->map(function ($entry) use ($participantsBySlot) {
                    $slot = (int) $entry['slot'];
                    $participant = $participantsBySlot[$slot];

                    return [
                        'slot' => $slot,
                        'team_id' => $participant->team_id,
                        'display_name_snapshot' => $participant->display_name_snapshot
                            ?? $participant->team?->team_name
                            ?? "Team {$slot}",
                        'score' => (int) $entry['score'],
                        'rank' => $entry['rank'] !== null ? (int) $entry['rank'] : null,
                    ];
                })
                ->values();

            if ($entries->isEmpty()) {
                throw new \RuntimeException('No valid result entries were provided.');
            }

            if ($entries->every(fn ($entry) => $entry['rank'] === null)) {
                $ranked = $entries->sort(function ($a, $b) {
                    if ($a['score'] === $b['score']) {
                        $aRankTie = $a['team_id'] ?? (1_000_000 + $a['slot']);
                        $bRankTie = $b['team_id'] ?? (1_000_000 + $b['slot']);

                        return $aRankTie <=> $bRankTie;
                    }

                    return $b['score'] <=> $a['score'];
                })->values();

                foreach ($ranked as $index => $entry) {
                    $entry['rank'] = $index + 1;
                    $entries[$entries->search(fn ($x) => $x['slot'] === $entry['slot'])] = $entry;
                }
            }

            $result = $lockedRound->result()->updateOrCreate(
                ['round_id' => $lockedRound->id],
                [
                    'finalized_by_user_id' => $request->user()?->id,
                    'finalized_at' => now(),
                    'is_overridden' => true,
                    'is_stale' => false,
                ],
            );

            $result->entries()->delete();
            $result->entries()->createMany($entries->sortBy('slot')->values()->all());

                return app(AdvancementEngine::class)->recomputeFromRound(
                    round: $lockedRound,
                    actor: $request->user(),
                    dueToOverride: true,
                    forceApply: (bool) ($data['force_apply'] ?? false),
                );
            });
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', sprintf(
            'Round result overwritten. Advancement: %d applied, %d blocked, %d skipped, %d eliminated, %d stale marked.',
            $advancementSummary['applied'],
            $advancementSummary['blocked_manual'],
            $advancementSummary['skipped'],
            $advancementSummary['eliminated'],
            $advancementSummary['stale_marked'],
        ));
    }
}
