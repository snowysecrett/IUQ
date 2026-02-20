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

const DEFAULT_LIGHTNING_DELTAS = [20];
const DEFAULT_BUZZER_NORMAL_DELTAS = [20, 10, -10];
const DEFAULT_BUZZER_FEVER_DELTAS = [30, 15, -15];
const DEFAULT_BUZZER_ULTIMATE_DELTAS = [40, 20, -20];
const normalizeDeltas = (values, fallback) => (Array.isArray(values) && values.length > 0 ? values : fallback);

const currentDeltas = computed(() => {
    if (!props.selectedRound) {
        return DEFAULT_LIGHTNING_DELTAS;
    }

    const phase = props.selectedRound.phase;
    if (phase === 'lightning') {
        return normalizeDeltas(
            props.selectedRound.lightning_score_deltas || props.selectedRound.score_deltas,
            DEFAULT_LIGHTNING_DELTAS
        );
    }
    if (phase === 'buzzer_fever') {
        return normalizeDeltas(
            props.selectedRound.buzzer_fever_score_deltas || props.selectedRound.buzzer_normal_score_deltas || props.selectedRound.score_deltas,
            DEFAULT_BUZZER_FEVER_DELTAS
        );
    }
    if (phase === 'buzzer_ultimate_fever') {
        return normalizeDeltas(
            props.selectedRound.buzzer_ultimate_score_deltas || props.selectedRound.buzzer_normal_score_deltas || props.selectedRound.score_deltas,
            DEFAULT_BUZZER_ULTIMATE_DELTAS
        );
    }

    return normalizeDeltas(
        props.selectedRound.buzzer_normal_score_deltas || props.selectedRound.score_deltas,
        DEFAULT_BUZZER_NORMAL_DELTAS
    );
});
const isLiveRound = computed(() => props.selectedRound?.status === 'live');
const phase = computed(() => props.selectedRound?.phase || 'lightning');
const isLightningPhase = computed(() => phase.value === 'lightning');
const isBuzzerNormalPhase = computed(() => phase.value === 'buzzer_normal' || phase.value === 'buzzer');
const isBuzzerFeverPhase = computed(() => phase.value === 'buzzer_fever');
const isBuzzerUltimatePhase = computed(() => phase.value === 'buzzer_ultimate_fever');
const canGoToBuzzer = computed(() => isLiveRound.value && isLightningPhase.value);
const hasFever = computed(() => !!props.selectedRound?.has_fever);
const hasUltimateFever = computed(() => !!props.selectedRound?.has_ultimate_fever);
const showNextBuzzerButton = computed(() => !isLightningPhase.value && !isBuzzerUltimatePhase.value);
const canAdvanceBuzzerPhase = computed(() => {
    if (!isLiveRound.value) {
        return false;
    }
    if (isBuzzerNormalPhase.value) {
        return hasFever.value;
    }
    if (isBuzzerFeverPhase.value) {
        return hasUltimateFever.value;
    }

    return false;
});
const nextBuzzerButtonLabel = computed(() => {
    if (isBuzzerNormalPhase.value) {
        return t('toFeverRound');
    }
    if (isBuzzerFeverPhase.value) {
        return t('toUltimateFeverRound');
    }

    return '';
});
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

const confirmToBuzzerRound = () => {
    if (!canGoToBuzzer.value) return;
    if (!confirm(t('confirmToBuzzerRound'))) return;
    runAction({ action: 'to_buzzer' });
};

const confirmNextBuzzerPhase = () => {
    if (!canAdvanceBuzzerPhase.value) return;
    if (isBuzzerNormalPhase.value && !confirm(t('confirmToFeverRound'))) return;
    if (isBuzzerFeverPhase.value && !confirm(t('confirmToUltimateFeverRound'))) return;
    runAction({ action: 'to_next_buzzer_phase' });
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
    const confirmed = confirm(t('confirmClearRound'));
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
        name: participant.display_name_snapshot || `${t('team')} ${participant.slot}`,
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

const phaseLabel = computed(() => {
    if (isLightningPhase.value) return t('lightningRound');
    if (isBuzzerNormalPhase.value) return t('buzzerNormalRound');
    if (isBuzzerFeverPhase.value) return t('buzzerFeverRound');
    if (isBuzzerUltimatePhase.value) return t('buzzerUltimateRound');

    return props.selectedRound?.phase;
});
</script>

<template>
    <Head :title="t('control')" />
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
                        <span class="ml-1 rounded bg-gray-100 px-2 py-0.5">{{ phaseLabel }}</span>
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
                    @click="confirmToBuzzerRound"
                >
                    {{ t('toBuzzerRound') }}
                </button>
                <button
                    v-if="showNextBuzzerButton"
                    class="rounded border px-3 py-1 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canAdvanceBuzzerPhase"
                    @click="confirmNextBuzzerPhase"
                >
                    {{ nextBuzzerButtonLabel }}
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
                            <th class="border px-2 py-1">{{ t('slot') }}</th>
                            <th class="border px-2 py-1">{{ t('teamNames') }}</th>
                            <th class="border px-2 py-1">{{ t('scores') }}</th>
                            <th class="border px-2 py-1">{{ t('actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="participant in selectedRound.participants" :key="participant.id">
                            <td class="border px-2 py-1">{{ participant.slot }}</td>
                            <td class="border px-2 py-1">{{ participant.display_name_snapshot || `${t('team')} ${participant.slot}` }}</td>
                            <td class="border px-2 py-1 text-center text-xl">{{ scoreMap[participant.slot] || 0 }}</td>
                            <td class="border px-2 py-1">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="delta in currentDeltas"
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
                <h3 class="mb-3 text-lg font-semibold">{{ t('confirmRoundResult') }}</h3>
                <p class="mb-3 text-sm text-gray-600">
                    {{ t('reviewFinalScores') }}
                </p>
                <div class="overflow-auto rounded border">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="border px-2 py-1 text-left">{{ t('slot') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('team') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('scores') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('rank') }}</th>
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
                    <button class="rounded border px-3 py-1" @click="isEndModalOpen = false">{{ t('cancel') }}</button>
                    <button class="rounded border bg-gray-900 px-3 py-1 text-white" @click="confirmEndCompetition">{{ t('confirmAndEnd') }}</button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
