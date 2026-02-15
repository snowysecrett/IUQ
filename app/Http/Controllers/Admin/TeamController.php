<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return back()->with('success', 'Team updated.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $team->delete();

        return back()->with('success', 'Team archived.');
    }
}
