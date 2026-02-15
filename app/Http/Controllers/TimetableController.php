<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimetableController extends Controller
{
    public function index(Request $request): Response
    {
        Tournament::syncScheduledStatuses();

        $year = $request->integer('year');
        $tournamentId = $request->integer('tournament_id');

        $tournamentsQuery = Tournament::query()->orderByDesc('year')->orderBy('name');
        if ($year) {
            $tournamentsQuery->where('year', $year);
        }

        $tournaments = $tournamentsQuery->get();

        $selectedTournament = $tournamentId
            ? Tournament::query()->with(['rounds.participants', 'rounds.scores', 'rounds.result.entries'])->find($tournamentId)
            : Tournament::query()->with(['rounds.participants', 'rounds.scores', 'rounds.result.entries'])->live()->first() ?? $tournaments->first();

        return Inertia::render('Public/Timetable', [
            'years' => Tournament::query()->select('year')->distinct()->orderByDesc('year')->pluck('year'),
            'tournaments' => $tournaments,
            'selectedTournament' => $selectedTournament,
        ]);
    }
}
