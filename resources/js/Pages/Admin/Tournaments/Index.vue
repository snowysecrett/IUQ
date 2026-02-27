<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { statusBadgeClass } from '@/composables/useStatusBadge';
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';

defineProps({
    tournaments: Array,
});

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth?.user?.role === 'super_admin');
const { t } = useI18n();

const form = useForm({
    name: '',
    year: new Date().getFullYear(),
    scheduled_start_at: '',
    timezone: 'UTC',
    logo_path: '',
    logo_file: null,
});
const cloneForm = useForm({
    source_tournament_id: '',
    name: '',
    year: new Date().getFullYear(),
    scheduled_start_at: '',
    clone_tournament_teams: false,
    clone_round_start_times: false,
    clone_eligible_round_participants: false,
});

const submit = () => {
    form.post(route('admin.tournaments.store'), {
        forceFormData: true,
        onSuccess: () => form.reset('name', 'scheduled_start_at', 'logo_path', 'logo_file'),
    });
};

const submitClone = () => {
    cloneForm.post(route('admin.tournaments.clone-rules'), {
        onSuccess: () => cloneForm.reset('name', 'scheduled_start_at', 'clone_tournament_teams', 'clone_round_start_times', 'clone_eligible_round_participants'),
    });
};
</script>

<template>
    <Head :title="t('tournamentsTitle')" />
    <MainLayout :title="t('tournamentsTitle')">
        <form v-if="isSuperAdmin" @submit.prevent="submit" class="mb-6 grid gap-2 rounded border bg-white p-4 md:grid-cols-5">
            <input v-model="form.name" class="rounded border px-2 py-1" :placeholder="t('tournamentName')" required />
            <input v-model="form.year" type="number" class="rounded border px-2 py-1" :placeholder="t('year')" required />
            <input v-model="form.scheduled_start_at" type="datetime-local" class="rounded border px-2 py-1" />
            <input v-model="form.timezone" class="rounded border px-2 py-1" :placeholder="t('timezone')" />
            <input v-model="form.logo_path" class="rounded border px-2 py-1" :placeholder="t('logoUrlPath')" />
            <input
                type="file"
                accept="image/*"
                class="rounded border px-2 py-1 md:col-span-3"
                @input="form.logo_file = $event.target.files[0]"
            />
            <div class="text-xs text-gray-500 md:col-span-2">{{ t('uploadOverridesLogo') }}</div>
            <button class="rounded border bg-gray-900 px-3 py-1 text-white md:col-span-5">{{ t('createTournament') }}</button>
        </form>

        <form v-if="isSuperAdmin" @submit.prevent="submitClone" class="mb-6 grid gap-2 rounded border bg-white p-4 md:grid-cols-5">
            <select v-model="cloneForm.source_tournament_id" class="rounded border px-2 py-1" required>
                <option disabled value="">{{ t('copyRulesFrom') }}</option>
                <option v-for="tournament in tournaments" :key="`source-${tournament.id}`" :value="tournament.id">
                    {{ tournament.name }} ({{ tournament.year }})
                </option>
            </select>
            <input v-model="cloneForm.name" class="rounded border px-2 py-1" :placeholder="t('newTournamentName')" required />
            <input v-model="cloneForm.year" type="number" class="rounded border px-2 py-1" :placeholder="t('newYear')" required />
            <input v-model="cloneForm.scheduled_start_at" type="datetime-local" class="rounded border px-2 py-1" />
            <div class="rounded border border-blue-200 bg-blue-50 px-2 py-1 text-sm text-blue-800">{{ t('copiesRulesOnly') }}</div>
            <label class="flex w-full items-center gap-2 rounded border px-2 py-1 text-sm md:col-span-1">
                <input
                    v-model="cloneForm.clone_tournament_teams"
                    type="checkbox"
                    @change="!cloneForm.clone_tournament_teams && (cloneForm.clone_eligible_round_participants = false)"
                />
                <span>{{ t('cloneTournamentTeams') }}</span>
            </label>
            <label class="flex w-full items-center gap-2 rounded border px-2 py-1 text-sm md:col-span-1">
                <input v-model="cloneForm.clone_round_start_times" type="checkbox" />
                <span>{{ t('cloneRoundStartTimes') }}</span>
            </label>
            <label
                class="flex w-full items-center gap-2 rounded border px-2 py-1 text-sm md:col-span-1"
                :class="cloneForm.clone_tournament_teams ? '' : 'opacity-60'"
            >
                <input
                    v-model="cloneForm.clone_eligible_round_participants"
                    type="checkbox"
                    :disabled="!cloneForm.clone_tournament_teams"
                />
                <span>{{ t('cloneEligibleRoundParticipants') }}</span>
            </label>
            <button class="rounded border bg-gray-900 px-3 py-1 text-white md:col-span-5">{{ t('cloneRulesToNewTournament') }}</button>
        </form>

        <div class="overflow-auto rounded border bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border px-2 py-1 text-left">{{ t('name') }}</th>
                        <th class="border px-2 py-1 text-left">{{ t('year') }}</th>
                        <th class="border px-2 py-1 text-left">{{ t('status') }}</th>
                        <th class="border px-2 py-1 text-left">{{ t('teams') }}</th>
                        <th class="border px-2 py-1 text-left">{{ t('rounds') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="tournament in tournaments" :key="tournament.id">
                        <td class="border px-2 py-1">
                            <Link :href="route('admin.tournaments.show', tournament.id)" class="text-blue-600 hover:underline">
                                {{ tournament.name }}
                            </Link>
                        </td>
                        <td class="border px-2 py-1">{{ tournament.year }}</td>
                        <td class="border px-2 py-1">
                            <span class="rounded border px-2 py-0.5" :class="statusBadgeClass(tournament.status)">
                                {{
                                    tournament.status === 'draft'
                                        ? t('statusDraft')
                                        : (tournament.status === 'live' ? t('statusLive') : t('statusCompleted'))
                                }}
                            </span>
                        </td>
                        <td class="border px-2 py-1">{{ tournament.tournament_teams_count }}</td>
                        <td class="border px-2 py-1">{{ tournament.rounds_count }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </MainLayout>
</template>
