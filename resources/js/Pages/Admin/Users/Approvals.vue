<script setup>
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineProps({
    users: Array,
});

const setApproval = (user, approved) => {
    router.patch(route('admin.user-approvals.update', user.id), { approved });
};
</script>

<template>
    <Head title="User Approvals" />
    <MainLayout title="User Approvals">
        <div class="overflow-auto rounded border bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border px-2 py-1 text-left">Name</th>
                        <th class="border px-2 py-1 text-left">Email</th>
                        <th class="border px-2 py-1 text-left">Approved</th>
                        <th class="border px-2 py-1 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id">
                        <td class="border px-2 py-1">{{ user.name }}</td>
                        <td class="border px-2 py-1">{{ user.email }}</td>
                        <td class="border px-2 py-1">{{ user.approved_at ? 'Yes' : 'No' }}</td>
                        <td class="border px-2 py-1">
                            <div class="flex gap-2">
                                <button class="rounded border px-2 py-1" @click="setApproval(user, true)">Approve</button>
                                <button class="rounded border px-2 py-1" @click="setApproval(user, false)">Revoke</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </MainLayout>
</template>
