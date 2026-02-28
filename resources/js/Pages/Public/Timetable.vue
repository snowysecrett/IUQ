<script setup>
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { statusBadgeClass } from '@/composables/useStatusBadge';
import { useI18n } from '@/composables/useI18n';

const props = defineProps({
    years: Array,
    tournaments: Array,
    selectedTournament: Object,
    selectedSection: String,
    selectedRounds: Array,
    sectionRoundCounts: Object,
});
const { t } = useI18n();

const sectionTabs = [
    { key: 'upcoming', labelKey: 'upcomingRounds' },
    { key: 'live', labelKey: 'liveRounds' },
    { key: 'completed', labelKey: 'completedRounds' },
];

const filterYear = (event) => {
    const value = event.target.value;
    const query = {
        ...(value ? { year: value } : {}),
        section: props.selectedSection || 'upcoming',
    };
    router.get(route('timetable.index'), query, { preserveState: true });
};

const selectTournament = (event) => {
    router.get(route('timetable.index'), {
        tournament_id: event.target.value,
        section: props.selectedSection || 'upcoming',
    }, { preserveState: true });
};

const selectSection = (section) => {
    const query = {
        section,
    };
    if (props.selectedTournament?.id) {
        query.tournament_id = props.selectedTournament.id;
    }
    router.get(route('timetable.index'), query, { preserveState: true });
};

const formatScheduledAt = (value) => {
    if (!value) {
        return t('tbd');
    }

    const normalized = String(value).replace('T', ' ').replace('Z', '');
    return normalized.includes('.') ? normalized.split('.')[0] : normalized;
};

const scoreFor = (round, slot) => {
    if (round.hide_public_scores) {
        return '???';
    }

    const resultScore = round.result?.entries?.find((item) => item.slot === slot)?.score;
    if (resultScore !== undefined && resultScore !== null) {
        return resultScore;
    }

    return round.scores.find((item) => item.slot === slot)?.score ?? 0;
};

const phaseLabel = (phase) => {
    if (phase === 'lightning') return t('lightningRound');
    if (phase === 'buzzer' || phase === 'buzzer_normal') return t('buzzerNormalRound');
    if (phase === 'buzzer_fever') return t('buzzerFeverRound');
    if (phase === 'buzzer_ultimate_fever') return t('buzzerUltimateRound');

    return phase || '-';
};

const statusLabel = (status) => {
    if (status === 'draft') return t('statusDraft');
    if (status === 'live') return t('statusLive');
    if (status === 'completed') return t('statusCompleted');
    return status || '-';
};
</script>

<template>
    <Head :title="t('timetable')" />
    <MainLayout :title="t('timetablePageTitle')">
        <div class="mb-4 grid gap-2 rounded border bg-white p-4 md:grid-cols-2">
            <select class="rounded border px-2 py-1" @change="filterYear">
                <option value="">{{ t('allYears') }}</option>
                <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
            </select>
            <select class="rounded border px-2 py-1" :value="selectedTournament?.id" @change="selectTournament">
                <option v-for="tournament in tournaments" :key="tournament.id" :value="tournament.id">
                    {{ tournament.name }} ({{ tournament.year }})
                </option>
            </select>
        </div>

        <div class="mb-4 flex flex-wrap gap-2 rounded border bg-white p-3">
            <button
                v-for="tab in sectionTabs"
                :key="tab.key"
                class="rounded border px-3 py-1 text-sm"
                :class="(selectedSection || 'upcoming') === tab.key ? 'border-gray-900 bg-gray-900 text-white' : 'bg-white'"
                @click="selectSection(tab.key)"
            >
                {{ t(tab.labelKey) }} ({{ sectionRoundCounts?.[tab.key] ?? 0 }})
            </button>
        </div>

        <div v-if="selectedTournament && selectedRounds?.length" class="space-y-3">
            <div v-for="round in selectedRounds" :key="round.id" class="overflow-auto rounded border bg-white">
                <div class="flex items-center justify-between border-b bg-gray-50 px-3 py-2">
                    <div class="text-lg font-semibold">{{ round.name }}</div>
                    <span
                        v-if="round.hide_public_scores"
                        class="rounded border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800"
                    >
                        {{ t('scoresHidden') }}
                    </span>
                </div>
                <table class="min-w-full table-fixed text-sm">
                    <colgroup>
                        <col class="w-28" />
                        <col class="w-44" />
                        <col class="w-48" />
                        <col />
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="border px-2 py-1 text-left">{{ t('roundStatus') }}</th>
                            <th class="border px-2 py-1 text-left">{{ t('roundPhase') }}</th>
                            <th class="border px-2 py-1 text-left">{{ t('scheduled') }}</th>
                            <th class="border px-2 py-1 text-left">{{ t('scores') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="border px-2 py-1">
                                <span class="rounded border px-2 py-0.5" :class="statusBadgeClass(round.status)">
                                    {{ statusLabel(round.status) }}
                                </span>
                            </td>
                            <td class="border px-2 py-1">{{ phaseLabel(round.phase) }}</td>
                            <td class="border px-2 py-1">{{ formatScheduledAt(round.scheduled_start_at) }}</td>
                            <td class="border px-2 py-1">
                                <div class="grid gap-2 md:grid-cols-3">
                                    <div
                                        v-for="participant in round.participants"
                                        :key="`${participant.id}-score`"
                                        class="rounded border bg-gray-50 px-3 py-2"
                                    >
                                        <div class="truncate text-xs font-medium uppercase tracking-wide text-gray-500">
                                            {{ participant.display_name_snapshot || `${t('team')} ${participant.slot}` }}
                                        </div>
                                        <div class="mt-1 text-3xl font-bold leading-none text-gray-900">
                                            {{ scoreFor(round, participant.slot) }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div v-else-if="selectedTournament" class="rounded border bg-white p-4 text-sm text-gray-600">
            {{ t('noRoundsInSection') }}
        </div>
    </MainLayout>
</template>
