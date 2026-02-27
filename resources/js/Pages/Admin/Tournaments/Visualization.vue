<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { statusBadgeClass } from '@/composables/useStatusBadge';

const props = defineProps({
    tournament: Object,
    groupSummaries: Array,
    rules: Array,
    standaloneLinkedRounds: Array,
    unlinkedRounds: Array,
});

const { t } = useI18n();

const statusLabel = (status) => {
    if (status === 'draft') return t('statusDraft');
    if (status === 'live') return t('statusLive');
    if (status === 'completed') return t('statusCompleted');
    return status;
};

const sourceTypeLabel = (sourceType) => {
    return sourceType === 'group' ? t('groupBased') : t('roundBased');
};

const actionLabel = (rule) => {
    if (rule.action_type === 'eliminate') {
        return t('eliminate');
    }

    const bonus = Number(rule.bonus_score || 0);
    const bonusSuffix = bonus !== 0 ? ` (${t('bonus')} ${bonus > 0 ? '+' : ''}${bonus})` : '';
    return `${t('advanceToAnotherRoundSlot')}: ${rule.target_label} / ${t('slot')} ${rule.target_slot}${bonusSuffix}`;
};

const formatSchedule = (value) => {
    if (!value) {
        return t('noSchedule');
    }
    if (typeof value === 'string' && value.includes('T')) {
        return value.slice(0, 19).replace('T', ' ');
    }

    return String(value);
};
</script>

<template>
    <Head :title="t('visualizeTournament')" />
    <MainLayout :title="t('visualizeTournament')">
        <div class="mb-4 flex items-center justify-between rounded border bg-white p-4">
            <div>
                <div class="text-lg font-semibold">{{ tournament.name }} ({{ tournament.year }})</div>
                <div class="mt-1 text-sm">
                    <span class="rounded border px-2 py-0.5" :class="statusBadgeClass(tournament.status)">
                        {{ statusLabel(tournament.status) }}
                    </span>
                </div>
            </div>
            <Link :href="route('admin.tournaments.show', tournament.id)" class="rounded border px-3 py-1 text-sm">
                {{ t('backToTournamentSettings') }}
            </Link>
        </div>

        <div class="space-y-4">
            <div class="rounded border bg-white p-4">
                <h2 class="mb-2 font-semibold">{{ t('tournamentFlow') }}</h2>
                <p class="mb-3 text-sm text-gray-600">{{ t('tournamentFlowDescription') }}</p>
                <div v-if="rules.length === 0" class="rounded border bg-gray-50 p-3 text-sm text-gray-600">
                    {{ t('noAdvancementRulesYet') }}
                </div>
                <div v-else class="overflow-auto rounded border">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="border px-2 py-1 text-left">{{ t('source') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('rank') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('action') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('priority') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('active') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="rule in rules" :key="rule.id">
                                <td class="border px-2 py-1">{{ sourceTypeLabel(rule.source_type) }}: {{ rule.source_label }}</td>
                                <td class="border px-2 py-1">{{ rule.source_rank }}</td>
                                <td class="border px-2 py-1">{{ actionLabel(rule) }}</td>
                                <td class="border px-2 py-1">{{ rule.priority }}</td>
                                <td class="border px-2 py-1">{{ rule.is_active ? t('yes') : t('no') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded border bg-white p-4">
                <h2 class="mb-2 font-semibold">{{ t('groupClusters') }}</h2>
                <div v-if="groupSummaries.length === 0" class="rounded border bg-gray-50 p-3 text-sm text-gray-600">
                    {{ t('noGroupsCreatedYet') }}
                </div>
                <div v-else class="space-y-3">
                    <div v-for="group in groupSummaries" :key="`group-${group.id}`" class="rounded border p-3">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <div class="font-medium">{{ group.name }}</div>
                            <span class="rounded border px-2 py-0.5 text-xs">
                                {{ t('roundsInGroup') }}: {{ group.round_count }}
                            </span>
                            <span class="rounded border px-2 py-0.5 text-xs">
                                {{ t('completedRounds') }}: {{ group.completed_round_count }}
                            </span>
                        </div>
                        <div class="mb-2 overflow-auto rounded border">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="border px-2 py-1 text-left">{{ t('round') }}</th>
                                        <th class="border px-2 py-1 text-left">{{ t('status') }}</th>
                                        <th class="border px-2 py-1 text-left">{{ t('phase') }}</th>
                                        <th class="border px-2 py-1 text-left">{{ t('scheduled') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="round in group.rounds" :key="`group-round-${round.id}`">
                                        <td class="border px-2 py-1">{{ round.name }}</td>
                                        <td class="border px-2 py-1">{{ statusLabel(round.status) }}</td>
                                        <td class="border px-2 py-1">{{ round.phase }}</td>
                                        <td class="border px-2 py-1">{{ formatSchedule(round.scheduled_start_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="overflow-auto rounded border">
                            <table class="min-w-full table-fixed text-sm">
                                <colgroup>
                                    <col class="w-20" />
                                    <col />
                                    <col class="w-28" />
                                </colgroup>
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="border px-2 py-1 text-left">{{ t('rank') }}</th>
                                        <th class="border px-2 py-1 text-left">{{ t('team') }}</th>
                                        <th class="border px-2 py-1 text-left">{{ t('totalScore') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, index) in group.standings" :key="`standing-${group.id}-${row.team_id}`">
                                        <td class="border px-2 py-1">{{ index + 1 }}</td>
                                        <td class="border px-2 py-1">{{ row.name }}</td>
                                        <td class="border px-2 py-1">{{ row.score }}</td>
                                    </tr>
                                    <tr v-if="group.standings.length === 0">
                                        <td class="border px-2 py-1 text-gray-500" colspan="3">{{ t('noTeamScoresYet') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded border bg-white p-4">
                <h2 class="mb-2 font-semibold">{{ t('standaloneBracketRounds') }}</h2>
                <p class="mb-3 text-sm text-gray-600">{{ t('standaloneBracketRoundsDescription') }}</p>
                <div v-if="standaloneLinkedRounds.length === 0" class="rounded border bg-gray-50 p-3 text-sm text-gray-600">
                    {{ t('noStandaloneBracketRounds') }}
                </div>
                <div v-else class="space-y-2">
                    <div v-for="round in standaloneLinkedRounds" :key="`standalone-${round.id}`" class="rounded border p-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="font-medium">{{ round.name }}</div>
                            <span class="rounded border px-2 py-0.5 text-xs" :class="statusBadgeClass(round.status)">{{ statusLabel(round.status) }}</span>
                            <span class="rounded border px-2 py-0.5 text-xs">{{ t('phase') }}: {{ round.phase }}</span>
                            <span class="rounded border px-2 py-0.5 text-xs">{{ t('scheduled') }}: {{ formatSchedule(round.scheduled_start_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded border bg-white p-4">
                <h2 class="mb-2 font-semibold">{{ t('friendlyOrUnlinkedRounds') }}</h2>
                <p class="mb-3 text-sm text-gray-600">{{ t('friendlyOrUnlinkedRoundsDescription') }}</p>
                <div v-if="unlinkedRounds.length === 0" class="rounded border bg-gray-50 p-3 text-sm text-gray-600">
                    {{ t('noUnlinkedRounds') }}
                </div>
                <div v-else class="overflow-auto rounded border">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="border px-2 py-1 text-left">{{ t('round') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('status') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('phase') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('scheduled') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="round in unlinkedRounds" :key="`unlinked-${round.id}`">
                                <td class="border px-2 py-1">{{ round.name }}</td>
                                <td class="border px-2 py-1">{{ statusLabel(round.status) }}</td>
                                <td class="border px-2 py-1">{{ round.phase }}</td>
                                <td class="border px-2 py-1">{{ formatSchedule(round.scheduled_start_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
