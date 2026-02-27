<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { statusBadgeClass } from '@/composables/useStatusBadge';
import { useI18n } from '@/composables/useI18n';

const props = defineProps({
    tournament: Object,
    allTeams: Array,
    groupSummaries: Array,
});

const page = usePage();
const { t } = useI18n();
const isSuperAdmin = computed(() => page.props.auth?.user?.role === 'super_admin');
const isUsingRoundTemplate = computed(() => !!roundForm.round_template_id);
const roundCreateSuccessMessage = ref('');
const roundExpandedState = ref({});
const advancementRulesExpanded = ref(false);
const advancementRuleFilters = ref({
    source_type: '',
    action_type: '',
    active_state: '',
});

const isTemplateModalOpen = ref(false);

const tournamentForm = useForm({
    name: props.tournament.name,
    year: props.tournament.year,
    status: props.tournament.status,
    scheduled_start_at: props.tournament.scheduled_start_at ? props.tournament.scheduled_start_at.slice(0, 16) : '',
    timezone: props.tournament.timezone,
    logo_path: props.tournament.logo_path || '',
    logo_file: null,
});

const addTeamForm = useForm({ team_id: '' });
const DEFAULT_LIGHTNING_DELTAS_TEXT = '20';
const DEFAULT_BUZZER_NORMAL_DELTAS_TEXT = '20,10,-10';
const DEFAULT_BUZZER_FEVER_DELTAS_TEXT = '30,15,-15';
const DEFAULT_BUZZER_ULTIMATE_DELTAS_TEXT = '40,20,-20';

const templateForm = useForm({
    name: '',
    code: '',
    teams_per_round: 3,
    default_score: 100,
    has_fever: false,
    has_ultimate_fever: false,
    sort_order: 0,
    default_lightning_score_deltas_text: DEFAULT_LIGHTNING_DELTAS_TEXT,
    default_buzzer_normal_score_deltas_text: DEFAULT_BUZZER_NORMAL_DELTAS_TEXT,
    default_buzzer_fever_score_deltas_text: DEFAULT_BUZZER_FEVER_DELTAS_TEXT,
    default_buzzer_ultimate_score_deltas_text: DEFAULT_BUZZER_ULTIMATE_DELTAS_TEXT,
});
const groupForm = useForm({
    name: '',
    code: '',
    sort_order: 0,
});
const roundForm = useForm({
    name: '',
    code: '',
    round_template_id: '',
    group_id: '',
    teams_per_round: 3,
    default_score: 100,
    has_fever: false,
    has_ultimate_fever: false,
    hide_public_scores: false,
    scheduled_start_at: '',
    sort_order: 0,
    lightning_score_deltas_text: DEFAULT_LIGHTNING_DELTAS_TEXT,
    buzzer_normal_score_deltas_text: DEFAULT_BUZZER_NORMAL_DELTAS_TEXT,
    buzzer_fever_score_deltas_text: DEFAULT_BUZZER_FEVER_DELTAS_TEXT,
    buzzer_ultimate_score_deltas_text: DEFAULT_BUZZER_ULTIMATE_DELTAS_TEXT,
});
const ruleForm = useForm({
    source_type: 'group',
    source_group_id: '',
    source_round_id: '',
    source_rank: 1,
    action_type: 'advance',
    target_round_id: '',
    target_slot: '',
    bonus_score: 0,
    priority: 0,
    is_active: true,
});

const parseDeltas = (text) =>
    text
        .split(',')
        .map((item) => parseInt(item.trim(), 10))
        .filter((item) => !Number.isNaN(item));

const formatDeltas = (values, fallback) => (
    Array.isArray(values) && values.length > 0 ? values.join(',') : fallback
);

const initRoundUiState = (round) => {
    if (round._lightning_score_deltas_text === undefined) {
        round._lightning_score_deltas_text = formatDeltas(
            round.lightning_score_deltas || round.score_deltas,
            DEFAULT_LIGHTNING_DELTAS_TEXT
        );
    }
    if (round._buzzer_normal_score_deltas_text === undefined) {
        round._buzzer_normal_score_deltas_text = formatDeltas(
            round.buzzer_normal_score_deltas || round.score_deltas,
            DEFAULT_BUZZER_NORMAL_DELTAS_TEXT
        );
    }
    if (round._buzzer_fever_score_deltas_text === undefined) {
        round._buzzer_fever_score_deltas_text = formatDeltas(
            round.buzzer_fever_score_deltas || round.score_deltas,
            DEFAULT_BUZZER_FEVER_DELTAS_TEXT
        );
    }
    if (round._buzzer_ultimate_score_deltas_text === undefined) {
        round._buzzer_ultimate_score_deltas_text = formatDeltas(
            round.buzzer_ultimate_score_deltas || round.score_deltas,
            DEFAULT_BUZZER_ULTIMATE_DELTAS_TEXT
        );
    }
    if (round.has_fever === undefined || round.has_fever === null) {
        round.has_fever = false;
    }
    if (round.has_ultimate_fever === undefined || round.has_ultimate_fever === null) {
        round.has_ultimate_fever = false;
    }
    if (round.hide_public_scores === undefined || round.hide_public_scores === null) {
        round.hide_public_scores = false;
    }
    if (round.phase === 'buzzer') {
        round.phase = 'buzzer_normal';
    }

    if (round._scheduled_start_at_local === undefined) {
        round._scheduled_start_at_local = round.scheduled_start_at ? round.scheduled_start_at.slice(0, 16) : '';
    }

    if (round._default_score === undefined) {
        round._default_score = round.default_score ?? 100;
    }
};

props.tournament.rounds.forEach(initRoundUiState);

props.tournament.rounds.forEach((round) => {
    if (roundExpandedState.value[round.id] === undefined) {
        roundExpandedState.value[round.id] = false;
    }
});

const isRoundExpanded = (roundId) => !!roundExpandedState.value[roundId];
const toggleRoundExpanded = (roundId) => {
    roundExpandedState.value[roundId] = !roundExpandedState.value[roundId];
};

const filteredAdvancementRules = computed(() => {
    return (props.tournament.advancement_rules || []).filter((rule) => {
        if (advancementRuleFilters.value.source_type && rule.source_type !== advancementRuleFilters.value.source_type) {
            return false;
        }
        if (advancementRuleFilters.value.action_type && rule.action_type !== advancementRuleFilters.value.action_type) {
            return false;
        }
        if (advancementRuleFilters.value.active_state === 'active' && !rule.is_active) {
            return false;
        }
        if (advancementRuleFilters.value.active_state === 'inactive' && rule.is_active) {
            return false;
        }

        return true;
    });
});

const updateTournament = () => {
    tournamentForm.patch(route('admin.tournaments.update', props.tournament.id), {
        forceFormData: true,
    });
};

const addTeam = () => {
    addTeamForm.post(route('admin.tournaments.teams.add', props.tournament.id));
};

const removeTeam = (teamId) => {
    router.delete(route('admin.tournaments.teams.remove', [props.tournament.id, teamId]));
};

const createTemplate = () => {
    templateForm.transform((data) => ({
        ...data,
        default_score: data.default_score === '' || data.default_score === null ? 100 : Number(data.default_score),
        has_fever: !!data.has_fever || !!data.has_ultimate_fever,
        has_ultimate_fever: !!data.has_ultimate_fever,
        default_lightning_score_deltas: parseDeltas(data.default_lightning_score_deltas_text),
        default_buzzer_normal_score_deltas: parseDeltas(data.default_buzzer_normal_score_deltas_text),
        default_buzzer_fever_score_deltas: (data.has_fever || data.has_ultimate_fever)
            ? parseDeltas(data.default_buzzer_fever_score_deltas_text)
            : null,
        default_buzzer_ultimate_score_deltas: data.has_ultimate_fever
            ? parseDeltas(data.default_buzzer_ultimate_score_deltas_text)
            : null,
        default_score_deltas: parseDeltas(data.default_buzzer_normal_score_deltas_text),
    })).post(route('admin.round-templates.store', props.tournament.id), {
        onSuccess: () => templateForm.reset(
            'name',
            'code',
            'sort_order',
            'default_score',
            'has_fever',
            'has_ultimate_fever',
            'default_lightning_score_deltas_text',
            'default_buzzer_normal_score_deltas_text',
            'default_buzzer_fever_score_deltas_text',
            'default_buzzer_ultimate_score_deltas_text',
        ),
    });
};

const deleteTemplate = (templateId) => {
    if (!confirm(t('deleteRoundTemplateConfirm'))) {
        return;
    }

    router.delete(route('admin.round-templates.destroy', templateId), {
        preserveScroll: true,
    });
};

const createGroup = () => {
    groupForm.post(route('admin.groups.store', props.tournament.id), {
        onSuccess: () => groupForm.reset('name', 'code', 'sort_order'),
    });
};

const deleteGroup = (groupId) => {
    if (!confirm(t('deleteGroupConfirm'))) {
        return;
    }

    router.delete(route('admin.groups.destroy', groupId), {
        preserveScroll: true,
    });
};

const createRound = () => {
    roundCreateSuccessMessage.value = '';
    roundForm.transform((data) => ({
        ...data,
        round_template_id: data.round_template_id || null,
        group_id: data.group_id || null,
        teams_per_round: data.round_template_id ? null : Number(data.teams_per_round),
        default_score: data.default_score === '' || data.default_score === null ? null : Number(data.default_score),
        hide_public_scores: !!data.hide_public_scores,
        has_fever: data.round_template_id ? null : (!!data.has_fever || !!data.has_ultimate_fever),
        has_ultimate_fever: data.round_template_id ? null : !!data.has_ultimate_fever,
        lightning_score_deltas: data.round_template_id ? null : parseDeltas(data.lightning_score_deltas_text),
        buzzer_normal_score_deltas: data.round_template_id ? null : parseDeltas(data.buzzer_normal_score_deltas_text),
        buzzer_fever_score_deltas: data.round_template_id
            ? null
            : ((data.has_fever || data.has_ultimate_fever)
                ? parseDeltas(data.buzzer_fever_score_deltas_text)
                : null),
        buzzer_ultimate_score_deltas: data.round_template_id
            ? null
            : (data.has_ultimate_fever
                ? parseDeltas(data.buzzer_ultimate_score_deltas_text)
                : null),
        score_deltas: data.round_template_id ? null : parseDeltas(data.buzzer_normal_score_deltas_text),
    })).post(route('admin.rounds.store', props.tournament.id), {
        onSuccess: () => {
            roundCreateSuccessMessage.value = t('roundCreatedSuccessfully');
            roundForm.reset(
                'name',
                'code',
                'round_template_id',
                'group_id',
                'scheduled_start_at',
                'sort_order',
                'default_score',
                'hide_public_scores',
                'has_fever',
                'has_ultimate_fever',
                'lightning_score_deltas_text',
                'buzzer_normal_score_deltas_text',
                'buzzer_fever_score_deltas_text',
                'buzzer_ultimate_score_deltas_text',
            );
        },
    });
};

const updateParticipants = (round) => {
    const participants = round.participants.map((participant) => ({
        slot: participant.slot,
        team_id: participant.team_id || null,
    }));

    router.post(route('admin.rounds.participants.update', round.id), { participants });
};

const saveRoundDetails = (round) => {
    router.patch(route('admin.rounds.update', round.id), {
        name: round.name,
        code: round.code || null,
        group_id: round.group_id || null,
        status: round.status,
        phase: round.phase,
        teams_per_round: round.teams_per_round,
        default_score: round._default_score === '' || round._default_score === null ? 100 : Number(round._default_score),
        hide_public_scores: !!round.hide_public_scores,
        scheduled_start_at: round._scheduled_start_at_local || null,
        sort_order: round.sort_order ?? 0,
        has_fever: !!round.has_fever || !!round.has_ultimate_fever,
        has_ultimate_fever: !!round.has_ultimate_fever,
        lightning_score_deltas: parseDeltas(round._lightning_score_deltas_text || DEFAULT_LIGHTNING_DELTAS_TEXT),
        buzzer_normal_score_deltas: parseDeltas(round._buzzer_normal_score_deltas_text || DEFAULT_BUZZER_NORMAL_DELTAS_TEXT),
        buzzer_fever_score_deltas: (round.has_fever || round.has_ultimate_fever)
            ? parseDeltas(round._buzzer_fever_score_deltas_text || DEFAULT_BUZZER_FEVER_DELTAS_TEXT)
            : null,
        buzzer_ultimate_score_deltas: round.has_ultimate_fever
            ? parseDeltas(round._buzzer_ultimate_score_deltas_text || DEFAULT_BUZZER_ULTIMATE_DELTAS_TEXT)
            : null,
        score_deltas: parseDeltas(round._buzzer_normal_score_deltas_text || DEFAULT_BUZZER_NORMAL_DELTAS_TEXT),
    }, {
        preserveScroll: true,
    });
};

const deleteRound = (round) => {
    if (!confirm(t('deleteRoundConfirm', { name: round.name }))) {
        return;
    }

    router.delete(route('admin.rounds.destroy', round.id), {
        preserveScroll: true,
    });
};

const createAdvancementRule = () => {
    ruleForm.transform((data) => ({
        ...data,
        source_group_id: data.source_type === 'group' ? (data.source_group_id || null) : null,
        source_round_id: data.source_type === 'round' ? (data.source_round_id || null) : null,
        target_round_id: data.action_type === 'advance' ? (data.target_round_id || null) : null,
        target_slot: data.action_type === 'advance' ? (data.target_slot ? Number(data.target_slot) : null) : null,
        bonus_score: data.action_type === 'advance' ? Number(data.bonus_score || 0) : 0,
        source_rank: Number(data.source_rank),
        priority: Number(data.priority || 0),
    })).post(route('admin.advancement-rules.store', props.tournament.id), {
        preserveScroll: true,
        onSuccess: () => {
            ruleForm.reset('source_group_id', 'source_round_id', 'source_rank', 'target_round_id', 'target_slot', 'bonus_score', 'priority');
            ruleForm.source_type = 'group';
            ruleForm.action_type = 'advance';
            ruleForm.source_rank = 1;
            ruleForm.bonus_score = 0;
            ruleForm.priority = 0;
            ruleForm.is_active = true;
        },
    });
};

const updateAdvancementRule = (rule, payload) => {
    router.patch(route('admin.advancement-rules.update', rule.id), payload, {
        preserveScroll: true,
    });
};

const deleteAdvancementRule = (rule) => {
    if (!confirm(t('deleteAdvancementRuleConfirm'))) {
        return;
    }

    router.delete(route('admin.advancement-rules.destroy', rule.id), {
        preserveScroll: true,
    });
};

const sourceTypeLabel = (rule) => rule.source_type === 'group' ? t('group') : t('round');
const sourceNameLabel = (rule) => rule.source_type === 'group'
    ? (rule.source_group?.name || t('unknownGroup'))
    : (rule.source_round?.name || t('unknownRound'));
const actionLabel = (rule) => rule.action_type === 'eliminate'
    ? t('eliminate')
    : `${t('advanceTo')} ${rule.target_round?.name || t('unknownRound')} / ${t('slot')} ${rule.target_slot ?? '-'}${Number(rule.bonus_score || 0) ? ` (${t('bonus')}: ${Number(rule.bonus_score) > 0 ? '+' : ''}${Number(rule.bonus_score)})` : ''}`;

const statusDisplayMap = {
    applied: { label: t('autoAdvanced'), class: 'border-green-200 bg-green-50 text-green-700' },
    bonus_applied: { label: t('bonusApplied'), class: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    blocked_manual: { label: t('blockedManualLock'), class: 'border-amber-200 bg-amber-50 text-amber-700' },
    blocked_round_state: { label: t('blockedRoundState'), class: 'border-orange-200 bg-orange-50 text-orange-700' },
    skipped: { label: t('skipped'), class: 'border-gray-200 bg-gray-50 text-gray-700' },
    eliminated: { label: t('eliminated'), class: 'border-red-200 bg-red-50 text-red-700' },
    stale_marked: { label: t('markedStale'), class: 'border-orange-200 bg-orange-50 text-orange-700' },
};

const formatLogTimestamp = (value) => {
    if (!value) {
        return '-';
    }

    // API timestamps are ISO-like; normalize to YYYY-MM-DD HH:mm:ss.
    if (typeof value === 'string' && value.includes('T')) {
        return value.slice(0, 19).replace('T', ' ');
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
};

const logStatusLabel = (status) => statusDisplayMap[status]?.label || status || '-';
const logStatusClass = (status) => statusDisplayMap[status]?.class || 'border-gray-200 bg-gray-50 text-gray-700';
const logTeamChange = (log) => `${log.before_team?.team_name || t('noTeam')} -> ${log.after_team?.team_name || t('noTeam')}`;
</script>

<template>
    <Head :title="tournament.name" />
    <MainLayout :title="tournament.name">
        <div class="mb-4 rounded border bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="text-sm text-gray-600">{{ t('visualizeTournamentDescription') }}</div>
                <Link :href="route('admin.tournaments.visualization', tournament.id)" class="rounded border px-3 py-1 text-sm">
                    {{ t('visualizeTournament') }}
                </Link>
            </div>
        </div>
        <div class="grid gap-4" :class="isSuperAdmin ? 'lg:grid-cols-2' : 'lg:grid-cols-1'">
            <form v-if="isSuperAdmin" @submit.prevent="updateTournament" class="rounded border bg-white p-4">
                <h2 class="mb-2 font-semibold">{{ t('tournamentSettings') }}</h2>
                <fieldset :disabled="!isSuperAdmin">
                    <div class="grid gap-2 md:grid-cols-2">
                        <input v-model="tournamentForm.name" class="rounded border px-2 py-1" />
                        <input v-model="tournamentForm.year" type="number" class="rounded border px-2 py-1" />
                        <select v-model="tournamentForm.status" class="rounded border px-2 py-1">
                            <option value="draft">{{ t('statusDraft') }}</option>
                            <option value="live">{{ t('statusLive') }}</option>
                            <option value="completed">{{ t('statusCompleted') }}</option>
                        </select>
                        <input v-model="tournamentForm.scheduled_start_at" type="datetime-local" class="rounded border px-2 py-1" />
                        <input v-model="tournamentForm.timezone" class="rounded border px-2 py-1" />
                        <input v-model="tournamentForm.logo_path" class="rounded border px-2 py-1" :placeholder="t('logoUrlPath')" />
                        <input
                            type="file"
                            accept="image/*"
                            class="rounded border px-2 py-1 md:col-span-2"
                            @input="tournamentForm.logo_file = $event.target.files[0]"
                        />
                        <div class="text-xs text-gray-500 md:col-span-2">
                            {{ t('uploadOverridesLogo') }}
                        </div>
                    </div>
                </fieldset>
                <button class="mt-3 rounded border bg-gray-900 px-3 py-1 text-white">{{ t('saveTournament') }}</button>
            </form>

            <div class="rounded border bg-white p-4">
                <h2 class="mb-2 font-semibold">{{ t('tournamentTeamsMax') }}</h2>
                <form v-if="isSuperAdmin" @submit.prevent="addTeam" class="mb-2 flex gap-2">
                    <select v-model="addTeamForm.team_id" class="min-w-0 flex-1 rounded border px-2 py-1" required>
                        <option disabled value="">{{ t('selectTeam') }}</option>
                        <option v-for="team in allTeams" :key="team.id" :value="team.id">
                            {{ team.university_name }} - {{ team.team_name }}
                        </option>
                    </select>
                    <button class="rounded border px-3 py-1">{{ t('add') }}</button>
                </form>
                <div v-else class="mb-2 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    {{ t('tournamentTeamsManageSuperadminOnly') }}
                </div>
                <div class="max-h-48 overflow-auto rounded border">
                    <table class="min-w-full text-sm">
                        <tr v-for="entry in tournament.tournament_teams" :key="entry.id">
                            <td class="border px-2 py-1">{{ entry.display_name_snapshot }}</td>
                            <td class="border px-2 py-1 text-right">
                                <button v-if="isSuperAdmin" class="rounded border px-2 py-0.5" @click="removeTeam(entry.team_id)">{{ t('remove') }}</button>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="isSuperAdmin" class="mt-4 grid gap-4 lg:grid-cols-2">
            <form @submit.prevent="createGroup" class="rounded border bg-white p-4">
                <h2 class="mb-2 font-semibold">{{ t('groups') }}</h2>
                <p class="mb-3 text-sm text-gray-600">
                    {{ t('groupsDescription') }}
                </p>
                <fieldset :disabled="!isSuperAdmin">
                    <div class="grid gap-3 md:grid-cols-3">
                        <label class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">{{ t('groupName') }}</div>
                            <input v-model="groupForm.name" class="w-full rounded border px-2 py-1" :placeholder="t('exampleGroupA')" required />
                        </label>
                        <label class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">{{ t('groupCodeOptional') }}</div>
                            <input v-model="groupForm.code" class="w-full rounded border px-2 py-1" :placeholder="t('exampleGA')" />
                        </label>
                        <label class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">{{ t('displayOrder') }}</div>
                            <input v-model="groupForm.sort_order" type="number" class="w-full rounded border px-2 py-1" />
                        </label>
                    </div>
                </fieldset>
                <button class="mt-3 rounded border bg-gray-900 px-3 py-1 text-white">{{ t('createGroup') }}</button>
            </form>

            <form @submit.prevent="createTemplate" class="rounded border bg-white p-4">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h2 class="font-semibold">{{ t('roundTemplateEditor') }}</h2>
                    <button
                        type="button"
                        class="rounded border px-3 py-1 text-sm"
                        @click="isTemplateModalOpen = true"
                    >
                        {{ t('viewCreatedTemplates') }} ({{ tournament.round_templates.length }})
                    </button>
                </div>
                <p class="mb-3 text-sm text-gray-600">
                    {{ t('templateDescriptionPrefix') }} <strong>3</strong>, {{ t('templateDescriptionSuffix') }}
                    <strong>{{ t('lightningRound').toLowerCase() }}: 20, {{ t('buzzerNormalRound').toLowerCase() }}: 20,10,-10</strong>.
                </p>
                <fieldset :disabled="!isSuperAdmin">
                    <div class="grid gap-3 md:grid-cols-2">
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('templateName') }}</div>
                        <input v-model="templateForm.name" class="w-full rounded border px-2 py-1" :placeholder="t('examplePrelimGroupMatch')" required />
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('templateCodeOptional') }}</div>
                        <input v-model="templateForm.code" class="w-full rounded border px-2 py-1" :placeholder="t('examplePrelim3t')" />
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('teamsPerRound') }}</div>
                        <input v-model="templateForm.teams_per_round" type="number" min="2" max="8" class="w-full rounded border px-2 py-1" />
                        <div class="mt-1 text-xs text-gray-500">{{ t('teamsPerRoundHint') }}</div>
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('defaultScore') }}</div>
                        <input v-model="templateForm.default_score" type="number" min="0" class="w-full rounded border px-2 py-1" />
                        <div class="mt-1 text-xs text-gray-500">{{ t('defaultScoreHint') }}</div>
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('displayOrder') }}</div>
                        <input v-model="templateForm.sort_order" type="number" class="w-full rounded border px-2 py-1" />
                        <div class="mt-1 text-xs text-gray-500">{{ t('displayOrderHint') }}</div>
                    </label>
                    <label class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('buzzerModes') }}</div>
                        <div class="flex flex-wrap gap-4 rounded border px-3 py-2 text-sm">
                            <label class="inline-flex items-center gap-2">
                                <input v-model="templateForm.has_fever" type="checkbox" />
                                <span>{{ t('enableFever') }}</span>
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input v-model="templateForm.has_ultimate_fever" type="checkbox" />
                                <span>{{ t('enableUltimateFever') }}</span>
                            </label>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">{{ t('ultimateImpliesFever') }}</div>
                    </label>
                    <label class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('lightningScoreDeltas') }}</div>
                        <input
                            v-model="templateForm.default_lightning_score_deltas_text"
                            class="w-full rounded border px-2 py-1"
                            placeholder="20"
                        />
                    </label>
                    <label class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('buzzerNormalScoreDeltas') }}</div>
                        <input
                            v-model="templateForm.default_buzzer_normal_score_deltas_text"
                            class="w-full rounded border px-2 py-1"
                            placeholder="20,10,-10"
                        />
                    </label>
                    <label v-if="templateForm.has_fever || templateForm.has_ultimate_fever" class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('buzzerFeverScoreDeltas') }}</div>
                        <input
                            v-model="templateForm.default_buzzer_fever_score_deltas_text"
                            class="w-full rounded border px-2 py-1"
                            placeholder="30,15,-15"
                        />
                    </label>
                    <label v-if="templateForm.has_ultimate_fever" class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('buzzerUltimateScoreDeltas') }}</div>
                        <input
                            v-model="templateForm.default_buzzer_ultimate_score_deltas_text"
                            class="w-full rounded border px-2 py-1"
                            placeholder="40,20,-20"
                        />
                    </label>
                    </div>
                </fieldset>
                <button class="mt-3 rounded border bg-gray-900 px-3 py-1 text-white">{{ t('createTemplate') }}</button>
            </form>

            <form @submit.prevent="createRound" class="rounded border bg-white p-4">
                <h2 class="mb-2 font-semibold">{{ t('createRound') }}</h2>
                <div
                    v-if="roundCreateSuccessMessage"
                    class="mb-3 rounded border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-700"
                >
                    {{ roundCreateSuccessMessage }}
                </div>
                <p class="mb-3 text-sm text-gray-600">
                    {{ t('createRoundDescription') }}
                </p>
                <fieldset :disabled="!isSuperAdmin">
                    <div class="grid gap-3 md:grid-cols-2">
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('roundName') }}</div>
                        <input v-model="roundForm.name" class="w-full rounded border px-2 py-1" :placeholder="t('examplePrelimA1')" required />
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('roundCodeOptional') }}</div>
                        <input v-model="roundForm.code" class="w-full rounded border px-2 py-1" :placeholder="t('examplePA1')" />
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('useTemplateOptional') }}</div>
                        <select v-model="roundForm.round_template_id" class="w-full rounded border px-2 py-1">
                            <option value="">{{ t('noTemplate') }}</option>
                            <option v-for="template in tournament.round_templates" :key="template.id" :value="template.id">
                                {{ template.name }}
                            </option>
                        </select>
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('groupOptional') }}</div>
                        <select v-model="roundForm.group_id" class="w-full min-w-56 rounded border px-2 py-1">
                            <option value="">{{ t('noGroup') }}</option>
                            <option v-for="group in tournament.groups" :key="group.id" :value="group.id">
                                {{ group.name }}
                            </option>
                        </select>
                    </label>
                    <label v-if="!isUsingRoundTemplate" class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('teamsPerRound') }}</div>
                        <input v-model="roundForm.teams_per_round" type="number" min="2" max="8" class="w-full rounded border px-2 py-1" />
                        <div class="mt-1 text-xs text-gray-500">{{ t('defaultTeamsPerRoundHint') }}</div>
                    </label>
                    <label v-if="!isUsingRoundTemplate" class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('defaultScore') }}</div>
                        <input v-model="roundForm.default_score" type="number" min="0" class="w-full rounded border px-2 py-1" />
                        <div class="mt-1 text-xs text-gray-500">{{ t('defaultRoundScoreHint') }}</div>
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('scheduledStartTime') }}</div>
                        <input v-model="roundForm.scheduled_start_at" type="datetime-local" class="w-full rounded border px-2 py-1" />
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('displayOrder') }}</div>
                        <input v-model="roundForm.sort_order" type="number" class="w-full rounded border px-2 py-1" />
                        <div class="mt-1 text-xs text-gray-500">{{ t('displayOrderRoundHint') }}</div>
                    </label>
                    <div v-if="isUsingRoundTemplate" class="rounded border bg-gray-50 px-3 py-2 text-sm text-gray-600 md:col-span-2">
                        {{ t('templateSelectedModeHint') }}
                    </div>
                    <label v-if="!isUsingRoundTemplate" class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('buzzerModes') }}</div>
                        <div class="flex flex-wrap gap-4 rounded border px-3 py-2 text-sm">
                            <label class="inline-flex items-center gap-2">
                                <input v-model="roundForm.has_fever" type="checkbox" />
                                <span>{{ t('enableFever') }}</span>
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input v-model="roundForm.has_ultimate_fever" type="checkbox" />
                                <span>{{ t('enableUltimateFever') }}</span>
                            </label>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">{{ t('ultimateImpliesFever') }}</div>
                    </label>
                    <label class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('publicScoreVisibility') }}</div>
                        <div class="flex flex-wrap gap-4 rounded border px-3 py-2 text-sm">
                            <label class="inline-flex items-center gap-2">
                                <input v-model="roundForm.hide_public_scores" type="checkbox" />
                                <span>{{ t('hideScoresOnPublic') }}</span>
                            </label>
                        </div>
                    </label>
                    <label v-if="!isUsingRoundTemplate" class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('lightningScoreDeltas') }}</div>
                        <input
                            v-model="roundForm.lightning_score_deltas_text"
                            class="w-full rounded border px-2 py-1"
                            placeholder="20"
                        />
                    </label>
                    <label v-if="!isUsingRoundTemplate" class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('buzzerNormalScoreDeltas') }}</div>
                        <input
                            v-model="roundForm.buzzer_normal_score_deltas_text"
                            class="w-full rounded border px-2 py-1"
                            placeholder="20,10,-10"
                        />
                    </label>
                    <label v-if="!isUsingRoundTemplate && (roundForm.has_fever || roundForm.has_ultimate_fever)" class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('buzzerFeverScoreDeltas') }}</div>
                        <input
                            v-model="roundForm.buzzer_fever_score_deltas_text"
                            class="w-full rounded border px-2 py-1"
                            placeholder="30,15,-15"
                        />
                    </label>
                    <label v-if="!isUsingRoundTemplate && roundForm.has_ultimate_fever" class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ t('buzzerUltimateScoreDeltas') }}</div>
                        <input
                            v-model="roundForm.buzzer_ultimate_score_deltas_text"
                            class="w-full rounded border px-2 py-1"
                            placeholder="40,20,-20"
                        />
                    </label>
                    </div>
                </fieldset>
                <button class="mt-3 rounded border bg-gray-900 px-3 py-1 text-white">{{ t('createRound') }}</button>
            </form>
        </div>

        <div class="mt-4 rounded border bg-white p-4">
            <h2 class="mb-3 font-semibold">{{ t('groupStandings') }}</h2>
            <div v-if="groupSummaries.length === 0" class="rounded border bg-gray-50 p-3 text-sm text-gray-600">
                {{ t('noGroupsCreatedYet') }}
            </div>
            <div v-else class="space-y-4">
                <div v-for="summary in groupSummaries" :key="summary.id" class="rounded border">
                    <div class="flex items-center justify-between border-b bg-gray-50 px-3 py-2">
                        <div>
                            <div class="font-semibold">{{ summary.name }}</div>
                            <div class="text-xs text-gray-500">{{ t('roundsInGroup') }}: {{ summary.round_count }}</div>
                        </div>
                        <button
                            v-if="isSuperAdmin"
                            class="rounded border border-red-300 px-2 py-1 text-sm text-red-700"
                            @click="deleteGroup(summary.id)"
                        >
                            {{ t('deleteGroup') }}
                        </button>
                    </div>
                    <div v-if="summary.standings.length === 0" class="p-3 text-sm text-gray-600">{{ t('noTeamScoresYet') }}</div>
                    <div v-else class="overflow-auto">
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
                                <tr v-for="(row, index) in summary.standings" :key="`${summary.id}-${row.team_id}`">
                                    <td class="border px-2 py-1">{{ index + 1 }}</td>
                                    <td class="border px-2 py-1">{{ row.name }}</td>
                                    <td class="border px-2 py-1">{{ row.score }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 grid gap-4" :class="isSuperAdmin ? 'lg:grid-cols-2' : 'lg:grid-cols-1'">
            <form v-if="isSuperAdmin" @submit.prevent="createAdvancementRule" class="rounded border bg-white p-4">
                <h2 class="mb-2 font-semibold">{{ t('advancementRules') }}</h2>
                <p class="mb-3 text-sm text-gray-600">
                    {{ t('advancementRulesDescription') }}
                </p>
                <fieldset :disabled="!isSuperAdmin">
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">{{ t('sourceType') }}</div>
                            <select v-model="ruleForm.source_type" class="w-full rounded border px-2 py-1">
                                <option value="group">{{ t('groupBased') }}</option>
                                <option value="round">{{ t('roundBased') }}</option>
                            </select>
                        </label>
                        <label v-if="ruleForm.source_type === 'group'" class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">{{ t('sourceGroup') }}</div>
                            <select v-model="ruleForm.source_group_id" class="w-full rounded border px-2 py-1" required>
                                <option disabled value="">{{ t('selectGroup') }}</option>
                                <option v-for="group in tournament.groups" :key="group.id" :value="group.id">
                                    {{ group.name }}
                                </option>
                            </select>
                        </label>
                        <label v-else class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">{{ t('sourceRound') }}</div>
                            <select v-model="ruleForm.source_round_id" class="w-full rounded border px-2 py-1" required>
                                <option disabled value="">{{ t('selectRound') }}</option>
                                <option v-for="round in tournament.rounds" :key="round.id" :value="round.id">
                                    {{ round.name }}
                                </option>
                            </select>
                        </label>
                        <label class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">{{ t('sourceRank') }}</div>
                            <input v-model="ruleForm.source_rank" type="number" min="1" class="w-full rounded border px-2 py-1" />
                        </label>
                        <label class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">{{ t('action') }}</div>
                            <select v-model="ruleForm.action_type" class="w-full rounded border px-2 py-1">
                                <option value="advance">{{ t('advanceToAnotherRoundSlot') }}</option>
                                <option value="eliminate">{{ t('eliminate') }}</option>
                            </select>
                        </label>
                        <label class="block" v-if="ruleForm.action_type === 'advance'">
                            <div class="mb-1 text-sm font-medium text-gray-700">{{ t('targetRound') }}</div>
                            <select v-model="ruleForm.target_round_id" class="w-full rounded border px-2 py-1" required>
                                <option disabled value="">{{ t('selectTargetRound') }}</option>
                                <option v-for="round in tournament.rounds" :key="`target-${round.id}`" :value="round.id">
                                    {{ round.name }}
                                </option>
                            </select>
                        </label>
                        <label class="block" v-if="ruleForm.action_type === 'advance'">
                            <div class="mb-1 text-sm font-medium text-gray-700">{{ t('targetSlot') }}</div>
                            <input v-model="ruleForm.target_slot" type="number" min="1" class="w-full rounded border px-2 py-1" />
                        </label>
                        <label class="block" v-if="ruleForm.action_type === 'advance'">
                            <div class="mb-1 text-sm font-medium text-gray-700">{{ t('grantBonusScore') }}</div>
                            <input v-model="ruleForm.bonus_score" type="number" class="w-full rounded border px-2 py-1" />
                            <div class="mt-1 text-xs text-gray-500">{{ t('grantBonusScoreHint') }}</div>
                        </label>
                        <label class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">{{ t('priority') }}</div>
                            <input v-model="ruleForm.priority" type="number" min="0" class="w-full rounded border px-2 py-1" />
                            <div class="mt-1 text-xs text-gray-500">{{ t('priorityHint') }}</div>
                        </label>
                    </div>
                </fieldset>
                <button class="mt-3 rounded border bg-gray-900 px-3 py-1 text-white">{{ t('createRule') }}</button>
            </form>

            <div class="rounded border bg-white p-4">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="font-semibold">{{ t('currentRules') }} ({{ tournament.advancement_rules.length }})</h2>
                    <button class="rounded border px-2 py-1 text-xs" @click="advancementRulesExpanded = !advancementRulesExpanded">
                        {{ advancementRulesExpanded ? t('collapse') : t('expand') }}
                    </button>
                </div>
                <div v-if="advancementRulesExpanded">
                    <div class="mb-3 grid gap-2 md:grid-cols-3">
                        <label class="block">
                            <div class="mb-1 text-xs font-medium text-gray-600">{{ t('filterSourceType') }}</div>
                            <select v-model="advancementRuleFilters.source_type" class="w-full rounded border px-2 py-1 text-sm">
                                <option value="">{{ t('allOption') }}</option>
                                <option value="group">{{ t('groupBased') }}</option>
                                <option value="round">{{ t('roundBased') }}</option>
                            </select>
                        </label>
                        <label class="block">
                            <div class="mb-1 text-xs font-medium text-gray-600">{{ t('filterAction') }}</div>
                            <select v-model="advancementRuleFilters.action_type" class="w-full rounded border px-2 py-1 text-sm">
                                <option value="">{{ t('allOption') }}</option>
                                <option value="advance">{{ t('advanceToAnotherRoundSlot') }}</option>
                                <option value="eliminate">{{ t('eliminate') }}</option>
                            </select>
                        </label>
                        <label class="block">
                            <div class="mb-1 text-xs font-medium text-gray-600">{{ t('filterActive') }}</div>
                            <select v-model="advancementRuleFilters.active_state" class="w-full rounded border px-2 py-1 text-sm">
                                <option value="">{{ t('allOption') }}</option>
                                <option value="active">{{ t('activeOnly') }}</option>
                                <option value="inactive">{{ t('inactiveOnly') }}</option>
                            </select>
                        </label>
                    </div>
                    <div v-if="tournament.advancement_rules.length === 0" class="rounded border bg-gray-50 p-3 text-sm text-gray-600">
                        {{ t('noAdvancementRulesYet') }}
                    </div>
                    <div v-else-if="filteredAdvancementRules.length === 0" class="rounded border bg-gray-50 p-3 text-sm text-gray-600">
                        {{ t('noRulesMatchFilters') }}
                    </div>
                    <div v-else class="overflow-auto rounded border">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border px-2 py-1 text-left">{{ t('source') }}</th>
                                    <th class="border px-2 py-1 text-left">{{ t('rank') }}</th>
                                    <th class="border px-2 py-1 text-left">{{ t('action') }}</th>
                                    <th class="border px-2 py-1 text-left">{{ t('bonus') }}</th>
                                    <th class="border px-2 py-1 text-left">{{ t('priority') }}</th>
                                    <th class="border px-2 py-1 text-left">{{ t('active') }}</th>
                                    <th v-if="isSuperAdmin" class="border px-2 py-1 text-left">{{ t('actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="rule in filteredAdvancementRules" :key="rule.id">
                                    <td class="border px-2 py-1">{{ sourceTypeLabel(rule) }}: {{ sourceNameLabel(rule) }}</td>
                                    <td class="border px-2 py-1">{{ rule.source_rank }}</td>
                                    <td class="border px-2 py-1">{{ actionLabel(rule) }}</td>
                                    <td class="border px-2 py-1">
                                        <template v-if="rule.action_type === 'advance'">
                                            <input
                                                v-if="isSuperAdmin"
                                                :value="Number(rule.bonus_score || 0)"
                                                type="number"
                                                class="w-24 rounded border px-2 py-1"
                                                @change="updateAdvancementRule(rule, { bonus_score: Number($event.target.value || 0) })"
                                            />
                                            <span v-else>{{ Number(rule.bonus_score || 0) }}</span>
                                        </template>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="border px-2 py-1">
                                        <input
                                            v-if="isSuperAdmin"
                                            :value="rule.priority"
                                            type="number"
                                            min="0"
                                            class="w-20 rounded border px-2 py-1"
                                            @change="updateAdvancementRule(rule, { priority: Number($event.target.value || 0) })"
                                        />
                                        <span v-else>{{ rule.priority }}</span>
                                    </td>
                                    <td class="border px-2 py-1">
                                        <label v-if="isSuperAdmin" class="inline-flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                :checked="rule.is_active"
                                                @change="updateAdvancementRule(rule, { is_active: $event.target.checked })"
                                            />
                                            <span>{{ rule.is_active ? t('yes') : t('no') }}</span>
                                        </label>
                                        <span v-else>{{ rule.is_active ? t('yes') : t('no') }}</span>
                                    </td>
                                    <td v-if="isSuperAdmin" class="border px-2 py-1">
                                        <button
                                            class="rounded border border-red-300 px-2 py-1 text-red-700"
                                            @click="deleteAdvancementRule(rule)"
                                        >
                                            {{ t('delete') }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded border bg-white p-4">
            <h2 class="mb-2 font-semibold">{{ t('advancementLog') }}</h2>
            <p class="mb-3 text-sm text-gray-600">
                {{ t('advancementLogDescription') }}
            </p>
            <div v-if="tournament.advancement_logs.length === 0" class="rounded border bg-gray-50 p-3 text-sm text-gray-600">
                {{ t('noLogEntriesYet') }}
            </div>
            <div v-else class="overflow-auto rounded border">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="border px-2 py-1 text-left">{{ t('time') }}</th>
                            <th class="border px-2 py-1 text-left">{{ t('roundStatus') }}</th>
                            <th class="border px-2 py-1 text-left">{{ t('source') }}</th>
                            <th class="border px-2 py-1 text-left">{{ t('target') }}</th>
                            <th class="border px-2 py-1 text-left">{{ t('teamAssignmentChange') }}</th>
                            <th class="border px-2 py-1 text-left">{{ t('by') }}</th>
                            <th class="border px-2 py-1 text-left">{{ t('message') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in tournament.advancement_logs" :key="log.id">
                            <td class="border px-2 py-1">{{ formatLogTimestamp(log.created_at) }}</td>
                            <td class="border px-2 py-1">
                                <span class="inline-flex rounded border px-2 py-0.5 text-xs font-medium" :class="logStatusClass(log.status)">
                                    {{ logStatusLabel(log.status) }}
                                </span>
                            </td>
                            <td class="border px-2 py-1">
                                <span v-if="log.source_type === 'group'">{{ t('group') }}: {{ log.source_group?.name || '-' }}</span>
                                <span v-else-if="log.source_type === 'round'">{{ t('round') }}: {{ log.source_round?.name || '-' }}</span>
                                <span v-else>{{ t('system') }}</span>
                            </td>
                            <td class="border px-2 py-1">
                                <span v-if="log.target_round">{{ t('round') }}: {{ log.target_round.name }} / {{ t('slot') }} {{ log.target_slot ?? '-' }}</span>
                                <span v-else>-</span>
                            </td>
                            <td class="border px-2 py-1">
                                {{ logTeamChange(log) }}
                            </td>
                            <td class="border px-2 py-1">{{ log.user?.name || '-' }}</td>
                            <td class="border px-2 py-1">{{ log.message || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="isTemplateModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        >
            <div class="max-h-[80vh] w-full max-w-5xl overflow-auto rounded border bg-white p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-lg font-semibold">{{ t('roundTemplatesFor') }} {{ tournament.name }}</h3>
                    <button class="rounded border px-3 py-1" @click="isTemplateModalOpen = false">{{ t('close') }}</button>
                </div>

                <div v-if="tournament.round_templates.length === 0" class="rounded border bg-gray-50 p-3 text-sm text-gray-600">
                    {{ t('noTemplatesCreatedYet') }}
                </div>

                <div v-else class="overflow-auto rounded border">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="border px-2 py-1 text-left">{{ t('name') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('code') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('teamsPerRound') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('defaultScore') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('displayOrder') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('modes') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('lightningDeltas') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('normalDeltas') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('feverDeltas') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('ultimateFeverDeltas') }}</th>
                                <th class="border px-2 py-1 text-left">{{ t('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="template in tournament.round_templates" :key="template.id">
                                <td class="border px-2 py-1">{{ template.name }}</td>
                                <td class="border px-2 py-1">{{ template.code || '-' }}</td>
                                <td class="border px-2 py-1">{{ template.teams_per_round }}</td>
                                <td class="border px-2 py-1">{{ template.default_score ?? 100 }}</td>
                                <td class="border px-2 py-1">{{ template.sort_order }}</td>
                                <td class="border px-2 py-1">
                                    {{
                                        template.has_ultimate_fever ? t('modeNormalFeverUltimate') : (template.has_fever ? t('modeNormalFever') : t('modeNormalOnly'))
                                    }}
                                </td>
                                <td class="border px-2 py-1">{{ (template.default_lightning_score_deltas || template.default_score_deltas || []).join(', ') || '-' }}</td>
                                <td class="border px-2 py-1">{{ (template.default_buzzer_normal_score_deltas || template.default_score_deltas || []).join(', ') || '-' }}</td>
                                <td class="border px-2 py-1">{{ (template.default_buzzer_fever_score_deltas || []).join(', ') || '-' }}</td>
                                <td class="border px-2 py-1">{{ (template.default_buzzer_ultimate_score_deltas || []).join(', ') || '-' }}</td>
                                <td class="border px-2 py-1">
                                    <button
                                        v-if="isSuperAdmin"
                                        class="rounded border border-red-300 px-2 py-1 text-red-700"
                                        @click="deleteTemplate(template.id)"
                                    >
                                        {{ t('delete') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-4 space-y-4">
            <div v-for="round in tournament.rounds" :key="round.id" class="rounded border bg-white p-4">
                <div class="mb-2 flex items-center justify-between">
                    <button type="button" class="font-semibold hover:underline" @click="toggleRoundExpanded(round.id)">
                        {{ isRoundExpanded(round.id) ? '▼' : '▶' }} {{ round.name }}
                    </button>
                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-1">
                            <span class="rounded border px-2 py-0.5 text-xs" :class="statusBadgeClass(round.status)">{{ round.status === 'draft' ? t('statusDraft') : (round.status === 'live' ? t('statusLive') : t('statusCompleted')) }}</span>
                            <span class="rounded border border-gray-200 bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                {{
                                    round.phase === 'lightning'
                                        ? t('phaseLightning')
                                        : (round.phase === 'buzzer_normal'
                                            ? t('phaseBuzzerNormal')
                                            : (round.phase === 'buzzer_fever'
                                                ? t('phaseBuzzerFever')
                                                : t('phaseBuzzerUltimateFever')))
                                }}
                            </span>
                            <span
                                v-if="round.result?.is_stale"
                                class="rounded border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs text-amber-800"
                            >
                                {{ t('resultStale') }}
                            </span>
                            <span
                                v-if="round.participants.some((participant) => participant.assignment_mode === 'auto' && participant.assignment_reason === 'override')"
                                class="rounded border border-blue-300 bg-blue-50 px-2 py-0.5 text-xs text-blue-800"
                            >
                                {{ t('autoUpdatedFromOverride') }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-500">{{ round.code || t('noCode') }}</div>
                    </div>
                </div>
                <div v-if="!isRoundExpanded(round.id)" class="text-xs text-gray-500">
                    {{ t('clickRoundToExpand') }}
                </div>
                <div v-else-if="isSuperAdmin" class="mb-3 grid gap-2 md:grid-cols-4">
                    <input v-model="round.name" class="rounded border px-2 py-1 text-sm" :placeholder="t('roundName')" />
                    <input v-model="round.code" class="rounded border px-2 py-1 text-sm" :placeholder="t('code')" />
                    <select v-model="round.status" class="rounded border px-2 py-1 text-sm">
                        <option value="draft">{{ t('statusDraft') }}</option>
                        <option value="live">{{ t('statusLive') }}</option>
                        <option value="completed">{{ t('statusCompleted') }}</option>
                    </select>
                    <select v-model="round.phase" class="rounded border px-2 py-1 text-sm">
                        <option value="lightning">{{ t('phaseLightning') }}</option>
                        <option value="buzzer_normal">{{ t('phaseBuzzerNormal') }}</option>
                        <option value="buzzer_fever">{{ t('phaseBuzzerFever') }}</option>
                        <option value="buzzer_ultimate_fever">{{ t('phaseBuzzerUltimateFever') }}</option>
                    </select>
                    <input v-model="round.teams_per_round" type="number" min="2" max="8" class="rounded border px-2 py-1 text-sm" />
                    <input v-model="round._default_score" type="number" min="0" class="rounded border px-2 py-1 text-sm" :placeholder="t('defaultScore')" />
                    <select v-model="round.group_id" class="rounded border px-2 py-1 text-sm">
                        <option :value="null">{{ t('noGroup') }}</option>
                        <option v-for="group in tournament.groups" :key="group.id" :value="group.id">
                            {{ group.name }}
                        </option>
                    </select>
                    <input v-model="round._scheduled_start_at_local" type="datetime-local" class="rounded border px-2 py-1 text-sm" />
                    <input v-model="round.sort_order" type="number" class="rounded border px-2 py-1 text-sm" :placeholder="t('sortOrder')" />
                    <label class="inline-flex items-center justify-between rounded border px-2 py-1 text-sm md:col-span-1">
                        <span class="mr-2">{{ t('fever') }}</span>
                        <input v-model="round.has_fever" type="checkbox" />
                    </label>
                    <label class="inline-flex items-center justify-between rounded border px-2 py-1 text-sm md:col-span-1">
                        <span class="mr-2">{{ t('ultimate') }}</span>
                        <input v-model="round.has_ultimate_fever" type="checkbox" />
                    </label>
                    <label class="inline-flex items-center justify-between rounded border px-2 py-1 text-sm md:col-span-1">
                        <span class="mr-2">{{ t('hidePublicScores') }}</span>
                        <input v-model="round.hide_public_scores" type="checkbox" />
                    </label>
                    <div class="rounded border bg-gray-50 p-2 text-xs text-gray-600 md:col-span-4">
                        {{ t('scoreDeltaButtonsHelp') }}
                        {{ t('scoreDeltaButtonsExample') }} <code>30,15,-15</code> {{ t('scoreDeltaButtonsExampleSuffix') }}
                    </div>
                    <label class="block md:col-span-4">
                        <div class="mb-1 text-xs font-semibold text-gray-700">{{ t('lightningRoundScoreDeltaButtons') }}</div>
                        <input
                            v-model="round._lightning_score_deltas_text"
                            class="w-full rounded border px-2 py-1 text-sm"
                            :placeholder="t('exampleSingleDelta')"
                        />
                    </label>
                    <label class="block md:col-span-4">
                        <div class="mb-1 text-xs font-semibold text-gray-700">{{ t('buzzerNormalScoreDeltaButtons') }}</div>
                        <input
                            v-model="round._buzzer_normal_score_deltas_text"
                            class="w-full rounded border px-2 py-1 text-sm"
                            :placeholder="t('exampleNormalDeltas')"
                        />
                    </label>
                    <label v-if="round.has_fever || round.has_ultimate_fever" class="block md:col-span-4">
                        <div class="mb-1 text-xs font-semibold text-gray-700">{{ t('buzzerFeverScoreDeltaButtons') }}</div>
                        <input
                            v-model="round._buzzer_fever_score_deltas_text"
                            class="w-full rounded border px-2 py-1 text-sm"
                            :placeholder="t('exampleFeverDeltas')"
                        />
                    </label>
                    <label v-if="round.has_ultimate_fever" class="block md:col-span-4">
                        <div class="mb-1 text-xs font-semibold text-gray-700">{{ t('buzzerUltimateScoreDeltaButtons') }}</div>
                        <input
                            v-model="round._buzzer_ultimate_score_deltas_text"
                            class="w-full rounded border px-2 py-1 text-sm"
                            :placeholder="t('exampleUltimateDeltas')"
                        />
                    </label>
                    <div class="md:col-span-2 flex flex-wrap gap-2">
                        <button class="rounded border px-3 py-1 text-sm" @click="saveRoundDetails(round)">{{ t('saveRoundDetails') }}</button>
                        <button class="rounded border border-red-300 px-3 py-1 text-sm text-red-700" @click="deleteRound(round)">{{ t('deleteRound') }}</button>
                    </div>
                </div>
                <div v-else-if="isRoundExpanded(round.id)" class="mb-3 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    {{ t('roundDetailsSuperadminOnly') }}
                </div>
                <div v-if="isRoundExpanded(round.id)" class="grid gap-2 md:grid-cols-3">
                    <div v-for="participant in round.participants" :key="participant.id" class="rounded border p-2">
                        <div class="text-xs text-gray-500">{{ t('slot') }} {{ participant.slot }}</div>
                        <select v-model="participant.team_id" class="mt-1 w-full rounded border px-2 py-1 text-sm">
                            <option :value="null">{{ t('tbd') }}</option>
                            <option v-for="entry in tournament.tournament_teams" :key="entry.id" :value="entry.team_id">
                                {{ entry.display_name_snapshot }}
                            </option>
                        </select>
                    </div>
                </div>
                <button v-if="isRoundExpanded(round.id)" class="mt-3 rounded border px-3 py-1" @click="updateParticipants(round)">{{ t('saveParticipants') }}</button>
            </div>
        </div>
    </MainLayout>
</template>
