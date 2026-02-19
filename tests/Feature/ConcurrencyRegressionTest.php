<?php

namespace Tests\Feature;

use App\Models\Round;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class ConcurrencyRegressionTest extends TestCase
{
    use RefreshDatabase;

    #[Group('concurrency')]
    public function test_concurrent_score_updates_are_serialized_without_lost_update(): void
    {
        $this->skipIfConcurrencyUnsupported();

        $round = $this->createLiveRoundWithScore(100);
        $scoreRow = $round->scores()->where('slot', 1)->firstOrFail();
        $resultFile = tempnam(sys_get_temp_dir(), 'iuq-concurrency-');

        DB::beginTransaction();
        try {
            $lockedScore = $round->scores()->where('slot', 1)->lockForUpdate()->firstOrFail();

            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'Failed to fork process for concurrency test.');

            if ($pid === 0) {
                $this->runChildScoreIncrement($resultFile, $round->id, 1, 10);
            }

            // Keep parent lock long enough so child must wait on row lock.
            usleep(1200000);
            $lockedScore->update(['score' => $lockedScore->score + 20]);
            DB::commit();

            pcntl_waitpid($pid, $status);
        } catch (\Throwable $e) {
            DB::rollBack();
            @unlink($resultFile);
            throw $e;
        }

        $childResult = $this->readChildResult($resultFile);
        $this->assertTrue($childResult['ok'], $childResult['message'] ?? 'Child process failed.');
        $this->assertGreaterThan(0.9, (float) $childResult['elapsed_seconds']);

        $scoreRow->refresh();
        $this->assertSame(130, (int) $scoreRow->score);
    }

    #[Group('concurrency')]
    public function test_concurrent_round_status_update_waits_for_round_row_lock(): void
    {
        $this->skipIfConcurrencyUnsupported();

        $round = $this->createLiveRoundWithScore(100);
        $resultFile = tempnam(sys_get_temp_dir(), 'iuq-concurrency-');

        DB::beginTransaction();
        try {
            $lockedRound = Round::query()->whereKey($round->id)->lockForUpdate()->firstOrFail();

            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'Failed to fork process for concurrency test.');

            if ($pid === 0) {
                $this->runChildRoundStatusUpdate($resultFile, $round->id, 'completed');
            }

            // Hold lock so child update cannot proceed immediately.
            usleep(1200000);
            $lockedRound->update(['status' => 'live']);
            DB::commit();

            pcntl_waitpid($pid, $status);
        } catch (\Throwable $e) {
            DB::rollBack();
            @unlink($resultFile);
            throw $e;
        }

        $childResult = $this->readChildResult($resultFile);
        $this->assertTrue($childResult['ok'], $childResult['message'] ?? 'Child process failed.');
        $this->assertGreaterThan(0.9, (float) $childResult['elapsed_seconds']);

        $round->refresh();
        $this->assertSame('completed', $round->status);
    }

    private function createLiveRoundWithScore(int $score): Round
    {
        $tournament = Tournament::query()->create([
            'name' => 'Concurrency Test Tournament',
            'year' => 2030,
            'status' => 'draft',
            'timezone' => 'UTC',
        ]);

        $round = Round::query()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Concurrency Round',
            'teams_per_round' => 3,
            'default_score' => $score,
            'status' => 'live',
            'phase' => 'lightning',
            'score_deltas' => [20, 10, -10],
            'sort_order' => 0,
        ]);

        for ($slot = 1; $slot <= 3; $slot++) {
            $round->participants()->create(['slot' => $slot]);
            $round->scores()->create(['slot' => $slot, 'score' => $score]);
        }

        return $round;
    }

    private function runChildScoreIncrement(string $resultFile, int $roundId, int $slot, int $delta): never
    {
        try {
            DB::disconnect();
            DB::reconnect();

            $start = microtime(true);
            DB::transaction(function () use ($roundId, $slot, $delta): void {
                $score = DB::table('round_scores')
                    ->where('round_id', $roundId)
                    ->where('slot', $slot)
                    ->lockForUpdate()
                    ->first();

                DB::table('round_scores')
                    ->where('id', $score->id)
                    ->update(['score' => (int) $score->score + $delta]);
            });

            file_put_contents($resultFile, json_encode([
                'ok' => true,
                'elapsed_seconds' => microtime(true) - $start,
            ]));
            exit(0);
        } catch (\Throwable $e) {
            file_put_contents($resultFile, json_encode([
                'ok' => false,
                'elapsed_seconds' => 0,
                'message' => $e->getMessage(),
            ]));
            exit(1);
        }
    }

    private function runChildRoundStatusUpdate(string $resultFile, int $roundId, string $status): never
    {
        try {
            DB::disconnect();
            DB::reconnect();

            $start = microtime(true);
            DB::transaction(function () use ($roundId, $status): void {
                $round = DB::table('rounds')
                    ->where('id', $roundId)
                    ->lockForUpdate()
                    ->first();

                DB::table('rounds')
                    ->where('id', $round->id)
                    ->update(['status' => $status]);
            });

            file_put_contents($resultFile, json_encode([
                'ok' => true,
                'elapsed_seconds' => microtime(true) - $start,
            ]));
            exit(0);
        } catch (\Throwable $e) {
            file_put_contents($resultFile, json_encode([
                'ok' => false,
                'elapsed_seconds' => 0,
                'message' => $e->getMessage(),
            ]));
            exit(1);
        }
    }

    private function readChildResult(string $resultFile): array
    {
        $raw = file_get_contents($resultFile);
        @unlink($resultFile);

        $decoded = json_decode($raw ?: '{}', true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'elapsed_seconds' => 0, 'message' => 'Invalid child result payload.'];
        }

        return $decoded;
    }

    private function skipIfConcurrencyUnsupported(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension is required for concurrency regression tests.');
        }

        $connectionName = config('database.default');
        $driver = config("database.connections.{$connectionName}.driver");
        if (!in_array($driver, ['mysql', 'pgsql'], true)) {
            $this->markTestSkipped('Concurrency regression tests require mysql or pgsql (sqlite does not support row-level FOR UPDATE locks).');
        }
    }
}
