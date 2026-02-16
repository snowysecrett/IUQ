<?php

namespace App\Events;

use App\Models\Round;
use App\Support\MediaPath;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

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
        $hidePublicScores = (bool) $round->hide_public_scores;
        $scoreRows = $round->result?->entries?->isNotEmpty()
            ? $round->result->entries->map(fn ($entry) => [
                'slot' => $entry->slot,
                'score' => $hidePublicScores ? null : $entry->score,
            ])->values()
            : $round->scores->map(fn ($score) => [
                'slot' => $score->slot,
                'score' => $hidePublicScores ? null : $score->score,
            ])->values();

        return [
            'id' => $round->id,
            'name' => $round->name,
            'status' => $round->status,
            'phase' => $round->phase,
            'hide_public_scores' => $hidePublicScores,
            'tournament' => [
                'id' => $round->tournament->id,
                'name' => $round->tournament->name,
            ],
            'participants' => $round->participants->map(fn ($participant) => [
                'slot' => $participant->slot,
                'name' => $participant->team?->team_name
                    ?: $participant->display_name_snapshot
                    ?: ("Team {$participant->slot}"),
                'icon_url' => MediaPath::toUrl($participant->team?->icon_path ?: $participant->icon_snapshot_path),
            ])->values(),
            'scores' => $scoreRows,
        ];
    }
}
