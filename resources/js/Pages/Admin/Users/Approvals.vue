<script setup>
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { useI18n } from '@/composables/useI18n';

defineProps({
    users: Array,
});
const { t } = useI18n();

const setApproval = (user, approved) => {
    router.patch(route('admin.user-approvals.update', user.id), { approved });
};

const formatDateTime = (value) => {
    if (!value) return '-';
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

const isOnline = (value) => {
    if (!value) return false;
    return (Date.now() - new Date(value).getTime()) <= 5 * 60 * 1000;
};
</script>

<template>
    <Head :title="t('userApprovals')" />
    <MainLayout :title="t('userApprovals')">
        <div class="overflow-auto rounded border bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border px-2 py-1 text-left">{{ t('name') }}</th>
                        <th class="border px-2 py-1 text-left">{{ t('email') }}</th>
                        <th class="border px-2 py-1 text-left">{{ t('lastOnline') }}</th>
                        <th class="border px-2 py-1 text-left">{{ t('approved') }}</th>
                        <th class="border px-2 py-1 text-left">{{ t('actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id">
                        <td class="border px-2 py-1">{{ user.name }}</td>
                        <td class="border px-2 py-1">{{ user.email }}</td>
                        <td class="border px-2 py-1">
                            <span class="mr-2">{{ formatDateTime(user.last_seen_at) }}</span>
                            <span
                                v-if="isOnline(user.last_seen_at)"
                                class="rounded border border-green-200 bg-green-50 px-2 py-0.5 text-xs text-green-700"
                            >
                                {{ t('online') }}
                            </span>
                        </td>
                        <td class="border px-2 py-1">{{ user.approved_at ? t('yes') : t('no') }}</td>
                        <td class="border px-2 py-1">
                            <div class="flex gap-2">
                                <button class="rounded border px-2 py-1" @click="setApproval(user, true)">{{ t('approve') }}</button>
                                <button class="rounded border px-2 py-1" @click="setApproval(user, false)">{{ t('revoke') }}</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </MainLayout>
</template>
