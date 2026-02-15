<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TournamentController extends Controller
{
    public function index(): Response
    {
        Tournament::syncScheduledStatuses();

        return Inertia::render('Admin/Tournaments/Index', [
            'tournaments' => Tournament::query()
                ->withCount(['rounds', 'tournamentTeams'])
                ->orderByDesc('year')
                ->orderBy('name')
                ->get(),
            'teams' => Team::query()->orderBy('university_name')->orderBy('team_name')->get(),
        ]);
    }

    public function show(Tournament $tournament): Response
    {
        Tournament::syncScheduledStatuses();

        $tournament->load([
            'tournamentTeams.team',
            'roundTemplates' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'groups.rounds.participants.team',
            'groups.rounds.scores',
            'groups.rounds.result.entries',
            'advancementRules.sourceRound:id,name',
            'advancementRules.sourceGroup:id,name',
            'advancementRules.targetRound:id,name',
            'advancementLogs' => fn ($q) => $q->with([
                'rule:id,source_type,source_rank,action_type,target_round_id,target_slot',
                'sourceRound:id,name',
                'sourceGroup:id,name',
                'targetRound:id,name',
                'beforeTeam:id,team_name',
                'afterTeam:id,team_name',
                'user:id,name',
            ])->limit(200),
            'rounds.group',
            'rounds.participants',
            'rounds.scores',
            'rounds.result',
        ]);

        $groupSummaries = $tournament->groups->map(function ($group) {
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
                                'name' => $entry->display_name_snapshot ?? "Team {$entry->slot}",
                                'score' => 0,
                            ];
                        }

                        $totals[$entry->team_id]['score'] += (int) $entry->score;
                    }
                } else {
                    $scoreBySlot = $round->scores->keyBy('slot');

                    foreach ($round->participants as $participant) {
                        if (!$participant->team_id) {
                            continue;
                        }

                        $teamId = $participant->team_id;
                        $name = $participant->display_name_snapshot
                            ?? $participant->team?->team_name
                            ?? "Team {$participant->slot}";
                        $score = (int) ($scoreBySlot[$participant->slot]->score ?? 0);

                        if (!isset($totals[$teamId])) {
                            $totals[$teamId] = [
                                'team_id' => $teamId,
                                'name' => $name,
                                'score' => 0,
                            ];
                        }

                        $totals[$teamId]['score'] += $score;
                    }
                }
            }

            $standings = collect($totals)
                ->sort(function ($a, $b) {
                    if ($a['score'] === $b['score']) {
                        return $a['team_id'] <=> $b['team_id'];
                    }

                    return $b['score'] <=> $a['score'];
                })
                ->values();

            return [
                'id' => $group->id,
                'name' => $group->name,
                'code' => $group->code,
                'sort_order' => $group->sort_order,
                'round_count' => $group->rounds->count(),
                'completed_round_count' => $group->rounds->where('status', 'completed')->count(),
                'is_completed' => $group->rounds->isNotEmpty() && $group->rounds->every(fn ($round) => $round->status === 'completed'),
                'standings' => $standings,
            ];
        })->values();

        return Inertia::render('Admin/Tournaments/Show', [
            'tournament' => $tournament,
            'allTeams' => Team::query()->orderBy('university_name')->orderBy('team_name')->get(),
            'groupSummaries' => $groupSummaries,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'scheduled_start_at' => ['nullable', 'date'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'logo_path' => ['nullable', 'string', 'max:2048'],
            'logo_file' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('logo_file')) {
            $data['logo_path'] = $request->file('logo_file')->store('tournament-logos', 'public');
        }

        unset($data['logo_file']);

        $tournament = Tournament::create([
            ...$data,
            'status' => 'draft',
            'timezone' => $data['timezone'] ?? 'UTC',
        ]);

        return redirect()->route('admin.tournaments.show', $tournament)->with('success', 'Tournament created.');
    }

    public function cloneRules(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_tournament_id' => ['required', 'exists:tournaments,id'],
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'scheduled_start_at' => ['nullable', 'date'],
        ]);

        $source = Tournament::query()
            ->with([
                'roundTemplates' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
                'groups' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
                'rounds' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
                'advancementRules',
            ])
            ->findOrFail($data['source_tournament_id']);

        $clonedTournament = DB::transaction(function () use ($source, $data, $request) {
            $newTournament = Tournament::query()->create([
                'name' => $data['name'],
                'year' => (int) $data['year'],
                'status' => 'draft',
                'scheduled_start_at' => $data['scheduled_start_at'] ?? null,
                'timezone' => $source->timezone ?: 'UTC',
                'logo_path' => $source->logo_path,
            ]);

            $templateMap = [];
            foreach ($source->roundTemplates as $template) {
                $newTemplate = $newTournament->roundTemplates()->create([
                    'name' => $template->name,
                    'code' => $template->code,
                    'teams_per_round' => $template->teams_per_round,
                    'default_score' => $template->default_score ?? 100,
                    'default_score_deltas' => $template->default_score_deltas,
                    'sort_order' => $template->sort_order,
                ]);
                $templateMap[$template->id] = $newTemplate->id;
            }

            $groupMap = [];
            foreach ($source->groups as $group) {
                $newGroup = $newTournament->groups()->create([
                    'name' => $group->name,
                    'code' => $group->code,
                    'sort_order' => $group->sort_order,
                ]);
                $groupMap[$group->id] = $newGroup->id;
            }

            $roundMap = [];
            foreach ($source->rounds as $round) {
                $newRound = $newTournament->rounds()->create([
                    'round_template_id' => $round->round_template_id ? ($templateMap[$round->round_template_id] ?? null) : null,
                    'group_id' => $round->group_id ? ($groupMap[$round->group_id] ?? null) : null,
                    'name' => $round->name,
                    'code' => $round->code,
                    'teams_per_round' => $round->teams_per_round,
                    'default_score' => $round->default_score ?? 100,
                    'status' => 'draft',
                    'phase' => 'lightning',
                    'scheduled_start_at' => null,
                    'score_deltas' => $round->score_deltas,
                    'sort_order' => $round->sort_order,
                ]);
                $roundMap[$round->id] = $newRound->id;

                for ($slot = 1; $slot <= (int) $round->teams_per_round; $slot++) {
                    $newRound->participants()->create([
                        'slot' => $slot,
                        'team_id' => null,
                        'display_name_snapshot' => null,
                        'icon_snapshot_path' => null,
                    ]);
                    $newRound->scores()->create([
                        'slot' => $slot,
                        'score' => (int) ($newRound->default_score ?? 100),
                    ]);
                }
            }

            foreach ($source->advancementRules as $rule) {
                $newTournament->advancementRules()->create([
                    'source_type' => $rule->source_type,
                    'source_round_id' => $rule->source_round_id ? ($roundMap[$rule->source_round_id] ?? null) : null,
                    'source_group_id' => $rule->source_group_id ? ($groupMap[$rule->source_group_id] ?? null) : null,
                    'source_rank' => $rule->source_rank,
                    'action_type' => $rule->action_type,
                    'target_round_id' => $rule->target_round_id ? ($roundMap[$rule->target_round_id] ?? null) : null,
                    'target_slot' => $rule->target_slot,
                    'is_active' => $rule->is_active,
                    'priority' => $rule->priority,
                    'created_by_user_id' => $request->user()?->id,
                ]);
            }

            return $newTournament;
        });

        return redirect()
            ->route('admin.tournaments.show', $clonedTournament)
            ->with('success', 'Tournament cloned with rules only. Teams, scores and results are empty.');
    }

    public function update(Request $request, Tournament $tournament): RedirectResponse
    {
        abort_unless($request->user()?->role === User::ROLE_SUPER_ADMIN, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'status' => ['required', Rule::in(['draft', 'live', 'completed'])],
            'scheduled_start_at' => ['nullable', 'date'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'logo_path' => ['nullable', 'string', 'max:2048'],
            'logo_file' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($data['status'] === 'live') {
            Tournament::query()->where('id', '!=', $tournament->id)->where('status', 'live')->update(['status' => 'completed']);
        }

        if ($request->hasFile('logo_file')) {
            if ($tournament->logo_path && !Str::startsWith($tournament->logo_path, ['http://', 'https://'])) {
                Storage::disk('public')->delete($tournament->logo_path);
            }
            $data['logo_path'] = $request->file('logo_file')->store('tournament-logos', 'public');
        }

        unset($data['logo_file']);

        $tournament->update($data);

        return back()->with('success', 'Tournament updated.');
    }

    public function addTeam(Request $request, Tournament $tournament): RedirectResponse
    {
        abort_unless($request->user()?->role === User::ROLE_SUPER_ADMIN, 403);

        $data = $request->validate([
            'team_id' => ['required', Rule::exists('teams', 'id')->whereNull('deleted_at')],
        ]);

        if ($tournament->tournamentTeams()->count() >= 24) {
            return back()->with('error', 'Tournament already has 24 teams.');
        }

        $team = Team::query()->findOrFail($data['team_id']);

        $tournament->teams()->syncWithoutDetaching([
            $team->id => [
                'display_name_snapshot' => $team->team_name,
                'icon_snapshot_path' => $team->icon_path,
            ],
        ]);

        return back()->with('success', 'Team added to tournament.');
    }

    public function removeTeam(Request $request, Tournament $tournament, Team $team): RedirectResponse
    {
        abort_unless($request->user()?->role === User::ROLE_SUPER_ADMIN, 403);

        $tournament->teams()->detach($team->id);

        return back()->with('success', 'Team removed from tournament.');
    }

    public function admins(): Response
    {
        return Inertia::render('Admin/Users/Approvals', [
            'users' => User::query()->where('role', User::ROLE_ADMIN)->orderByDesc('created_at')->get(),
        ]);
    }
}
