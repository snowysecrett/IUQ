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
        $section = $request->query('section', 'live');
        if (!in_array($section, ['upcoming', 'live', 'completed'], true)) {
            $section = 'live';
        }

        $tournamentsQuery = Tournament::query()->orderByDesc('year')->orderBy('name');
        if ($year) {
            $tournamentsQuery->where('year', $year);
        }

        $tournaments = $tournamentsQuery->get();

        $selectedTournament = $tournamentId
            ? Tournament::query()->with(['rounds.participants', 'rounds.scores', 'rounds.result.entries'])->find($tournamentId)
            : Tournament::query()->with(['rounds.participants', 'rounds.scores', 'rounds.result.entries'])->live()->first() ?? $tournaments->first();

        $selectedRounds = collect();
        $sectionRoundCounts = [
            'upcoming' => 0,
            'live' => 0,
            'completed' => 0,
        ];

        if ($selectedTournament) {
            $allRounds = $selectedTournament->rounds->map(function ($round) {
                if (!$round->hide_public_scores) {
                    return $round;
                }

                $round->setRelation('scores', $round->scores->map(function ($score) {
                    $score->setAttribute('score', null);

                    return $score;
                }));

                if ($round->result) {
                    $round->result->setRelation('entries', $round->result->entries->map(function ($entry) {
                        $entry->setAttribute('score', null);

                        return $entry;
                    }));
                }

                return $round;
            });

            $sortRounds = fn ($rounds) => $rounds
                ->sortBy(function ($round) {
                    $timestamp = $round->scheduled_start_at ? $round->scheduled_start_at->timestamp : PHP_INT_MAX;
                    return sprintf('%020d-%020d', $timestamp, (int) $round->id);
                })
                ->values();

            $upcomingRounds = $sortRounds(
                $allRounds
                    ->where('status', 'draft')
                    ->filter(fn ($round) => $round->participants->isNotEmpty() && $round->participants->every(fn ($p) => $p->team_id !== null))
            );
            $liveRounds = $sortRounds($allRounds->where('status', 'live'));
            $completedRounds = $sortRounds($allRounds->where('status', 'completed'));

            $selectedRounds = match ($section) {
                'upcoming' => $upcomingRounds,
                'completed' => $completedRounds,
                default => $liveRounds,
            };

            $sectionRoundCounts = [
                'upcoming' => $upcomingRounds->count(),
                'live' => $liveRounds->count(),
                'completed' => $completedRounds->count(),
            ];
        }

        return Inertia::render('Public/Timetable', [
            'years' => Tournament::query()->select('year')->distinct()->orderByDesc('year')->pluck('year'),
            'tournaments' => $tournaments,
            'selectedTournament' => $selectedTournament,
            'selectedSection' => $section,
            'selectedRounds' => $selectedRounds,
            'sectionRoundCounts' => $sectionRoundCounts,
        ]);
    }
}
