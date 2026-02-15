<?php

namespace App\Http\Controllers;

use App\Models\Round;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            $selectedTournament->setAttribute('logo_url', $this->resolveLogoUrl($selectedTournament->logo_path));
            $rounds = $selectedTournament->rounds()->orderBy('sort_order')->orderBy('id')->get(['id', 'name']);
            $selectedRound = $roundId
                ? $selectedTournament->rounds()->with(['participants.team', 'scores', 'result.entries'])->find($roundId)
                : $selectedTournament->rounds()->with(['participants.team', 'scores', 'result.entries'])->where('status', 'live')->first();

            if (!$selectedRound && $rounds->isNotEmpty()) {
                $selectedRound = Round::query()->with(['participants.team', 'scores', 'result.entries'])->find($rounds->first()->id);
            }

            if ($selectedRound) {
                $selectedRound->setRelation('participants', $selectedRound->participants->map(function ($participant) {
                    $iconPath = $participant->icon_snapshot_path ?: $participant->team?->icon_path;
                    $participant->setAttribute('icon_url', $this->resolveLogoUrl($iconPath));

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

    private function resolveLogoUrl(?string $logoPath): ?string
    {
        if (!$logoPath) {
            return null;
        }

        if (Str::startsWith($logoPath, ['http://', 'https://'])) {
            return $logoPath;
        }

        return '/storage/'.ltrim($logoPath, '/');
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
                'name' => $participant->display_name_snapshot ?? ("Team {$participant->slot}"),
                'icon_url' => $this->resolveLogoUrl($participant->icon_snapshot_path ?: $participant->team?->icon_path),
            ])->values(),
            'scores' => $scoreRows,
        ]);
    }
}
