<?php

namespace App\Events;

use App\Models\Round;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class RoundUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Round $round) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('round.'.$this->round->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'round.updated';
    }

    public function broadcastWith(): array
    {
        $round = $this->round->load(['participants.team', 'scores', 'result.entries', 'tournament']);
        $scoreRows = $round->result?->entries?->isNotEmpty()
            ? $round->result->entries->map(fn ($entry) => [
                'slot' => $entry->slot,
                'score' => $entry->score,
            ])->values()
            : $round->scores->map(fn ($score) => [
                'slot' => $score->slot,
                'score' => $score->score,
            ])->values();

        return [
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
        ];
    }

    private function resolveLogoUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }
}
