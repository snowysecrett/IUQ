<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoundTemplate;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoundTemplateController extends Controller
{
    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        abort_unless($request->user()?->role === User::ROLE_SUPER_ADMIN, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'teams_per_round' => ['required', 'integer', 'min:2', 'max:8'],
            'default_score' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'default_score_deltas' => ['nullable', 'array'],
            'default_score_deltas.*' => ['integer'],
        ]);

        $tournament->roundTemplates()->create([
            ...$data,
            'default_score' => $data['default_score'] ?? 100,
            'sort_order' => $data['sort_order'] ?? 0,
            'default_score_deltas' => $data['default_score_deltas'] ?? [20, 10, -10],
        ]);

        return back()->with('success', 'Round template created.');
    }

    public function update(Request $request, RoundTemplate $roundTemplate): RedirectResponse
    {
        abort_unless($request->user()?->role === User::ROLE_SUPER_ADMIN, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'teams_per_round' => ['required', 'integer', 'min:2', 'max:8'],
            'default_score' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'default_score_deltas' => ['nullable', 'array'],
            'default_score_deltas.*' => ['integer'],
        ]);

        $roundTemplate->update([
            ...$data,
            'default_score' => $data['default_score'] ?? 100,
            'sort_order' => $data['sort_order'] ?? 0,
            'default_score_deltas' => $data['default_score_deltas'] ?? [20, 10, -10],
        ]);

        return back()->with('success', 'Round template updated.');
    }

    public function destroy(Request $request, RoundTemplate $roundTemplate): RedirectResponse
    {
        abort_unless($request->user()?->role === User::ROLE_SUPER_ADMIN, 403);

        $roundTemplate->delete();

        return back()->with('success', 'Round template deleted.');
    }
}
