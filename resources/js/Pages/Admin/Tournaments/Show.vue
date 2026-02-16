<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { statusBadgeClass } from '@/composables/useStatusBadge';

const props = defineProps({
    tournament: Object,
    allTeams: Array,
    groupSummaries: Array,
});

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth?.user?.role === 'super_admin');
const isUsingRoundTemplate = computed(() => !!roundForm.round_template_id);

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
    if (!confirm('Delete this round template?')) {
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
    if (!confirm('Delete this group? Rounds in this group will become ungrouped.')) {
        return;
    }

    router.delete(route('admin.groups.destroy', groupId), {
        preserveScroll: true,
    });
};

const createRound = () => {
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
        onSuccess: () => roundForm.reset(
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
        ),
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
    if (!confirm(`Delete round "${round.name}"?`)) {
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
        source_rank: Number(data.source_rank),
        priority: Number(data.priority || 0),
    })).post(route('admin.advancement-rules.store', props.tournament.id), {
        preserveScroll: true,
        onSuccess: () => {
            ruleForm.reset('source_group_id', 'source_round_id', 'source_rank', 'target_round_id', 'target_slot', 'priority');
            ruleForm.source_type = 'group';
            ruleForm.action_type = 'advance';
            ruleForm.source_rank = 1;
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
    if (!confirm('Delete this advancement rule?')) {
        return;
    }

    router.delete(route('admin.advancement-rules.destroy', rule.id), {
        preserveScroll: true,
    });
};

const sourceTypeLabel = (rule) => rule.source_type === 'group' ? 'Group' : 'Round';
const sourceNameLabel = (rule) => rule.source_type === 'group'
    ? (rule.source_group?.name || 'Unknown group')
    : (rule.source_round?.name || 'Unknown round');
const actionLabel = (rule) => rule.action_type === 'eliminate'
    ? 'Eliminate'
    : `Advance to ${rule.target_round?.name || 'Unknown round'} / Slot ${rule.target_slot ?? '-'}`;
</script>

<template>
    <Head :title="tournament.name" />
    <MainLayout :title="tournament.name">
        <div class="grid gap-4" :class="isSuperAdmin ? 'lg:grid-cols-2' : 'lg:grid-cols-1'">
            <form v-if="isSuperAdmin" @submit.prevent="updateTournament" class="rounded border bg-white p-4">
                <h2 class="mb-2 font-semibold">Tournament Settings</h2>
                <fieldset :disabled="!isSuperAdmin">
                    <div class="grid gap-2 md:grid-cols-2">
                        <input v-model="tournamentForm.name" class="rounded border px-2 py-1" />
                        <input v-model="tournamentForm.year" type="number" class="rounded border px-2 py-1" />
                        <select v-model="tournamentForm.status" class="rounded border px-2 py-1">
                            <option value="draft">draft</option>
                            <option value="live">live</option>
                            <option value="completed">completed</option>
                        </select>
                        <input v-model="tournamentForm.scheduled_start_at" type="datetime-local" class="rounded border px-2 py-1" />
                        <input v-model="tournamentForm.timezone" class="rounded border px-2 py-1" />
                        <input v-model="tournamentForm.logo_path" class="rounded border px-2 py-1" placeholder="Logo URL/path" />
                        <input
                            type="file"
                            accept="image/*"
                            class="rounded border px-2 py-1 md:col-span-2"
                            @input="tournamentForm.logo_file = $event.target.files[0]"
                        />
                        <div class="text-xs text-gray-500 md:col-span-2">
                            Upload file overrides Logo URL/path when both are provided.
                        </div>
                    </div>
                </fieldset>
                <button class="mt-3 rounded border bg-gray-900 px-3 py-1 text-white">Save Tournament</button>
            </form>

            <div class="rounded border bg-white p-4">
                <h2 class="mb-2 font-semibold">Tournament Teams (max 24)</h2>
                <form v-if="isSuperAdmin" @submit.prevent="addTeam" class="mb-2 flex gap-2">
                    <select v-model="addTeamForm.team_id" class="min-w-0 flex-1 rounded border px-2 py-1" required>
                        <option disabled value="">Select team</option>
                        <option v-for="team in allTeams" :key="team.id" :value="team.id">
                            {{ team.university_name }} - {{ team.team_name }}
                        </option>
                    </select>
                    <button class="rounded border px-3 py-1">Add</button>
                </form>
                <div v-else class="mb-2 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    Adding/removing tournament teams is superadmin only.
                </div>
                <div class="max-h-48 overflow-auto rounded border">
                    <table class="min-w-full text-sm">
                        <tr v-for="entry in tournament.tournament_teams" :key="entry.id">
                            <td class="border px-2 py-1">{{ entry.display_name_snapshot }}</td>
                            <td class="border px-2 py-1 text-right">
                                <button v-if="isSuperAdmin" class="rounded border px-2 py-0.5" @click="removeTeam(entry.team_id)">Remove</button>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="isSuperAdmin" class="mt-4 grid gap-4 lg:grid-cols-2">
            <form @submit.prevent="createGroup" class="rounded border bg-white p-4">
                <h2 class="mb-2 font-semibold">Groups</h2>
                <p class="mb-3 text-sm text-gray-600">
                    Group rounds within this tournament and maintain cumulative team scores across all rounds in each group.
                </p>
                <fieldset :disabled="!isSuperAdmin">
                    <div class="grid gap-3 md:grid-cols-3">
                        <label class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">Group Name</div>
                            <input v-model="groupForm.name" class="w-full rounded border px-2 py-1" placeholder="e.g. Group A" required />
                        </label>
                        <label class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">Group Code (optional)</div>
                            <input v-model="groupForm.code" class="w-full rounded border px-2 py-1" placeholder="e.g. GA" />
                        </label>
                        <label class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">Display Order</div>
                            <input v-model="groupForm.sort_order" type="number" class="w-full rounded border px-2 py-1" />
                        </label>
                    </div>
                </fieldset>
                <button class="mt-3 rounded border bg-gray-900 px-3 py-1 text-white">Create Group</button>
            </form>

            <form @submit.prevent="createTemplate" class="rounded border bg-white p-4">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h2 class="font-semibold">Round Template Editor</h2>
                    <button
                        type="button"
                        class="rounded border px-3 py-1 text-sm"
                        @click="isTemplateModalOpen = true"
                    >
                        View Created Templates ({{ tournament.round_templates.length }})
                    </button>
                </div>
                <p class="mb-3 text-sm text-gray-600">
                    A template is a reusable setup for rounds. Example: teams per round = <strong>3</strong>, score buttons =
                    <strong>lightning: 20, buzzer normal: 20,10,-10</strong>.
                </p>
                <fieldset :disabled="!isSuperAdmin">
                    <div class="grid gap-3 md:grid-cols-2">
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">Template Name</div>
                        <input v-model="templateForm.name" class="w-full rounded border px-2 py-1" placeholder="e.g. Prelim Group Match" required />
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">Template Code (optional)</div>
                        <input v-model="templateForm.code" class="w-full rounded border px-2 py-1" placeholder="e.g. PRELIM_3T" />
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">Teams Per Round</div>
                        <input v-model="templateForm.teams_per_round" type="number" min="2" max="8" class="w-full rounded border px-2 py-1" />
                        <div class="mt-1 text-xs text-gray-500">Default is 3. This controls how many participant slots each round gets.</div>
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">Default Score</div>
                        <input v-model="templateForm.default_score" type="number" min="0" class="w-full rounded border px-2 py-1" />
                        <div class="mt-1 text-xs text-gray-500">Default is 100 if left empty.</div>
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">Display Order</div>
                        <input v-model="templateForm.sort_order" type="number" class="w-full rounded border px-2 py-1" />
                        <div class="mt-1 text-xs text-gray-500">Default is 0. Smaller numbers appear earlier in lists.</div>
                    </label>
                    <label class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">Buzzer Modes</div>
                        <div class="flex flex-wrap gap-4 rounded border px-3 py-2 text-sm">
                            <label class="inline-flex items-center gap-2">
                                <input v-model="templateForm.has_fever" type="checkbox" />
                                <span>Enable Fever</span>
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input v-model="templateForm.has_ultimate_fever" type="checkbox" />
                                <span>Enable Ultimate Fever</span>
                            </label>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">Ultimate Fever implies Fever.</div>
                    </label>
                    <label class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">Lightning Score Deltas</div>
                        <input
                            v-model="templateForm.default_lightning_score_deltas_text"
                            class="w-full rounded border px-2 py-1"
                            placeholder="20"
                        />
                    </label>
                    <label class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">Buzzer (Normal) Score Deltas</div>
                        <input
                            v-model="templateForm.default_buzzer_normal_score_deltas_text"
                            class="w-full rounded border px-2 py-1"
                            placeholder="20,10,-10"
                        />
                    </label>
                    <label v-if="templateForm.has_fever || templateForm.has_ultimate_fever" class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">Buzzer (Fever) Score Deltas</div>
                        <input
                            v-model="templateForm.default_buzzer_fever_score_deltas_text"
                            class="w-full rounded border px-2 py-1"
                            placeholder="30,15,-15"
                        />
                    </label>
                    <label v-if="templateForm.has_ultimate_fever" class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">Buzzer (Ultimate Fever) Score Deltas</div>
                        <input
                            v-model="templateForm.default_buzzer_ultimate_score_deltas_text"
                            class="w-full rounded border px-2 py-1"
                            placeholder="40,20,-20"
                        />
                    </label>
                    </div>
                </fieldset>
                <button class="mt-3 rounded border bg-gray-900 px-3 py-1 text-white">Create Template</button>
            </form>

            <form @submit.prevent="createRound" class="rounded border bg-white p-4">
                <h2 class="mb-2 font-semibold">Create Round</h2>
                <p class="mb-3 text-sm text-gray-600">
                    Create a specific match/round in this tournament. If you choose a template, keep values aligned with that template.
                </p>
                <fieldset :disabled="!isSuperAdmin">
                    <div class="grid gap-3 md:grid-cols-2">
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">Round Name</div>
                        <input v-model="roundForm.name" class="w-full rounded border px-2 py-1" placeholder="e.g. PrelimA1" required />
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">Round Code (optional)</div>
                        <input v-model="roundForm.code" class="w-full rounded border px-2 py-1" placeholder="e.g. PA1" />
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">Use Template (optional)</div>
                        <select v-model="roundForm.round_template_id" class="w-full rounded border px-2 py-1">
                            <option value="">No template</option>
                            <option v-for="template in tournament.round_templates" :key="template.id" :value="template.id">
                                {{ template.name }}
                            </option>
                        </select>
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">Group (optional)</div>
                        <select v-model="roundForm.group_id" class="w-full min-w-56 rounded border px-2 py-1">
                            <option value="">No group</option>
                            <option v-for="group in tournament.groups" :key="group.id" :value="group.id">
                                {{ group.name }}
                            </option>
                        </select>
                    </label>
                    <label v-if="!isUsingRoundTemplate" class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">Teams Per Round</div>
                        <input v-model="roundForm.teams_per_round" type="number" min="2" max="8" class="w-full rounded border px-2 py-1" />
                        <div class="mt-1 text-xs text-gray-500">Default is 3 participant slots for this round.</div>
                    </label>
                    <label v-if="!isUsingRoundTemplate" class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">Default Score</div>
                        <input v-model="roundForm.default_score" type="number" min="0" class="w-full rounded border px-2 py-1" />
                        <div class="mt-1 text-xs text-gray-500">Defaults to 100 if left empty.</div>
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">Scheduled Start Time</div>
                        <input v-model="roundForm.scheduled_start_at" type="datetime-local" class="w-full rounded border px-2 py-1" />
                    </label>
                    <label class="block">
                        <div class="mb-1 text-sm font-medium text-gray-700">Display Order</div>
                        <input v-model="roundForm.sort_order" type="number" class="w-full rounded border px-2 py-1" />
                        <div class="mt-1 text-xs text-gray-500">Default is 0. Smaller numbers appear earlier in round lists.</div>
                    </label>
                    <div v-if="isUsingRoundTemplate" class="rounded border bg-gray-50 px-3 py-2 text-sm text-gray-600 md:col-span-2">
                        Template-selected mode: teams per round, score deltas, and fever settings come from the chosen template.
                    </div>
                    <label v-if="!isUsingRoundTemplate" class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">Buzzer Modes</div>
                        <div class="flex flex-wrap gap-4 rounded border px-3 py-2 text-sm">
                            <label class="inline-flex items-center gap-2">
                                <input v-model="roundForm.has_fever" type="checkbox" />
                                <span>Enable Fever</span>
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input v-model="roundForm.has_ultimate_fever" type="checkbox" />
                                <span>Enable Ultimate Fever</span>
                            </label>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">Ultimate Fever implies Fever.</div>
                    </label>
                    <label class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">Public Score Visibility</div>
                        <div class="flex flex-wrap gap-4 rounded border px-3 py-2 text-sm">
                            <label class="inline-flex items-center gap-2">
                                <input v-model="roundForm.hide_public_scores" type="checkbox" />
                                <span>Hide scores on Timetable/Display (show ???)</span>
                            </label>
                        </div>
                    </label>
                    <label v-if="!isUsingRoundTemplate" class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">Lightning Score Deltas</div>
                        <input
                            v-model="roundForm.lightning_score_deltas_text"
                            class="w-full rounded border px-2 py-1"
                            placeholder="20"
                        />
                    </label>
                    <label v-if="!isUsingRoundTemplate" class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">Buzzer (Normal) Score Deltas</div>
                        <input
                            v-model="roundForm.buzzer_normal_score_deltas_text"
                            class="w-full rounded border px-2 py-1"
                            placeholder="20,10,-10"
                        />
                    </label>
                    <label v-if="!isUsingRoundTemplate && (roundForm.has_fever || roundForm.has_ultimate_fever)" class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">Buzzer (Fever) Score Deltas</div>
                        <input
                            v-model="roundForm.buzzer_fever_score_deltas_text"
                            class="w-full rounded border px-2 py-1"
                            placeholder="30,15,-15"
                        />
                    </label>
                    <label v-if="!isUsingRoundTemplate && roundForm.has_ultimate_fever" class="block md:col-span-2">
                        <div class="mb-1 text-sm font-medium text-gray-700">Buzzer (Ultimate Fever) Score Deltas</div>
                        <input
                            v-model="roundForm.buzzer_ultimate_score_deltas_text"
                            class="w-full rounded border px-2 py-1"
                            placeholder="40,20,-20"
                        />
                    </label>
                    </div>
                </fieldset>
                <button class="mt-3 rounded border bg-gray-900 px-3 py-1 text-white">Create Round</button>
            </form>
        </div>

        <div v-if="isSuperAdmin" class="mt-4 rounded border bg-white p-4">
            <h2 class="mb-3 font-semibold">Group Standings</h2>
            <div v-if="groupSummaries.length === 0" class="rounded border bg-gray-50 p-3 text-sm text-gray-600">
                No groups created yet.
            </div>
            <div v-else class="space-y-4">
                <div v-for="summary in groupSummaries" :key="summary.id" class="rounded border">
                    <div class="flex items-center justify-between border-b bg-gray-50 px-3 py-2">
                        <div>
                            <div class="font-semibold">{{ summary.name }}</div>
                            <div class="text-xs text-gray-500">Rounds in group: {{ summary.round_count }}</div>
                        </div>
                        <button
                            v-if="isSuperAdmin"
                            class="rounded border border-red-300 px-2 py-1 text-sm text-red-700"
                            @click="deleteGroup(summary.id)"
                        >
                            Delete Group
                        </button>
                    </div>
                    <div v-if="summary.standings.length === 0" class="p-3 text-sm text-gray-600">No team scores yet.</div>
                    <div v-else class="overflow-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border px-2 py-1 text-left">Rank</th>
                                    <th class="border px-2 py-1 text-left">Team</th>
                                    <th class="border px-2 py-1 text-left">Total Score</th>
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

        <div v-if="isSuperAdmin" class="mt-4 grid gap-4 lg:grid-cols-2">
            <form @submit.prevent="createAdvancementRule" class="rounded border bg-white p-4">
                <h2 class="mb-2 font-semibold">Advancement Rules</h2>
                <p class="mb-3 text-sm text-gray-600">
                    Configure auto-advancement from either a completed round ranking or a completed group ranking.
                </p>
                <fieldset :disabled="!isSuperAdmin">
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">Source Type</div>
                            <select v-model="ruleForm.source_type" class="w-full rounded border px-2 py-1">
                                <option value="group">Group-based</option>
                                <option value="round">Round-based</option>
                            </select>
                        </label>
                        <label v-if="ruleForm.source_type === 'group'" class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">Source Group</div>
                            <select v-model="ruleForm.source_group_id" class="w-full rounded border px-2 py-1" required>
                                <option disabled value="">Select group</option>
                                <option v-for="group in tournament.groups" :key="group.id" :value="group.id">
                                    {{ group.name }}
                                </option>
                            </select>
                        </label>
                        <label v-else class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">Source Round</div>
                            <select v-model="ruleForm.source_round_id" class="w-full rounded border px-2 py-1" required>
                                <option disabled value="">Select round</option>
                                <option v-for="round in tournament.rounds" :key="round.id" :value="round.id">
                                    {{ round.name }}
                                </option>
                            </select>
                        </label>
                        <label class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">Source Rank</div>
                            <input v-model="ruleForm.source_rank" type="number" min="1" class="w-full rounded border px-2 py-1" />
                        </label>
                        <label class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">Action</div>
                            <select v-model="ruleForm.action_type" class="w-full rounded border px-2 py-1">
                                <option value="advance">Advance to another round slot</option>
                                <option value="eliminate">Eliminate</option>
                            </select>
                        </label>
                        <label class="block" v-if="ruleForm.action_type === 'advance'">
                            <div class="mb-1 text-sm font-medium text-gray-700">Target Round</div>
                            <select v-model="ruleForm.target_round_id" class="w-full rounded border px-2 py-1" required>
                                <option disabled value="">Select target round</option>
                                <option v-for="round in tournament.rounds" :key="`target-${round.id}`" :value="round.id">
                                    {{ round.name }}
                                </option>
                            </select>
                        </label>
                        <label class="block" v-if="ruleForm.action_type === 'advance'">
                            <div class="mb-1 text-sm font-medium text-gray-700">Target Slot</div>
                            <input v-model="ruleForm.target_slot" type="number" min="1" class="w-full rounded border px-2 py-1" />
                        </label>
                        <label class="block">
                            <div class="mb-1 text-sm font-medium text-gray-700">Priority</div>
                            <input v-model="ruleForm.priority" type="number" min="0" class="w-full rounded border px-2 py-1" />
                            <div class="mt-1 text-xs text-gray-500">Lower number means earlier processing.</div>
                        </label>
                    </div>
                </fieldset>
                <button class="mt-3 rounded border bg-gray-900 px-3 py-1 text-white">Create Rule</button>
            </form>

            <div class="rounded border bg-white p-4">
                <h2 class="mb-2 font-semibold">Current Rules</h2>
                <div v-if="tournament.advancement_rules.length === 0" class="rounded border bg-gray-50 p-3 text-sm text-gray-600">
                    No advancement rules created yet.
                </div>
                <div v-else class="overflow-auto rounded border">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="border px-2 py-1 text-left">Source</th>
                                <th class="border px-2 py-1 text-left">Rank</th>
                                <th class="border px-2 py-1 text-left">Action</th>
                                <th class="border px-2 py-1 text-left">Priority</th>
                                <th class="border px-2 py-1 text-left">Active</th>
                                <th class="border px-2 py-1 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="rule in tournament.advancement_rules" :key="rule.id">
                                <td class="border px-2 py-1">{{ sourceTypeLabel(rule) }}: {{ sourceNameLabel(rule) }}</td>
                                <td class="border px-2 py-1">{{ rule.source_rank }}</td>
                                <td class="border px-2 py-1">{{ actionLabel(rule) }}</td>
                                <td class="border px-2 py-1">
                                    <input
                                        :value="rule.priority"
                                        type="number"
                                        min="0"
                                        class="w-20 rounded border px-2 py-1"
                                        :disabled="!isSuperAdmin"
                                        @change="updateAdvancementRule(rule, { priority: Number($event.target.value || 0) })"
                                    />
                                </td>
                                <td class="border px-2 py-1">
                                    <label class="inline-flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            :checked="rule.is_active"
                                            :disabled="!isSuperAdmin"
                                            @change="updateAdvancementRule(rule, { is_active: $event.target.checked })"
                                        />
                                        <span>{{ rule.is_active ? 'Yes' : 'No' }}</span>
                                    </label>
                                </td>
                                <td class="border px-2 py-1">
                                    <button
                                        v-if="isSuperAdmin"
                                        class="rounded border border-red-300 px-2 py-1 text-red-700"
                                        @click="deleteAdvancementRule(rule)"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="isSuperAdmin" class="mt-4 rounded border bg-white p-4">
            <h2 class="mb-2 font-semibold">Advancement Log</h2>
            <p class="mb-3 text-sm text-gray-600">
                Tracks auto-advancement decisions, manual-lock blocks, eliminated outcomes, and stale-result marks.
            </p>
            <div v-if="tournament.advancement_logs.length === 0" class="rounded border bg-gray-50 p-3 text-sm text-gray-600">
                No log entries yet.
            </div>
            <div v-else class="overflow-auto rounded border">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="border px-2 py-1 text-left">Time</th>
                            <th class="border px-2 py-1 text-left">Status</th>
                            <th class="border px-2 py-1 text-left">Source</th>
                            <th class="border px-2 py-1 text-left">Target</th>
                            <th class="border px-2 py-1 text-left">Before -> After</th>
                            <th class="border px-2 py-1 text-left">By</th>
                            <th class="border px-2 py-1 text-left">Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in tournament.advancement_logs" :key="log.id">
                            <td class="border px-2 py-1">{{ log.created_at }}</td>
                            <td class="border px-2 py-1">{{ log.status }}</td>
                            <td class="border px-2 py-1">
                                <span v-if="log.source_type === 'group'">Group: {{ log.source_group?.name || '-' }}</span>
                                <span v-else-if="log.source_type === 'round'">Round: {{ log.source_round?.name || '-' }}</span>
                                <span v-else>System</span>
                            </td>
                            <td class="border px-2 py-1">
                                <span v-if="log.target_round">Round: {{ log.target_round.name }} / Slot {{ log.target_slot ?? '-' }}</span>
                                <span v-else>-</span>
                            </td>
                            <td class="border px-2 py-1">
                                {{ log.before_team?.team_name || '-' }} -> {{ log.after_team?.team_name || '-' }}
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
                    <h3 class="text-lg font-semibold">Round Templates for {{ tournament.name }}</h3>
                    <button class="rounded border px-3 py-1" @click="isTemplateModalOpen = false">Close</button>
                </div>

                <div v-if="tournament.round_templates.length === 0" class="rounded border bg-gray-50 p-3 text-sm text-gray-600">
                    No templates created yet.
                </div>

                <div v-else class="overflow-auto rounded border">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="border px-2 py-1 text-left">Name</th>
                                <th class="border px-2 py-1 text-left">Code</th>
                                <th class="border px-2 py-1 text-left">Teams / Round</th>
                                <th class="border px-2 py-1 text-left">Default Score</th>
                                <th class="border px-2 py-1 text-left">Display Order</th>
                                <th class="border px-2 py-1 text-left">Modes</th>
                                <th class="border px-2 py-1 text-left">Lightning Deltas</th>
                                <th class="border px-2 py-1 text-left">Normal Deltas</th>
                                <th class="border px-2 py-1 text-left">Fever Deltas</th>
                                <th class="border px-2 py-1 text-left">Ultimate Fever Deltas</th>
                                <th class="border px-2 py-1 text-left">Actions</th>
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
                                        template.has_ultimate_fever ? 'Normal + Fever + Ultimate' : (template.has_fever ? 'Normal + Fever' : 'Normal only')
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
                                        Delete
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
                    <h3 class="font-semibold">{{ round.name }}</h3>
                    <div class="flex items-center gap-1">
                        <span class="rounded border px-2 py-0.5 text-xs" :class="statusBadgeClass(round.status)">{{ round.status }}</span>
                        <span class="rounded border border-gray-200 bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ round.phase }}</span>
                        <span
                            v-if="round.result?.is_stale"
                            class="rounded border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs text-amber-800"
                        >
                            Result Stale
                        </span>
                        <span
                            v-if="round.participants.some((participant) => participant.assignment_mode === 'auto' && participant.assignment_reason === 'override')"
                            class="rounded border border-blue-300 bg-blue-50 px-2 py-0.5 text-xs text-blue-800"
                        >
                            Auto-updated from override
                        </span>
                    </div>
                    <div class="text-xs text-gray-500">{{ round.code || 'No code' }}</div>
                </div>
                <div v-if="isSuperAdmin" class="mb-3 grid gap-2 md:grid-cols-4">
                    <input v-model="round.name" class="rounded border px-2 py-1 text-sm" placeholder="Round name" />
                    <input v-model="round.code" class="rounded border px-2 py-1 text-sm" placeholder="Code" />
                    <select v-model="round.status" class="rounded border px-2 py-1 text-sm">
                        <option value="draft">draft</option>
                        <option value="live">live</option>
                        <option value="completed">completed</option>
                    </select>
                    <select v-model="round.phase" class="rounded border px-2 py-1 text-sm">
                        <option value="lightning">lightning</option>
                        <option value="buzzer_normal">buzzer normal</option>
                        <option value="buzzer_fever">buzzer fever</option>
                        <option value="buzzer_ultimate_fever">buzzer ultimate fever</option>
                    </select>
                    <input v-model="round.teams_per_round" type="number" min="2" max="8" class="rounded border px-2 py-1 text-sm" />
                    <input v-model="round._default_score" type="number" min="0" class="rounded border px-2 py-1 text-sm" placeholder="Default score" />
                    <select v-model="round.group_id" class="rounded border px-2 py-1 text-sm">
                        <option :value="null">No group</option>
                        <option v-for="group in tournament.groups" :key="group.id" :value="group.id">
                            {{ group.name }}
                        </option>
                    </select>
                    <input v-model="round._scheduled_start_at_local" type="datetime-local" class="rounded border px-2 py-1 text-sm" />
                    <input v-model="round.sort_order" type="number" class="rounded border px-2 py-1 text-sm" placeholder="Sort order" />
                    <label class="inline-flex items-center justify-between rounded border px-2 py-1 text-sm md:col-span-1">
                        <span class="mr-2">Fever</span>
                        <input v-model="round.has_fever" type="checkbox" />
                    </label>
                    <label class="inline-flex items-center justify-between rounded border px-2 py-1 text-sm md:col-span-1">
                        <span class="mr-2">Ultimate</span>
                        <input v-model="round.has_ultimate_fever" type="checkbox" />
                    </label>
                    <label class="inline-flex items-center justify-between rounded border px-2 py-1 text-sm md:col-span-2">
                        <span class="mr-2">Hide Public Scores</span>
                        <input v-model="round.hide_public_scores" type="checkbox" />
                    </label>
                    <div class="rounded border bg-gray-50 p-2 text-xs text-gray-600 md:col-span-4">
                        Score Delta Buttons: comma-separated values shown on the Control page for this phase.
                        Example <code>30,15,-15</code> creates +30, +15, and -15 buttons.
                    </div>
                    <label class="block md:col-span-4">
                        <div class="mb-1 text-xs font-semibold text-gray-700">Lightning Round Score Delta Buttons</div>
                        <input
                            v-model="round._lightning_score_deltas_text"
                            class="w-full rounded border px-2 py-1 text-sm"
                            placeholder="Example: 20"
                        />
                    </label>
                    <label class="block md:col-span-4">
                        <div class="mb-1 text-xs font-semibold text-gray-700">Buzzer (Normal) Score Delta Buttons</div>
                        <input
                            v-model="round._buzzer_normal_score_deltas_text"
                            class="w-full rounded border px-2 py-1 text-sm"
                            placeholder="Example: 20,10,-10"
                        />
                    </label>
                    <label v-if="round.has_fever || round.has_ultimate_fever" class="block md:col-span-4">
                        <div class="mb-1 text-xs font-semibold text-gray-700">Buzzer (Fever) Score Delta Buttons</div>
                        <input
                            v-model="round._buzzer_fever_score_deltas_text"
                            class="w-full rounded border px-2 py-1 text-sm"
                            placeholder="Example: 30,15,-15"
                        />
                    </label>
                    <label v-if="round.has_ultimate_fever" class="block md:col-span-4">
                        <div class="mb-1 text-xs font-semibold text-gray-700">Buzzer (Ultimate Fever) Score Delta Buttons</div>
                        <input
                            v-model="round._buzzer_ultimate_score_deltas_text"
                            class="w-full rounded border px-2 py-1 text-sm"
                            placeholder="Example: 40,20,-20"
                        />
                    </label>
                    <div class="md:col-span-2 flex flex-wrap gap-2">
                        <button class="rounded border px-3 py-1 text-sm" @click="saveRoundDetails(round)">Save Round Details</button>
                        <button class="rounded border border-red-300 px-3 py-1 text-sm text-red-700" @click="deleteRound(round)">Delete Round</button>
                    </div>
                </div>
                <div v-else class="mb-3 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    Round details are editable by superadmin only. You can still edit participant slots below.
                </div>
                <div class="grid gap-2 md:grid-cols-3">
                    <div v-for="participant in round.participants" :key="participant.id" class="rounded border p-2">
                        <div class="text-xs text-gray-500">Slot {{ participant.slot }}</div>
                        <select v-model="participant.team_id" class="mt-1 w-full rounded border px-2 py-1 text-sm">
                            <option :value="null">TBD</option>
                            <option v-for="entry in tournament.tournament_teams" :key="entry.id" :value="entry.team_id">
                                {{ entry.display_name_snapshot }}
                            </option>
                        </select>
                    </div>
                </div>
                <button class="mt-3 rounded border px-3 py-1" @click="updateParticipants(round)">Save Participants</button>
            </div>
        </div>
    </MainLayout>
</template>
