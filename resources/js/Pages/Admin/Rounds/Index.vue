<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { statusBadgeClass } from '@/composables/useStatusBadge';
import { ref } from 'vue';
import { useI18n } from '@/composables/useI18n';

const props = defineProps({
    rounds: Array,
    filters: Object,
    tournaments: Array,
    years: Array,
});

const filterForm = useForm({
    search: props.filters.search || '',
    status: props.filters.status || '',
    tournament_id: props.filters.tournament_id || '',
    year: props.filters.year || '',
});
const isOverwriteModalOpen = ref(false);
const overwriteRoundId = ref(null);
const overwriteEntries = ref([]);
const forceApply = ref(false);
const { t } = useI18n();
const statusLabel = (status) => {
    if (status === 'draft') return t('statusDraft');
    if (status === 'live') return t('statusLive');
    if (status === 'completed') return t('statusCompleted');
    return status;
};
const phaseLabel = (phase) => {
    if (phase === 'lightning') return t('phaseLightning');
    if (phase === 'buzzer_normal') return t('phaseBuzzerNormal');
    if (phase === 'buzzer_fever') return t('phaseBuzzerFever');
    if (phase === 'buzzer_ultimate_fever') return t('phaseBuzzerUltimateFever');
    return phase;
};

const applyFilters = () => {
    router.get(route('admin.rounds.index'), filterForm.data(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearFilters = () => {
    filterForm.reset();
    router.get(route('admin.rounds.index'), {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const formatScheduledAt = (value) => {
    if (!value) {
        return t('noSchedule');
    }

    const normalized = String(value).replace('T', ' ').replace('Z', '');
    return normalized.includes('.') ? normalized.split('.')[0] : normalized;
};

const openOverwriteModal = (round) => {
    overwriteRoundId.value = round.id;
    forceApply.value = false;
    overwriteEntries.value = (round.participants || []).map((participant) => ({
        slot: participant.slot,
        name: participant.name,
        score: participant.score,
        rank: participant.rank || null,
    }));
    isOverwriteModalOpen.value = true;
};

const submitOverwriteResult = () => {
    if (!overwriteRoundId.value) {
        return;
    }

    router.post(route('admin.rounds.overwrite-result', overwriteRoundId.value), {
        results: overwriteEntries.value.map((entry) => ({
            slot: Number(entry.slot),
            score: Number(entry.score),
            rank: entry.rank ? Number(entry.rank) : null,
        })),
        force_apply: forceApply.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isOverwriteModalOpen.value = false;
        },
    });
};
</script>

<template>
    <Head :title="t('rounds')" />
    <MainLayout :title="t('rounds')">
        <form @submit.prevent="applyFilters" class="mb-4 grid gap-2 rounded border bg-white p-4 md:grid-cols-5">
            <input v-model="filterForm.search" class="rounded border px-2 py-1" :placeholder="`${t('round')} / ${t('tournament')}`" />
            <select v-model="filterForm.status" class="rounded border px-2 py-1">
                <option value="">{{ t('allStatuses') }}</option>
                <option value="draft">{{ t('statusDraft') }}</option>
                <option value="live">{{ t('statusLive') }}</option>
                <option value="completed">{{ t('statusCompleted') }}</option>
            </select>
            <select v-model="filterForm.tournament_id" class="rounded border px-2 py-1">
                <option value="">{{ t('allTournaments') }}</option>
                <option v-for="tournament in tournaments" :key="tournament.id" :value="tournament.id">
                    {{ tournament.name }} ({{ tournament.year }})
                </option>
            </select>
            <select v-model="filterForm.year" class="rounded border px-2 py-1">
                <option value="">{{ t('allYears') }}</option>
                <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
            </select>
            <div class="flex gap-2">
                <button class="rounded border bg-gray-900 px-3 py-1 text-white">{{ t('apply') }}</button>
                <button type="button" class="rounded border px-3 py-1" @click="clearFilters">{{ t('clearFilters') }}</button>
            </div>
        </form>

        <div class="overflow-auto rounded border bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border px-2 py-1 text-left">{{ t('tournament') }}</th>
                        <th class="border px-2 py-1 text-left">{{ t('round') }}</th>
                        <th class="border px-2 py-1 text-left">{{ t('roundStatus') }}</th>
                        <th class="border px-2 py-1 text-left">{{ t('phase') }}</th>
                        <th class="border px-2 py-1 text-left">{{ t('participants') }}</th>
                        <th class="border px-2 py-1 text-left">{{ t('winner') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="round in rounds" :key="round.id">
                        <td class="border px-2 py-1">{{ round.tournament.name }} ({{ round.tournament.year }})</td>
                        <td class="border px-2 py-1">
                            <div class="font-medium">{{ round.name }}</div>
                            <div class="text-xs text-gray-500">{{ round.code || t('noCode') }}</div>
                            <div class="text-xs text-gray-500">{{ formatScheduledAt(round.scheduled_start_at) }}</div>
                        </td>
                        <td class="border px-2 py-1">
                            <span class="rounded border px-2 py-0.5" :class="statusBadgeClass(round.status)">{{ statusLabel(round.status) }}</span>
                            <div v-if="round.auto_updated_from_override" class="mt-1 text-xs text-blue-700">{{ t('autoUpdatedFromOverride') }}</div>
                            <div v-if="round.result_is_stale" class="mt-1 text-xs text-amber-700">{{ t('resultStale') }}</div>
                        </td>
                        <td class="border px-2 py-1">{{ phaseLabel(round.phase) }}</td>
                        <td class="border px-2 py-1">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="participant in round.participants"
                                    :key="`${round.id}-${participant.slot}`"
                                    class="rounded bg-gray-100 px-2 py-0.5"
                                >
                                    {{ participant.name }}: {{ participant.score }}
                                </span>
                            </div>
                        </td>
                        <td class="border px-2 py-1">
                            <template v-if="round.winner">
                                <div class="font-medium">{{ round.winner.name }}</div>
                                <div class="text-xs text-gray-500">{{ t('scoreLabel') }}: {{ round.winner.score }}</div>
                            </template>
                            <span v-else>-</span>
                            <div v-if="round.status === 'completed'" class="mt-2">
                                <button class="rounded border px-2 py-1 text-xs" @click="openOverwriteModal(round)">
                                    {{ t('overwriteResult') }}
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="rounds.length === 0">
                        <td colspan="6" class="border px-2 py-6 text-center text-gray-500">{{ t('noRoundsFound') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="isOverwriteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-2xl rounded border bg-white p-4">
                <h3 class="mb-3 text-lg font-semibold">{{ t('overwriteRoundResult') }}</h3>
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
                            <tr v-for="entry in overwriteEntries" :key="entry.slot">
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
                <label class="mt-3 inline-flex items-center gap-2 text-sm">
                    <input v-model="forceApply" type="checkbox" />
                    {{ t('forceApplyAutoAdvancement') }}
                </label>
                <div class="mt-3 flex justify-end gap-2">
                    <button class="rounded border px-3 py-1" @click="isOverwriteModalOpen = false">{{ t('cancel') }}</button>
                    <button class="rounded border bg-gray-900 px-3 py-1 text-white" @click="submitOverwriteResult">{{ t('saveResult') }}</button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
