<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvancementRule;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdvancementRuleController extends Controller
{
    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        abort_unless($request->user()?->role === User::ROLE_SUPER_ADMIN, 403);

        $data = $request->validate([
            'source_type' => ['required', Rule::in(['round', 'group'])],
            'source_round_id' => ['nullable', Rule::exists('rounds', 'id')->where('tournament_id', $tournament->id)],
            'source_group_id' => ['nullable', Rule::exists('groups', 'id')->where('tournament_id', $tournament->id)],
            'source_rank' => ['required', 'integer', 'min:1'],
            'action_type' => ['required', Rule::in(['advance', 'eliminate'])],
            'target_round_id' => ['nullable', Rule::exists('rounds', 'id')->where('tournament_id', $tournament->id)],
            'target_slot' => ['nullable', 'integer', 'min:1'],
            'bonus_score' => ['nullable', 'integer'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($data['source_type'] === 'round' && empty($data['source_round_id'])) {
            return back()->with('error', 'Source round is required for round-based rules.');
        }

        if ($data['source_type'] === 'group' && empty($data['source_group_id'])) {
            return back()->with('error', 'Source group is required for group-based rules.');
        }

        if ($data['action_type'] === 'advance' && (empty($data['target_round_id']) || empty($data['target_slot']))) {
            return back()->with('error', 'Target round and slot are required for advance rules.');
        }

        if ($data['action_type'] === 'eliminate') {
            $data['target_round_id'] = null;
            $data['target_slot'] = null;
            $data['bonus_score'] = 0;
        }

        AdvancementRule::query()->create([
            'tournament_id' => $tournament->id,
            'source_type' => $data['source_type'],
            'source_round_id' => $data['source_type'] === 'round' ? ($data['source_round_id'] ?? null) : null,
            'source_group_id' => $data['source_type'] === 'group' ? ($data['source_group_id'] ?? null) : null,
            'source_rank' => (int) $data['source_rank'],
            'action_type' => $data['action_type'],
            'target_round_id' => $data['target_round_id'] ?? null,
            'target_slot' => $data['target_slot'] ?? null,
            'bonus_score' => (int) ($data['bonus_score'] ?? 0),
            'priority' => $data['priority'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return back()->with('success', 'Advancement rule created.');
    }

    public function update(Request $request, AdvancementRule $advancementRule): RedirectResponse
    {
        abort_unless($request->user()?->role === User::ROLE_SUPER_ADMIN, 403);

        $data = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'action_type' => ['nullable', Rule::in(['advance', 'eliminate'])],
            'target_round_id' => ['nullable', Rule::exists('rounds', 'id')->where('tournament_id', $advancementRule->tournament_id)],
            'target_slot' => ['nullable', 'integer', 'min:1'],
            'bonus_score' => ['nullable', 'integer'],
        ]);

        if (($data['action_type'] ?? $advancementRule->action_type) === 'eliminate') {
            $data['target_round_id'] = null;
            $data['target_slot'] = null;
            $data['bonus_score'] = 0;
        }

        if (array_key_exists('bonus_score', $data)) {
            $data['bonus_score'] = (int) ($data['bonus_score'] ?? 0);
        }

        $advancementRule->update($data);

        return back()->with('success', 'Advancement rule updated.');
    }

    public function destroy(Request $request, AdvancementRule $advancementRule): RedirectResponse
    {
        abort_unless($request->user()?->role === User::ROLE_SUPER_ADMIN, 403);

        $advancementRule->delete();

        return back()->with('success', 'Advancement rule deleted.');
    }
}
