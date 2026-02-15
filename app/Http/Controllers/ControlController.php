<?php

namespace App\Http\Controllers;

use App\Events\RoundUpdated;
use App\Models\Round;
use App\Models\RoundAction;
use App\Models\RoundResult;
use App\Services\AdvancementEngine;
use App\Models\Tournament;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ControlController extends Controller
{
    public function index(Request $request): Response
    {
        Tournament::syncScheduledStatuses();

        $tournamentId = $request->integer('tournament_id');
        $roundId = $request->integer('round_id');

        $tournaments = Tournament::query()->orderByDesc('year')->orderBy('name')->get();

        $selectedTournament = $tournamentId
            ? Tournament::query()->find($tournamentId)
            : Tournament::query()->live()->first() ?? $tournaments->first();

        $rounds = collect();
        $selectedRound = null;

        if ($selectedTournament) {
            $rounds = $selectedTournament->rounds()->orderBy('sort_order')->orderBy('id')->get();
            $selectedRound = $roundId
                ? $selectedTournament->rounds()->with(['participants', 'scores', 'result.entries'])->find($roundId)
                : $selectedTournament->rounds()->with(['participants', 'scores', 'result.entries'])->where('status', 'live')->first();

            if (!$selectedRound && $rounds->isNotEmpty()) {
                $selectedRound = $selectedTournament->rounds()->with(['participants', 'scores', 'result.entries'])->find($rounds->first()->id);
            }
        }

        return Inertia::render('Control/Index', [
            'tournaments' => $tournaments,
            'rounds' => $rounds,
            'selectedTournamentId' => $selectedTournament?->id,
            'selectedRound' => $selectedRound,
        ]);
    }

    public function action(Request $request, Round $round): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['start_competition', 'end_competition', 'to_buzzer', 'add_score', 'undo', 'clear'])],
            'slot' => ['nullable', 'integer', 'min:1'],
            'delta' => ['nullable', 'integer'],
            'results' => ['nullable', 'array'],
            'results.*.slot' => ['required_with:results', 'integer', 'min:1'],
            'results.*.score' => ['nullable', 'integer', 'min:0'],
            'results.*.rank' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $successMessage = DB::transaction(function () use ($request, $round, $data) {
                $lockedRound = Round::query()
                    ->whereKey($round->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            $requiresLive = in_array($data['action'], ['to_buzzer', 'add_score', 'undo'], true);
            if ($requiresLive && $lockedRound->status !== 'live') {
                throw new \RuntimeException('This action is only available when the round is live.');
            }

            if ($data['action'] === 'clear' && !in_array($lockedRound->status, ['live', 'completed'], true)) {
                throw new \RuntimeException('Clear is only available when the round is live or completed.');
            }

            if ($data['action'] === 'start_competition' && $lockedRound->status !== 'draft') {
                throw new \RuntimeException('Only draft rounds can be started.');
            }

            if ($data['action'] === 'end_competition' && $lockedRound->status !== 'live') {
                throw new \RuntimeException('Only live rounds can be ended.');
            }

            if ($data['action'] === 'to_buzzer' && $lockedRound->phase === 'buzzer') {
                throw new \RuntimeException('Round is already in buzzer phase.');
            }

            switch ($data['action']) {
                case 'start_competition':
                    Round::query()
                        ->where('tournament_id', $lockedRound->tournament_id)
                        ->where('id', '!=', $lockedRound->id)
                        ->where('status', 'live')
                        ->lockForUpdate()
                        ->update(['status' => 'completed']);
                    $lockedRound->update(['status' => 'live']);
                    $this->logAction($request, $lockedRound, 'start_competition', []);
                    return 'Round updated.';

                case 'end_competition':
                    $this->finalizeRoundResult($lockedRound, $request, $data['results'] ?? null, false);
                    $lockedRound->update(['status' => 'completed']);
                    $advancementSummary = app(AdvancementEngine::class)->recomputeFromRound(
                        round: $lockedRound,
                        actor: $request->user(),
                        dueToOverride: false,
                        forceApply: false,
                    );
                    $this->logAction($request, $lockedRound, 'end_competition', []);
                    return sprintf(
                        'Round updated. Advancement: %d applied, %d blocked, %d skipped, %d eliminated, %d stale marked.',
                        $advancementSummary['applied'],
                        $advancementSummary['blocked_manual'],
                        $advancementSummary['skipped'],
                        $advancementSummary['eliminated'],
                        $advancementSummary['stale_marked'],
                    );

                case 'to_buzzer':
                    $lockedRound->update(['phase' => 'buzzer']);
                    $this->logAction($request, $lockedRound, 'to_buzzer', []);
                    return 'Round updated.';

                case 'add_score':
                    $slot = (int) $data['slot'];
                    $delta = (int) $data['delta'];
                    $score = $lockedRound->scores()->where('slot', $slot)->lockForUpdate()->firstOrFail();
                    $before = (int) $score->score;
                    $after = max(0, $before + $delta);
                    $actualDelta = $after - $before;
                    $score->update(['score' => $after]);
                    $this->logAction($request, $lockedRound, 'add_score', [
                        'slot' => $slot,
                        'delta' => $actualDelta,
                        'phase' => $lockedRound->phase,
                    ]);
                    return 'Round updated.';

                case 'undo':
                    $last = $lockedRound->actions()->whereNull('rolled_back_at')->lockForUpdate()->first();
                    if ($last && $last->action_type === 'add_score') {
                        $slot = (int) ($last->payload['slot'] ?? 0);
                        $delta = (int) ($last->payload['delta'] ?? 0);
                        $score = $lockedRound->scores()->where('slot', $slot)->lockForUpdate()->first();
                        if ($score) {
                            $score->update(['score' => max(0, ((int) $score->score) - $delta)]);
                        }
                        $last->update(['rolled_back_at' => now()]);
                    }
                    return 'Round updated.';

                case 'clear':
                    $lockedRound->scores()->lockForUpdate()->update(['score' => (int) ($lockedRound->default_score ?? 100)]);
                    $lockedRound->actions()->whereNull('rolled_back_at')->lockForUpdate()->update(['rolled_back_at' => now()]);
                    $lockedRound->result()?->delete();
                    $lockedRound->update(['status' => 'draft', 'phase' => 'lightning']);
                    $this->logAction($request, $lockedRound, 'clear', []);
                    return 'Round updated.';
            }

                return 'Round updated.';
            });
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $round = Round::query()->with(['participants', 'scores', 'tournament'])->findOrFail($round->id);

        try {
            broadcast(new RoundUpdated($round))->toOthers();
        } catch (BroadcastException $exception) {
            Log::warning('Round updated but realtime broadcast failed.', [
                'round_id' => $round->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', $successMessage);
    }

    private function logAction(Request $request, Round $round, string $actionType, array $payload): RoundAction
    {
        return $round->actions()->create([
            'user_id' => $request->user()?->id,
            'action_type' => $actionType,
            'payload' => $payload,
        ]);
    }

    private function finalizeRoundResult(Round $round, Request $request, ?array $overrides, bool $isOverridden): RoundResult
    {
        $round->loadMissing(['participants.team', 'scores']);

        $scoreBySlot = $round->scores->keyBy('slot');
        $entries = $round->participants->map(function ($participant) use ($scoreBySlot) {
            return [
                'slot' => (int) $participant->slot,
                'team_id' => $participant->team_id,
                'display_name_snapshot' => $participant->display_name_snapshot
                    ?? $participant->team?->team_name
                    ?? "Team {$participant->slot}",
                'score' => (int) ($scoreBySlot[$participant->slot]->score ?? 0),
                'rank' => null,
            ];
        })->keyBy('slot');

        if (is_array($overrides)) {
            foreach ($overrides as $override) {
                $slot = (int) ($override['slot'] ?? 0);
                if (!$entries->has($slot)) {
                    continue;
                }

                $entry = $entries[$slot];
                if (array_key_exists('score', $override) && $override['score'] !== null) {
                    $entry['score'] = max(0, (int) $override['score']);
                }
                if (array_key_exists('rank', $override) && $override['rank'] !== null) {
                    $entry['rank'] = max(1, (int) $override['rank']);
                }
                $entries[$slot] = $entry;
            }
        }

        $hasManualRank = $entries->contains(fn ($entry) => $entry['rank'] !== null);
        if (!$hasManualRank) {
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
                $entries[$entry['slot']] = $entry;
            }
        }

        $result = $round->result()->updateOrCreate(
            ['round_id' => $round->id],
            [
                'finalized_by_user_id' => $request->user()?->id,
                'finalized_at' => now(),
                'is_overridden' => $isOverridden,
                'is_stale' => false,
            ],
        );

        $result->entries()->delete();
        $result->entries()->createMany($entries->sortBy('slot')->values()->all());

        return $result;
    }
}
