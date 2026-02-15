<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Round;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoundListController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $tournamentId = $request->integer('tournament_id');
        $year = $request->integer('year');
        $search = trim($request->string('search')->toString());

        $query = Round::query()
            ->with([
                'tournament:id,name,year,status',
                'participants.team:id,team_name',
                'scores:round_id,slot,score',
                'result.entries',
            ])
            ->orderByDesc('id');

        if (in_array($status, ['draft', 'live', 'completed'], true)) {
            $query->where('status', $status);
        }

        if ($tournamentId) {
            $query->where('tournament_id', $tournamentId);
        }

        if ($year) {
            $query->whereHas('tournament', fn ($q) => $q->where('year', $year));
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhereHas('tournament', fn ($tq) => $tq->where('name', 'like', "%{$search}%"));
            });
        }

        $rounds = $query->get()->map(function (Round $round) {
            $participants = collect();
            if ($round->result && $round->result->entries->isNotEmpty()) {
                $participants = $round->result->entries->sortBy('slot')->values()->map(function ($entry) {
                    return [
                        'slot' => $entry->slot,
                        'team_id' => $entry->team_id,
                        'name' => $entry->display_name_snapshot ?? "Team {$entry->slot}",
                        'score' => (int) $entry->score,
                        'rank' => $entry->rank,
                    ];
                });
            } else {
                $scoreBySlot = $round->scores->keyBy('slot');
                $participants = $round->participants
                    ->sortBy('slot')
                    ->values()
                    ->map(function ($participant) use ($scoreBySlot) {
                        $name = $participant->display_name_snapshot
                            ?? $participant->team?->team_name
                            ?? "Team {$participant->slot}";

                        return [
                            'slot' => $participant->slot,
                            'team_id' => $participant->team_id,
                            'name' => $name,
                            'score' => (int) ($scoreBySlot[$participant->slot]->score ?? 0),
                            'rank' => null,
                        ];
                    });
            }

            $winner = null;
            if ($round->status === 'completed') {
                foreach ($participants as $participant) {
                    $candidateTeamRank = $participant['team_id'] ?? (1_000_000 + $participant['slot']);

                    if (
                        $winner === null
                        || $participant['score'] > $winner['score']
                        || ($participant['score'] === $winner['score'] && $candidateTeamRank < $winner['team_rank'])
                    ) {
                        $winner = [
                            'slot' => $participant['slot'],
                            'team_id' => $participant['team_id'],
                            'name' => $participant['name'],
                            'score' => $participant['score'],
                            'team_rank' => $candidateTeamRank,
                        ];
                    }
                }
            }

            return [
                'id' => $round->id,
                'name' => $round->name,
                'code' => $round->code,
                'status' => $round->status,
                'phase' => $round->phase,
                'scheduled_start_at' => $round->scheduled_start_at,
                'tournament' => [
                    'id' => $round->tournament->id,
                    'name' => $round->tournament->name,
                    'year' => $round->tournament->year,
                ],
                'participants' => $participants,
                'winner' => $winner ? [
                    'slot' => $winner['slot'],
                    'team_id' => $winner['team_id'],
                    'name' => $winner['name'],
                    'score' => $winner['score'],
                ] : null,
                'has_finalized_result' => (bool) $round->result,
                'result_is_stale' => (bool) $round->result?->is_stale,
                'auto_updated_from_override' => $round->participants->contains(
                    fn ($participant) => $participant->assignment_mode === 'auto' && $participant->assignment_reason === 'override'
                ),
            ];
        });

        return Inertia::render('Admin/Rounds/Index', [
            'rounds' => $rounds,
            'filters' => [
                'status' => $status,
                'tournament_id' => $tournamentId ?: '',
                'year' => $year ?: '',
                'search' => $search,
            ],
            'tournaments' => Tournament::query()
                ->select(['id', 'name', 'year'])
                ->orderByDesc('year')
                ->orderBy('name')
                ->get(),
            'years' => Tournament::query()
                ->select('year')
                ->distinct()
                ->orderByDesc('year')
                ->pluck('year'),
        ]);
    }
}
