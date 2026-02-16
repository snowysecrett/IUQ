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
    private const DEFAULT_LIGHTNING_DELTAS = [20];
    private const DEFAULT_BUZZER_NORMAL_DELTAS = [20, 10, -10];
    private const DEFAULT_BUZZER_FEVER_DELTAS = [30, 15, -15];
    private const DEFAULT_BUZZER_ULTIMATE_DELTAS = [40, 20, -20];

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
            'has_fever' => ['nullable', 'boolean'],
            'has_ultimate_fever' => ['nullable', 'boolean'],
            'default_lightning_score_deltas' => ['nullable', 'array'],
            'default_lightning_score_deltas.*' => ['integer'],
            'default_buzzer_normal_score_deltas' => ['nullable', 'array'],
            'default_buzzer_normal_score_deltas.*' => ['integer'],
            'default_buzzer_fever_score_deltas' => ['nullable', 'array'],
            'default_buzzer_fever_score_deltas.*' => ['integer'],
            'default_buzzer_ultimate_score_deltas' => ['nullable', 'array'],
            'default_buzzer_ultimate_score_deltas.*' => ['integer'],
        ]);

        $config = $this->resolvePhaseConfig($data, null);

        $tournament->roundTemplates()->create([
            ...$data,
            'default_score' => $data['default_score'] ?? 100,
            'sort_order' => $data['sort_order'] ?? 0,
            'default_score_deltas' => $config['buzzer_normal_score_deltas'],
            'has_fever' => $config['has_fever'],
            'has_ultimate_fever' => $config['has_ultimate_fever'],
            'default_lightning_score_deltas' => $config['lightning_score_deltas'],
            'default_buzzer_normal_score_deltas' => $config['buzzer_normal_score_deltas'],
            'default_buzzer_fever_score_deltas' => $config['buzzer_fever_score_deltas'],
            'default_buzzer_ultimate_score_deltas' => $config['buzzer_ultimate_score_deltas'],
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
            'has_fever' => ['nullable', 'boolean'],
            'has_ultimate_fever' => ['nullable', 'boolean'],
            'default_lightning_score_deltas' => ['nullable', 'array'],
            'default_lightning_score_deltas.*' => ['integer'],
            'default_buzzer_normal_score_deltas' => ['nullable', 'array'],
            'default_buzzer_normal_score_deltas.*' => ['integer'],
            'default_buzzer_fever_score_deltas' => ['nullable', 'array'],
            'default_buzzer_fever_score_deltas.*' => ['integer'],
            'default_buzzer_ultimate_score_deltas' => ['nullable', 'array'],
            'default_buzzer_ultimate_score_deltas.*' => ['integer'],
        ]);

        $config = $this->resolvePhaseConfig($data, $roundTemplate);

        $roundTemplate->update([
            ...$data,
            'default_score' => $data['default_score'] ?? 100,
            'sort_order' => $data['sort_order'] ?? 0,
            'default_score_deltas' => $config['buzzer_normal_score_deltas'],
            'has_fever' => $config['has_fever'],
            'has_ultimate_fever' => $config['has_ultimate_fever'],
            'default_lightning_score_deltas' => $config['lightning_score_deltas'],
            'default_buzzer_normal_score_deltas' => $config['buzzer_normal_score_deltas'],
            'default_buzzer_fever_score_deltas' => $config['buzzer_fever_score_deltas'],
            'default_buzzer_ultimate_score_deltas' => $config['buzzer_ultimate_score_deltas'],
        ]);

        return back()->with('success', 'Round template updated.');
    }

    public function destroy(Request $request, RoundTemplate $roundTemplate): RedirectResponse
    {
        abort_unless($request->user()?->role === User::ROLE_SUPER_ADMIN, 403);

        $roundTemplate->delete();

        return back()->with('success', 'Round template deleted.');
    }

    private function resolvePhaseConfig(array $data, ?RoundTemplate $current): array
    {
        $legacy = $data['default_score_deltas']
            ?? $current?->default_score_deltas
            ?? self::DEFAULT_BUZZER_NORMAL_DELTAS;
        $base = $this->normalizeDeltas($legacy, self::DEFAULT_BUZZER_NORMAL_DELTAS);

        $hasFever = filter_var($data['has_fever'] ?? $current?->has_fever ?? false, FILTER_VALIDATE_BOOLEAN);
        $hasUltimate = filter_var($data['has_ultimate_fever'] ?? $current?->has_ultimate_fever ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($hasUltimate) {
            $hasFever = true;
        }

        $lightning = $this->normalizeDeltas(
            $data['default_lightning_score_deltas']
                ?? $current?->default_lightning_score_deltas
                ?? self::DEFAULT_LIGHTNING_DELTAS,
            self::DEFAULT_LIGHTNING_DELTAS
        );
        $normal = $this->normalizeDeltas(
            $data['default_buzzer_normal_score_deltas']
                ?? $current?->default_buzzer_normal_score_deltas
                ?? $base,
            self::DEFAULT_BUZZER_NORMAL_DELTAS
        );
        $fever = $hasFever
            ? $this->normalizeDeltas(
                $data['default_buzzer_fever_score_deltas']
                    ?? $current?->default_buzzer_fever_score_deltas
                    ?? self::DEFAULT_BUZZER_FEVER_DELTAS,
                self::DEFAULT_BUZZER_FEVER_DELTAS
            )
            : null;
        $ultimate = $hasUltimate
            ? $this->normalizeDeltas(
                $data['default_buzzer_ultimate_score_deltas']
                    ?? $current?->default_buzzer_ultimate_score_deltas
                    ?? self::DEFAULT_BUZZER_ULTIMATE_DELTAS,
                self::DEFAULT_BUZZER_ULTIMATE_DELTAS
            )
            : null;

        return [
            'has_fever' => $hasFever,
            'has_ultimate_fever' => $hasUltimate,
            'lightning_score_deltas' => $lightning,
            'buzzer_normal_score_deltas' => $normal,
            'buzzer_fever_score_deltas' => $fever,
            'buzzer_ultimate_score_deltas' => $ultimate,
        ];
    }

    private function normalizeDeltas(?array $values, array $fallback): array
    {
        $clean = collect($values ?? [])
            ->map(fn ($value) => is_numeric($value) ? (int) $value : null)
            ->filter(fn ($value) => $value !== null)
            ->values()
            ->all();

        return count($clean) > 0 ? $clean : $fallback;
    }
}
