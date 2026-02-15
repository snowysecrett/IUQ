<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { statusBadgeClass } from '@/composables/useStatusBadge';
import { ref } from 'vue';

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
        return 'No schedule';
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
    <Head title="Rounds" />
    <MainLayout title="Rounds">
        <form @submit.prevent="applyFilters" class="mb-4 grid gap-2 rounded border bg-white p-4 md:grid-cols-5">
            <input v-model="filterForm.search" class="rounded border px-2 py-1" placeholder="Search round or tournament" />
            <select v-model="filterForm.status" class="rounded border px-2 py-1">
                <option value="">All statuses</option>
                <option value="draft">draft</option>
                <option value="live">live</option>
                <option value="completed">completed</option>
            </select>
            <select v-model="filterForm.tournament_id" class="rounded border px-2 py-1">
                <option value="">All tournaments</option>
                <option v-for="tournament in tournaments" :key="tournament.id" :value="tournament.id">
                    {{ tournament.name }} ({{ tournament.year }})
                </option>
            </select>
            <select v-model="filterForm.year" class="rounded border px-2 py-1">
                <option value="">All years</option>
                <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
            </select>
            <div class="flex gap-2">
                <button class="rounded border bg-gray-900 px-3 py-1 text-white">Apply</button>
                <button type="button" class="rounded border px-3 py-1" @click="clearFilters">Clear</button>
            </div>
        </form>

        <div class="overflow-auto rounded border bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border px-2 py-1 text-left">Tournament</th>
                        <th class="border px-2 py-1 text-left">Round</th>
                        <th class="border px-2 py-1 text-left">Status</th>
                        <th class="border px-2 py-1 text-left">Phase</th>
                        <th class="border px-2 py-1 text-left">Participants</th>
                        <th class="border px-2 py-1 text-left">Winner</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="round in rounds" :key="round.id">
                        <td class="border px-2 py-1">{{ round.tournament.name }} ({{ round.tournament.year }})</td>
                        <td class="border px-2 py-1">
                            <div class="font-medium">{{ round.name }}</div>
                            <div class="text-xs text-gray-500">{{ round.code || 'No code' }}</div>
                            <div class="text-xs text-gray-500">{{ formatScheduledAt(round.scheduled_start_at) }}</div>
                        </td>
                        <td class="border px-2 py-1">
                            <span class="rounded border px-2 py-0.5" :class="statusBadgeClass(round.status)">{{ round.status }}</span>
                            <div v-if="round.auto_updated_from_override" class="mt-1 text-xs text-blue-700">Auto-updated from override</div>
                            <div v-if="round.result_is_stale" class="mt-1 text-xs text-amber-700">Result stale</div>
                        </td>
                        <td class="border px-2 py-1">{{ round.phase }}</td>
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
                                <div class="text-xs text-gray-500">Score: {{ round.winner.score }}</div>
                            </template>
                            <span v-else>-</span>
                            <div v-if="round.status === 'completed'" class="mt-2">
                                <button class="rounded border px-2 py-1 text-xs" @click="openOverwriteModal(round)">
                                    Overwrite Result
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="rounds.length === 0">
                        <td colspan="6" class="border px-2 py-6 text-center text-gray-500">No rounds found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="isOverwriteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-2xl rounded border bg-white p-4">
                <h3 class="mb-3 text-lg font-semibold">Overwrite Round Result</h3>
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
                    Force apply auto-advancement (overwrite manual-locked target slots)
                </label>
                <div class="mt-3 flex justify-end gap-2">
                    <button class="rounded border px-3 py-1" @click="isOverwriteModalOpen = false">Cancel</button>
                    <button class="rounded border bg-gray-900 px-3 py-1 text-white" @click="submitOverwriteResult">Save Result</button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
