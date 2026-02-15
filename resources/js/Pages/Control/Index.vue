<script setup>
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { computed, ref } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { statusBadgeClass } from '@/composables/useStatusBadge';

const props = defineProps({
    tournaments: Array,
    rounds: Array,
    selectedTournamentId: Number,
    selectedRound: Object,
});

const { t } = useI18n();

const scoreMap = computed(() => {
    const map = {};
    const rows = (props.selectedRound?.result?.entries?.length ?? 0) > 0
        ? props.selectedRound.result.entries
        : (props.selectedRound?.scores || []);

    rows.forEach((item) => {
        map[item.slot] = item.score;
    });
    return map;
});

const deltas = computed(() => props.selectedRound?.score_deltas || [20, 10, -10]);
const isLiveRound = computed(() => props.selectedRound?.status === 'live');
const canGoToBuzzer = computed(() => isLiveRound.value && props.selectedRound?.phase !== 'buzzer');
const canClearRound = computed(() => ['live', 'completed'].includes(props.selectedRound?.status));
const canToggleCompetition = computed(() => ['draft', 'live'].includes(props.selectedRound?.status));
const isEndModalOpen = ref(false);
const endResultEntries = ref([]);

const selectTournament = (event) => {
    router.get(route('control.index'), { tournament_id: event.target.value }, { preserveState: true });
};

const selectRound = (event) => {
    router.get(route('control.index'), {
        tournament_id: props.selectedTournamentId,
        round_id: event.target.value,
    }, { preserveState: true });
};

const runAction = (payload) => {
    if (!props.selectedRound) return;
    router.post(route('control.round.action', props.selectedRound.id), payload, {
        preserveScroll: true,
        preserveState: false,
    });
};

const startOrEndCompetition = () => {
    if (!props.selectedRound) return;
    if (props.selectedRound.status !== 'draft' && props.selectedRound.status !== 'live') {
        return;
    }
    if (props.selectedRound.status === 'live') {
        prepareEndCompetitionModal();
        return;
    }
    runAction({ action: 'start_competition' });
};

const clearRound = () => {
    if (!props.selectedRound) return;
    const confirmed = confirm('Are you sure you want to clear this round? Scores will reset and status will return to draft.');
    if (!confirmed) return;
    runAction({ action: 'clear' });
};

const prepareEndCompetitionModal = () => {
    if (!props.selectedRound) {
        return;
    }

    const scoreLookup = {};
    (props.selectedRound.scores || []).forEach((row) => {
        scoreLookup[row.slot] = row.score;
    });

    const entries = (props.selectedRound.participants || []).map((participant) => ({
        slot: participant.slot,
        name: participant.display_name_snapshot || `Team ${participant.slot}`,
        score: Number(scoreLookup[participant.slot] || 0),
        rank: null,
    }));

    entries.sort((a, b) => b.score - a.score || a.slot - b.slot);
    entries.forEach((entry, index) => {
        entry.rank = index + 1;
    });
    entries.sort((a, b) => a.slot - b.slot);

    endResultEntries.value = entries;
    isEndModalOpen.value = true;
};

const confirmEndCompetition = () => {
    const payload = {
        action: 'end_competition',
        results: endResultEntries.value.map((entry) => ({
            slot: entry.slot,
            score: Number(entry.score),
            rank: Number(entry.rank),
        })),
    };

    isEndModalOpen.value = false;
    runAction(payload);
};
</script>

<template>
    <Head title="Control" />
    <MainLayout :title="t('control')">
        <div class="mb-4 grid gap-2 rounded border bg-white p-4 md:grid-cols-2">
            <select class="rounded border px-2 py-1" :value="selectedTournamentId" @change="selectTournament">
                <option v-for="tournament in tournaments" :key="tournament.id" :value="tournament.id">
                    {{ tournament.name }} ({{ tournament.year }})
                </option>
            </select>
            <select class="rounded border px-2 py-1" :value="selectedRound?.id" @change="selectRound">
                <option v-for="round in rounds" :key="round.id" :value="round.id">
                    {{ round.name }}
                </option>
            </select>
        </div>

        <div v-if="selectedRound" class="space-y-4">
            <div class="rounded border bg-white p-4">
                <div class="grid gap-2 text-sm md:grid-cols-2">
                    <div>
                        <span class="font-semibold">{{ t('roundStatus') }}:</span>
                        <span class="ml-1 rounded border px-2 py-0.5" :class="statusBadgeClass(selectedRound.status)">{{ selectedRound.status }}</span>
                    </div>
                    <div>
                        <span class="font-semibold">{{ t('roundPhase') }}:</span>
                        <span class="ml-1 rounded bg-gray-100 px-2 py-0.5">{{ selectedRound.phase }}</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 rounded border bg-white p-4">
                <button
                    class="rounded border px-3 py-1 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canToggleCompetition"
                    @click="startOrEndCompetition"
                >
                    {{ selectedRound.status === 'live' ? t('endCompetition') : t('startCompetition') }}
                </button>
                <button
                    class="rounded border px-3 py-1 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canGoToBuzzer"
                    @click="runAction({ action: 'to_buzzer' })"
                >
                    {{ t('toBuzzerRound') }}
                </button>
                <button
                    class="rounded border px-3 py-1 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!isLiveRound"
                    @click="runAction({ action: 'undo' })"
                >
                    {{ t('undo') }}
                </button>
                <button
                    class="rounded border px-3 py-1 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canClearRound"
                    @click="clearRound"
                >
                    {{ t('clear') }}
                </button>
            </div>

            <div class="overflow-auto rounded border bg-white">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="border px-2 py-1">Slot</th>
                            <th class="border px-2 py-1">{{ t('teamNames') }}</th>
                            <th class="border px-2 py-1">{{ t('scores') }}</th>
                            <th class="border px-2 py-1">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="participant in selectedRound.participants" :key="participant.id">
                            <td class="border px-2 py-1">{{ participant.slot }}</td>
                            <td class="border px-2 py-1">{{ participant.display_name_snapshot || `Team ${participant.slot}` }}</td>
                            <td class="border px-2 py-1 text-center text-xl">{{ scoreMap[participant.slot] || 0 }}</td>
                            <td class="border px-2 py-1">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="delta in deltas"
                                        :key="`${participant.slot}-${delta}`"
                                        class="rounded border px-2 py-1 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="!isLiveRound"
                                        @click="runAction({ action: 'add_score', slot: participant.slot, delta })"
                                    >
                                        {{ delta > 0 ? `+${delta}` : delta }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="isEndModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-2xl rounded border bg-white p-4">
                <h3 class="mb-3 text-lg font-semibold">Confirm Round Result</h3>
                <p class="mb-3 text-sm text-gray-600">
                    Review and adjust final scores/rankings before ending this competition.
                </p>
                <div class="overflow-auto rounded border">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="border px-2 py-1 text-left">Slot</th>
                                <th class="border px-2 py-1 text-left">Team</th>
                                <th class="border px-2 py-1 text-left">Score</th>
                                <th class="border px-2 py-1 text-left">Rank</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="entry in endResultEntries" :key="entry.slot">
                                <td class="border px-2 py-1">{{ entry.slot }}</td>
                                <td class="border px-2 py-1">{{ entry.name }}</td>
                                <td class="border px-2 py-1">
                                    <input v-model.number="entry.score" type="number" min="0" class="w-24 rounded border px-2 py-1" />
                                </td>
                                <td class="border px-2 py-1">
                                    <input v-model.number="entry.rank" type="number" min="1" class="w-24 rounded border px-2 py-1" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex justify-end gap-2">
                    <button class="rounded border px-3 py-1" @click="isEndModalOpen = false">Cancel</button>
                    <button class="rounded border bg-gray-900 px-3 py-1 text-white" @click="confirmEndCompetition">Confirm & End</button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
