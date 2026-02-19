<script setup>
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    tournaments: Array,
    rounds: Array,
    selectedTournament: Object,
    selectedRound: Object,
});

const normalizeRound = (roundPayload) => {
    if (!roundPayload) {
        return null;
    }

    return {
        ...roundPayload,
        participants: (roundPayload.participants || []).map((item) => ({
            id: item.id || `${roundPayload.id}-${item.slot}`,
            slot: item.slot,
            display_name_snapshot: item.display_name_snapshot || item.name || `Team ${item.slot}`,
            icon_url: item.icon_url || null,
        })),
    };
};

const liveRound = ref(normalizeRound(props.selectedRound));
let intervalId = null;
let echoChannel = null;

const columnTintClasses = [
    'bg-red-100/70',
    'bg-green-100/70',
    'bg-blue-100/70',
];

const resolveColumnClass = (index) => columnTintClasses[index % columnTintClasses.length];

watch(
    () => props.selectedRound,
    (nextRound) => {
        liveRound.value = normalizeRound(nextRound);
        setupRealtime();
    },
);

const scoreMap = computed(() => {
    const map = {};
    const rows = (liveRound.value?.result?.entries?.length ?? 0) > 0
        ? liveRound.value.result.entries
        : (liveRound.value?.scores || []);

    rows.forEach((entry) => {
        map[entry.slot] = entry.score;
    });
    return map;
});

const scoreText = (slot) => {
    if (liveRound.value?.hide_public_scores) {
        return '???';
    }

    const value = scoreMap.value[slot];
    return value === undefined || value === null ? 0 : value;
};

const selectTournament = (event) => {
    router.get(route('display.index'), { tournament_id: event.target.value }, { preserveState: true });
};

const selectRound = (event) => {
    router.get(route('display.index'), {
        tournament_id: props.selectedTournament?.id,
        round_id: event.target.value,
    }, { preserveState: true });
};

const fetchRound = async () => {
    if (!liveRound.value?.id) return;

    const response = await axios.get(route('display.round.state', liveRound.value.id));
    liveRound.value = normalizeRound({
        ...liveRound.value,
        ...response.data,
        participants: response.data.participants.map((item) => ({
            id: `${response.data.id}-${item.slot}`,
            slot: item.slot,
            name: item.name,
            icon_url: item.icon_url || null,
        })),
        scores: response.data.scores,
    });
};

const setupRealtime = () => {
    if (echoChannel) {
        window.Echo?.leave(echoChannel);
        echoChannel = null;
    }
    if (intervalId) {
        clearInterval(intervalId);
        intervalId = null;
    }

    if (!liveRound.value?.id) return;

    if (window.Echo) {
        echoChannel = `round.${liveRound.value.id}`;
        window.Echo.channel(echoChannel).listen('.round.updated', (event) => {
            liveRound.value = normalizeRound({
                ...liveRound.value,
                ...event,
                participants: event.participants.map((item) => ({
                    id: `${event.id}-${item.slot}`,
                    slot: item.slot,
                    name: item.name,
                    icon_url: item.icon_url || null,
                })),
                scores: event.scores,
            });
        });
    } else {
        intervalId = setInterval(fetchRound, 3000);
    }
};

onMounted(() => {
    setupRealtime();
});

onBeforeUnmount(() => {
    if (echoChannel) {
        window.Echo?.leave(echoChannel);
    }
    if (intervalId) {
        clearInterval(intervalId);
    }
});
</script>

<template>
    <Head title="Display" />
    <MainLayout title="Display Page">
        <div class="mb-4 grid gap-2 rounded border bg-white p-4 md:grid-cols-2">
            <select class="rounded border px-2 py-1" :value="selectedTournament?.id" @change="selectTournament">
                <option v-for="tournament in tournaments" :key="tournament.id" :value="tournament.id">
                    {{ tournament.name }} ({{ tournament.year }})
                </option>
            </select>
            <select class="rounded border px-2 py-1" :value="liveRound?.id" @change="selectRound">
                <option v-for="round in rounds" :key="round.id" :value="round.id">
                    {{ round.name }}
                </option>
            </select>
        </div>

        <div v-if="liveRound" class="rounded border bg-white">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <div class="text-2xl font-semibold">{{ liveRound.name }}</div>
                <span
                    v-if="liveRound.hide_public_scores"
                    class="rounded border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800"
                >
                    Scores Hidden
                </span>
            </div>
            <div class="grid" :style="`grid-template-columns: repeat(${liveRound.participants.length || 1}, minmax(0, 1fr));`">
                <div
                    v-for="(participant, index) in liveRound.participants"
                    :key="participant.id"
                    class="border-r last:border-r-0"
                    :class="resolveColumnClass(index)"
                >
                    <div class="border-b px-3 py-4 text-center">
                        <img
                            v-if="participant.icon_url"
                            :src="participant.icon_url"
                            :alt="participant.display_name_snapshot || `Team ${participant.slot}`"
                            class="mx-auto h-24 w-24 rounded-none border object-contain"
                        />
                        <div v-else class="mx-auto flex h-24 w-24 items-center justify-center rounded-none border bg-white text-xs text-gray-500">
                            No Logo
                        </div>
                    </div>
                    <div class="border-b px-3 py-6 text-center text-4xl font-semibold">
                        {{ participant.display_name_snapshot || `Team ${participant.slot}` }}
                    </div>
                    <div class="px-3 py-10 text-center text-7xl font-light">{{ scoreText(participant.slot) }}</div>
                </div>
            </div>
            <div class="border-t px-4 py-4 text-center">
                <img
                    v-if="selectedTournament?.logo_url"
                    :src="selectedTournament.logo_url"
                    :alt="`${selectedTournament?.name || 'Tournament'} logo`"
                    class="mx-auto max-h-28 w-auto object-contain"
                />
                <div v-else class="text-2xl font-semibold">CUHKCAS Logo Placeholder</div>
            </div>
            <div class="border-t px-4 py-4 text-center text-4xl font-bold">{{ selectedTournament?.name }}</div>
        </div>
    </MainLayout>
</template>
