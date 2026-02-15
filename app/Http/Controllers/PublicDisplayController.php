<?php

namespace App\Http\Controllers;

use App\Models\Round;
use App\Models\Tournament;
use App\Support\MediaPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicDisplayController extends Controller
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
            $selectedTournament->setAttribute('logo_url', MediaPath::toUrl($selectedTournament->logo_path));
            $rounds = $selectedTournament->rounds()->orderBy('sort_order')->orderBy('id')->get(['id', 'name']);
            $selectedRound = $roundId
                ? $selectedTournament->rounds()->with(['participants.team', 'scores', 'result.entries'])->find($roundId)
                : $selectedTournament->rounds()->with(['participants.team', 'scores', 'result.entries'])->where('status', 'live')->first();

            if (!$selectedRound && $rounds->isNotEmpty()) {
                $selectedRound = Round::query()->with(['participants.team', 'scores', 'result.entries'])->find($rounds->first()->id);
            }

            if ($selectedRound) {
                $selectedRound->setRelation('participants', $selectedRound->participants->map(function ($participant) {
                    $resolvedName = $participant->team?->team_name
                        ?: $participant->display_name_snapshot
                        ?: "Team {$participant->slot}";
                    $iconPath = $participant->team?->icon_path ?: $participant->icon_snapshot_path;
                    $participant->setAttribute('display_name_snapshot', $resolvedName);
                    $participant->setAttribute('icon_url', MediaPath::toUrl($iconPath));

                    return $participant;
                }));
            }
        }

        return Inertia::render('Public/Display', [
            'tournaments' => $tournaments,
            'rounds' => $rounds,
            'selectedTournament' => $selectedTournament,
            'selectedRound' => $selectedRound,
        ]);
    }

    public function state(Round $round): JsonResponse
    {
        $round->load(['participants.team', 'scores', 'result.entries', 'tournament']);
        $scoreRows = $round->result?->entries?->isNotEmpty()
            ? $round->result->entries->map(fn ($entry) => [
                'slot' => $entry->slot,
                'score' => $entry->score,
            ])->values()
            : $round->scores->map(fn ($score) => [
                'slot' => $score->slot,
                'score' => $score->score,
            ])->values();

        return response()->json([
            'id' => $round->id,
            'name' => $round->name,
            'status' => $round->status,
            'phase' => $round->phase,
            'tournament' => [
                'id' => $round->tournament->id,
                'name' => $round->tournament->name,
            ],
            'participants' => $round->participants->map(fn ($participant) => [
                'slot' => $participant->slot,
                'name' => $participant->team?->team_name
                    ?: $participant->display_name_snapshot
                    ?: ("Team {$participant->slot}"),
                'icon_url' => MediaPath::toUrl($participant->team?->icon_path ?: $participant->icon_snapshot_path),
            ])->values(),
            'scores' => $scoreRows,
        ]);
    }
}
