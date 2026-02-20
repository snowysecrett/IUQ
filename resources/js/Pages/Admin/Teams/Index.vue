<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import { useI18n } from '@/composables/useI18n';

defineProps({
    teams: Array,
});

const form = useForm({
    university_name: '',
    team_name: '',
    short_name: '',
    icon_path: '',
    icon_file: null,
});

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth?.user?.role === 'super_admin');
const { t } = useI18n();

const submit = () => {
    form.post(route('admin.teams.store'), {
        forceFormData: true,
        onSuccess: () => form.reset('team_name', 'short_name', 'icon_path', 'icon_file'),
    });
};

const editingTeamId = ref(null);
const editForm = useForm({
    university_name: '',
    team_name: '',
    short_name: '',
    icon_path: '',
    icon_file: null,
});

const startEdit = (team) => {
    if (!isSuperAdmin.value) {
        return;
    }

    editingTeamId.value = team.id;
    editForm.reset();
    editForm.university_name = team.university_name ?? '';
    editForm.team_name = team.team_name ?? '';
    editForm.short_name = team.short_name ?? '';
    editForm.icon_path = team.icon_path ?? '';
    editForm.icon_file = null;
};

const cancelEdit = () => {
    editingTeamId.value = null;
    editForm.reset();
};

const submitEdit = (teamId) => {
    if (!isSuperAdmin.value) {
        return;
    }

    editForm
        .transform((data) => ({
            ...data,
            _method: 'patch',
        }))
        .post(route('admin.teams.update', teamId), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            cancelEdit();
        },
    });
};

const removeTeam = (team) => {
    if (!isSuperAdmin.value) {
        return;
    }

    if (!confirm(t('archiveTeamConfirm', { name: team.team_name }))) {
        return;
    }

    router.delete(route('admin.teams.destroy', team.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="t('teamsTitle')" />
    <MainLayout :title="t('teamsTitle')">
        <form
            v-if="isSuperAdmin"
            @submit.prevent="submit"
            class="mb-6 grid gap-2 rounded border bg-white p-4 md:grid-cols-4"
        >
            <input v-model="form.university_name" :placeholder="t('university')" class="rounded border px-2 py-1" required />
            <input v-model="form.team_name" :placeholder="t('teamName')" class="rounded border px-2 py-1" required />
            <input v-model="form.short_name" :placeholder="t('shortName')" class="rounded border px-2 py-1" />
            <input v-model="form.icon_path" :placeholder="t('iconUrlOptional')" class="rounded border px-2 py-1" />
            <input
                type="file"
                accept="image/*"
                class="rounded border px-2 py-1 md:col-span-2"
                @change="form.icon_file = $event.target.files[0]"
            />
            <div class="text-xs text-gray-500 md:col-span-4">{{ t('uploadOverridesUrl') }}</div>
            <button class="rounded border bg-gray-900 px-3 py-1 text-white md:col-span-4">{{ t('createTeam') }}</button>
        </form>

        <div class="overflow-auto rounded border bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border px-2 py-1 text-left">{{ t('university') }}</th>
                        <th class="border px-2 py-1 text-left">{{ t('team') }}</th>
                        <th class="border px-2 py-1 text-left">{{ t('short') }}</th>
                        <th class="border px-2 py-1 text-left">{{ t('icon') }}</th>
                        <th class="border px-2 py-1 text-left">{{ t('actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="team in teams" :key="team.id">
                        <tr>
                            <td class="border px-2 py-1">{{ team.university_name }}</td>
                            <td class="border px-2 py-1">{{ team.team_name }}</td>
                            <td class="border px-2 py-1">{{ team.short_name || '-' }}</td>
                            <td class="border px-2 py-1">
                                <img v-if="team.icon_url" :src="team.icon_url" :alt="team.team_name" class="h-10 w-10 rounded object-cover" />
                                <span v-else>-</span>
                            </td>
                            <td class="border px-2 py-1">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-if="isSuperAdmin"
                                        class="rounded border border-blue-400 px-2 py-1 text-blue-700"
                                        @click="startEdit(team)"
                                    >
                                        {{ t('edit') }}
                                    </button>
                                    <button
                                        v-if="isSuperAdmin"
                                        class="rounded border border-amber-400 px-2 py-1 text-amber-700"
                                        @click="removeTeam(team)"
                                    >
                                        {{ t('archive') }}
                                    </button>
                                    <span v-if="!isSuperAdmin" class="text-xs text-gray-500">{{ t('superadminOnly') }}</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="isSuperAdmin && editingTeamId === team.id">
                            <td colspan="5" class="border bg-gray-50 p-3">
                                <form @submit.prevent="submitEdit(team.id)" class="grid gap-2 md:grid-cols-4">
                                    <input
                                        v-model="editForm.university_name"
                                        :placeholder="t('university')"
                                        class="rounded border px-2 py-1"
                                        required
                                    />
                                    <input
                                        v-model="editForm.team_name"
                                        :placeholder="t('teamName')"
                                        class="rounded border px-2 py-1"
                                        required
                                    />
                                    <input v-model="editForm.short_name" :placeholder="t('shortName')" class="rounded border px-2 py-1" />
                                    <input
                                        v-model="editForm.icon_path"
                                        :placeholder="t('iconUrlPathOptional')"
                                        class="rounded border px-2 py-1"
                                    />
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="rounded border px-2 py-1 md:col-span-2"
                                        @change="editForm.icon_file = $event.target.files[0]"
                                    />
                                    <div class="text-xs text-gray-500 md:col-span-4">
                                        {{ t('uploadOverridesUrlPath') }}
                                    </div>
                                    <div class="flex gap-2 md:col-span-4">
                                        <button class="rounded border bg-gray-900 px-3 py-1 text-white">{{ t('saveTeam') }}</button>
                                        <button type="button" class="rounded border px-3 py-1" @click="cancelEdit">{{ t('cancel') }}</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </MainLayout>
</template>
