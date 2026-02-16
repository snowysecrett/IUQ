<?php

namespace App\Http\Controllers\Admin;

use App\Events\RoundUpdated;
use App\Http\Controllers\Controller;
use App\Models\Round;
use App\Models\Team;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Teams/Index', [
            'teams' => Team::query()->orderBy('university_name')->orderBy('team_name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role === User::ROLE_SUPER_ADMIN, 403);

        $mediaDisk = config('media.disk', 'public');

        $data = $request->validate([
            'university_name' => ['required', 'string', 'max:255'],
            'team_name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:64'],
            'icon_path' => ['nullable', 'string', 'max:2048'],
            'icon_file' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('icon_file')) {
            $data['icon_path'] = $request->file('icon_file')->store('team-icons', $mediaDisk);
        }

        unset($data['icon_file']);
        Team::create($data);

        return back()->with('success', 'Team created.');
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        abort_unless($request->user()?->role === User::ROLE_SUPER_ADMIN, 403);

        $mediaDisk = config('media.disk', 'public');

        $data = $request->validate([
            'university_name' => ['required', 'string', 'max:255'],
            'team_name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:64'],
            'icon_path' => ['nullable', 'string', 'max:2048'],
            'icon_file' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('icon_file')) {
            $data['icon_path'] = $request->file('icon_file')->store('team-icons', $mediaDisk);
        }

        unset($data['icon_file']);
        $team->update($data);

        try {
            Round::query()
                ->whereHas('participants', fn ($query) => $query->where('team_id', $team->id))
                ->with(['participants.team', 'scores', 'result.entries', 'tournament'])
                ->get()
                ->each(fn (Round $round) => broadcast(new RoundUpdated($round)));
        } catch (BroadcastException $exception) {
            Log::warning('Team updated but realtime round refresh broadcast failed.', [
                'team_id' => $team->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', 'Team updated.');
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        abort_unless($request->user()?->role === User::ROLE_SUPER_ADMIN, 403);

        $team->delete();

        return back()->with('success', 'Team archived.');
    }
}
