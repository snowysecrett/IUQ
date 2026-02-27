<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { statusBadgeClass } from '@/composables/useStatusBadge';
import { computed } from 'vue';

const props = defineProps({
    tournament: Object,
    rules: Array,
    standaloneLinkedRounds: Array,
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

const nodeTypeLabel = (type) => {
    if (type === 'group') return t('group');
    if (type === 'round') return t('round');
    if (type === 'eliminated') return t('eliminate');
    return type;
};

const nodeCardClass = (type) => {
    if (type === 'group') {
        return 'border-amber-300 bg-amber-50';
    }
    if (type === 'eliminated') {
        return 'border-rose-300 bg-rose-50';
    }
    return 'border-sky-300 bg-sky-50';
};

const nodeTypeTextClass = (type) => {
    if (type === 'group') {
        return 'text-amber-700';
    }
    if (type === 'eliminated') {
        return 'text-rose-700';
    }
    return 'text-sky-700';
};

const tournamentTree = computed(() => {
    const nodes = new Map();
    const edges = [];

    const addNode = (key, payload) => {
        if (!key) return;
        if (!nodes.has(key)) {
            nodes.set(key, {
                key,
                depth: 0,
                ...payload,
            });
            return;
        }

        const existing = nodes.get(key);
        nodes.set(key, {
            ...existing,
            ...payload,
        });
    };

    (props.standaloneLinkedRounds || []).forEach((round) => {
        addNode(`round-${round.id}`, {
            id: round.id,
            type: 'round',
            label: round.name,
        });
    });

    (props.rules || []).forEach((rule) => {
        const fromKey = rule.source_type === 'group'
            ? (rule.source_group_id ? `group-${rule.source_group_id}` : null)
            : (rule.source_round_id ? `round-${rule.source_round_id}` : null);

        if (fromKey && !nodes.has(fromKey)) {
            addNode(fromKey, {
                type: rule.source_type === 'group' ? 'group' : 'round',
                label: rule.source_label || '-',
            });
        }

        const toKey = rule.action_type === 'advance'
            ? (rule.target_round_id ? `round-${rule.target_round_id}` : null)
            : 'eliminated';

        if (toKey === 'eliminated') {
            addNode('eliminated', {
                type: 'eliminated',
                label: t('eliminatedNode'),
            });
        } else if (toKey && !nodes.has(toKey)) {
            addNode(toKey, {
                type: 'round',
                label: rule.target_label || '-',
            });
        }

        if (fromKey && toKey) {
            edges.push({
                from: fromKey,
                to: toKey,
                sourceRank: rule.source_rank,
                actionType: rule.action_type,
                targetSlot: rule.target_slot,
                bonusScore: Number(rule.bonus_score || 0),
                active: !!rule.is_active,
            });
        }
    });

    const nodeCount = nodes.size;
    for (let i = 0; i < nodeCount; i++) {
        let changed = false;
        edges.forEach((edge) => {
            const from = nodes.get(edge.from);
            const to = nodes.get(edge.to);
            if (!from || !to) return;
            const nextDepth = from.depth + 1;
            if (nextDepth > to.depth) {
                to.depth = nextDepth;
                nodes.set(edge.to, to);
                changed = true;
            }
        });
        if (!changed) break;
    }

    const edgeText = (edge) => {
        if (edge.actionType === 'eliminate') {
            return `${t('rank')} ${edge.sourceRank}: ${t('eliminate')}`;
        }

        const bonusText = edge.bonusScore !== 0 ? ` (${t('bonus')} ${edge.bonusScore > 0 ? '+' : ''}${edge.bonusScore})` : '';
        return `${t('rank')} ${edge.sourceRank} -> ${t('slot')} ${edge.targetSlot}${bonusText}`;
    };

    const grouped = new Map();
    nodes.forEach((node) => {
        if (!grouped.has(node.depth)) grouped.set(node.depth, []);

        const outgoing = edges
            .filter((edge) => edge.from === node.key)
            .map((edge) => ({
                text: edgeText(edge),
                toLabel: nodes.get(edge.to)?.label || '-',
                active: edge.active,
            }));

        grouped.get(node.depth).push({
            ...node,
            typeLabel: nodeTypeLabel(node.type),
            outgoing,
        });
    });

    const columns = Array.from(grouped.entries())
        .sort(([a], [b]) => a - b)
        .map(([depth, list]) => ({
            depth,
            nodes: list.sort((a, b) => a.label.localeCompare(b.label)),
        }));

    return {
        columns,
    };
});
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
                <h2 class="mb-2 font-semibold">{{ t('tournamentTree') }}</h2>
                <p class="mb-3 text-sm text-gray-600">{{ t('tournamentTreeDescription') }}</p>
                <div v-if="tournamentTree.columns.length === 0" class="rounded border bg-gray-50 p-3 text-sm text-gray-600">
                    {{ t('noAdvancementRulesYet') }}
                </div>
                <div v-else class="overflow-x-auto">
                    <div class="flex min-w-max gap-3">
                        <div
                            v-for="(column, columnIndex) in tournamentTree.columns"
                            :key="`stage-${column.depth}`"
                            class="relative w-72 rounded border bg-gray-50 p-2"
                        >
                            <div
                                v-if="columnIndex < tournamentTree.columns.length - 1"
                                class="pointer-events-none absolute -right-3 top-1/2 h-0 w-3 border-t border-dashed border-gray-300"
                            />
                            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-600">
                                {{ t('stageLabel') }} {{ column.depth + 1 }}
                            </div>
                            <div class="space-y-2">
                                <div v-for="node in column.nodes" :key="node.key" class="rounded border p-2" :class="nodeCardClass(node.type)">
                                    <div class="text-sm font-semibold">{{ node.label }}</div>
                                    <div class="text-xs font-medium" :class="nodeTypeTextClass(node.type)">{{ node.typeLabel }}</div>
                                    <div v-if="node.outgoing.length" class="mt-2 space-y-1 text-xs text-gray-600">
                                        <div
                                            v-for="(out, idx) in node.outgoing"
                                            :key="`${node.key}-out-${idx}`"
                                            :class="out.active ? '' : 'opacity-50'"
                                        >
                                            {{ t('flowTo') }} {{ out.toLabel }} ({{ out.text }})
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded border bg-white p-4">
                <h2 class="mb-2 font-semibold">{{ t('treeLegend') }}</h2>
                <div class="grid gap-2 text-sm md:grid-cols-2">
                    <div class="rounded border bg-gray-50 px-3 py-2">
                        <span class="font-semibold">{{ t('group') }}</span>: {{ t('treeLegendGroup') }}
                    </div>
                    <div class="rounded border bg-gray-50 px-3 py-2">
                        <span class="font-semibold">{{ t('round') }}</span>: {{ t('treeLegendRound') }}
                    </div>
                    <div class="rounded border bg-gray-50 px-3 py-2">
                        <span class="font-semibold">{{ t('eliminate') }}</span>: {{ t('treeLegendEliminated') }}
                    </div>
                    <div class="rounded border bg-gray-50 px-3 py-2">
                        <span class="font-semibold">{{ t('active') }}/{{ t('inactiveOnly') }}</span>: {{ t('treeLegendActive') }}
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
