<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

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

const submit = () => {
    form.post(route('admin.teams.store'), {
        forceFormData: true,
        onSuccess: () => form.reset('team_name', 'short_name', 'icon_path', 'icon_file'),
    });
};

const removeTeam = (team) => {
    if (!confirm(`Archive team "${team.team_name}"?`)) {
        return;
    }

    router.delete(route('admin.teams.destroy', team.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Teams" />
    <MainLayout title="Teams">
        <form @submit.prevent="submit" class="mb-6 grid gap-2 rounded border bg-white p-4 md:grid-cols-4">
            <input v-model="form.university_name" placeholder="University" class="rounded border px-2 py-1" required />
            <input v-model="form.team_name" placeholder="Team name" class="rounded border px-2 py-1" required />
            <input v-model="form.short_name" placeholder="Short name" class="rounded border px-2 py-1" />
            <input v-model="form.icon_path" placeholder="Icon URL (optional)" class="rounded border px-2 py-1" />
            <input
                type="file"
                accept="image/*"
                class="rounded border px-2 py-1 md:col-span-2"
                @input="form.icon_file = $event.target.files[0]"
            />
            <div class="text-xs text-gray-500 md:col-span-4">Upload file will override URL if both are provided.</div>
            <button class="rounded border bg-gray-900 px-3 py-1 text-white md:col-span-4">Create Team</button>
        </form>

        <div class="overflow-auto rounded border bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border px-2 py-1 text-left">University</th>
                        <th class="border px-2 py-1 text-left">Team</th>
                        <th class="border px-2 py-1 text-left">Short</th>
                        <th class="border px-2 py-1 text-left">Icon</th>
                        <th class="border px-2 py-1 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="team in teams" :key="team.id">
                        <td class="border px-2 py-1">{{ team.university_name }}</td>
                        <td class="border px-2 py-1">{{ team.team_name }}</td>
                        <td class="border px-2 py-1">{{ team.short_name || '-' }}</td>
                        <td class="border px-2 py-1">
                            <img v-if="team.icon_url" :src="team.icon_url" :alt="team.team_name" class="h-10 w-10 rounded object-cover" />
                            <span v-else>-</span>
                        </td>
                        <td class="border px-2 py-1">
                            <button class="rounded border border-amber-400 px-2 py-1 text-amber-700" @click="removeTeam(team)">Archive</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </MainLayout>
</template>
