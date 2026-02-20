<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';

const props = defineProps({
    title: {
        type: String,
        default: '',
    },
});

const { t, locale, switchLocale } = useI18n();
const page = usePage();

const user = computed(() => page.props.auth?.user || null);
const canManage = computed(() => !!user.value && ['admin', 'super_admin'].includes(user.value.role));
const isSuperAdmin = computed(() => user.value?.role === 'super_admin');
const canAccessDisplay = computed(() => {
    if (!user.value) {
        return false;
    }

    if (user.value.role === 'super_admin') {
        return true;
    }

    return user.value.role === 'admin' && !!user.value.approved_at;
});
const homeHref = computed(() => user.value ? route('dashboard') : '/');
const onlineUsers = computed(() => page.props.presence?.online_users || []);
const onlineCount = computed(() => page.props.presence?.online_count || 0);
const visitorsOnlineCount = computed(() => page.props.presence?.visitors_online_count || 0);
const roleLabel = (role) => {
    if (role === 'super_admin') return t('roleSuperAdmin');
    if (role === 'admin') return t('roleAdmin');
    if (role === 'viewer') return t('roleViewer');
    return role || '-';
};
const formatDateTime = (value) => {
    if (!value) return '-';
    if (typeof value === 'string' && value.includes('T')) {
        return value.slice(0, 19).replace('T', ' ');
    }
    return String(value);
};
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <nav class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="homeHref" class="text-lg font-semibold">{{ t('siteTitle') }}</Link>
                    <Link v-if="canManage" :href="route('admin.tournaments.index')" class="rounded border px-2 py-1 text-sm">{{ t('tournaments') }}</Link>
                    <Link v-if="canManage" :href="route('admin.rounds.index')" class="rounded border px-2 py-1 text-sm">{{ t('rounds') }}</Link>
                    <Link v-if="canManage" :href="route('admin.teams.index')" class="rounded border px-2 py-1 text-sm">{{ t('teams') }}</Link>
                    <Link v-if="canManage" :href="route('control.index')" class="rounded border px-2 py-1 text-sm">{{ t('control') }}</Link>
                    <Link v-if="canAccessDisplay" :href="route('display.index')" class="rounded border px-2 py-1 text-sm">{{ t('display') }}</Link>
                    <Link :href="route('timetable.index')" class="rounded border px-2 py-1 text-sm">{{ t('timetable') }}</Link>
                    <Link v-if="isSuperAdmin" :href="route('admin.user-approvals.index')" class="rounded border px-2 py-1 text-sm">{{ t('userApprovals') }}</Link>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <span
                        v-if="isSuperAdmin"
                        class="rounded border border-indigo-200 bg-indigo-50 px-2 py-1 text-xs text-indigo-700"
                    >
                        {{ t('visitorsOnlineLabel') }}: {{ visitorsOnlineCount }}
                    </span>
                    <details v-if="isSuperAdmin" class="relative">
                        <summary class="cursor-pointer rounded border px-2 py-1 list-none">{{ t('onlineCountLabel') }}: {{ onlineCount }}</summary>
                        <div class="absolute right-0 z-20 mt-1 w-80 rounded border bg-white p-2 shadow">
                            <div v-if="onlineUsers.length === 0" class="text-xs text-gray-500">{{ t('noOnlineUsers') }}</div>
                            <div v-else class="max-h-64 overflow-auto">
                                <div v-for="onlineUser in onlineUsers" :key="onlineUser.id" class="border-b px-2 py-1 last:border-b-0">
                                    <div class="text-sm font-medium">{{ onlineUser.name }}</div>
                                    <div class="text-xs text-gray-600">{{ onlineUser.email }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ roleLabel(onlineUser.role) }} • {{ t('lastSeenLabel') }}: {{ formatDateTime(onlineUser.last_seen_at) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </details>
                    <button class="rounded border px-2 py-1" @click="switchLocale('en')" :disabled="locale === 'en'">EN</button>
                    <button class="rounded border px-2 py-1" @click="switchLocale('zh')" :disabled="locale === 'zh'">中文</button>
                    <template v-if="user">
                        <Link :href="route('profile.edit')" class="rounded border px-2 py-1">{{ t('profile') }}</Link>
                        <Link :href="route('logout')" method="post" as="button" class="rounded border px-2 py-1">{{ t('logout') }}</Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="rounded border px-2 py-1">{{ t('login') }}</Link>
                    </template>
                </div>
            </div>
        </nav>

        <main class="mx-auto max-w-7xl px-4 py-6">
            <h1 v-if="title" class="mb-4 text-2xl font-bold">{{ title }}</h1>
            <div v-if="$page.props.flash?.success" class="mb-3 rounded border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-700">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="mb-3 rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $page.props.flash.error }}
            </div>
            <slot />
        </main>
    </div>
</template>
