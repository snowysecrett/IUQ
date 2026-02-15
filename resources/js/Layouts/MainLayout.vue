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
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <nav class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="route('dashboard')" class="text-lg font-semibold">IUQ Scoring Site</Link>
                    <Link v-if="canManage" :href="route('admin.tournaments.index')" class="rounded border px-2 py-1 text-sm">{{ t('tournaments') }}</Link>
                    <Link v-if="canManage" :href="route('admin.rounds.index')" class="rounded border px-2 py-1 text-sm">{{ t('rounds') }}</Link>
                    <Link v-if="canManage" :href="route('admin.teams.index')" class="rounded border px-2 py-1 text-sm">{{ t('teams') }}</Link>
                    <Link v-if="canManage" :href="route('control.index')" class="rounded border px-2 py-1 text-sm">{{ t('control') }}</Link>
                    <Link :href="route('display.index')" class="rounded border px-2 py-1 text-sm">{{ t('display') }}</Link>
                    <Link :href="route('timetable.index')" class="rounded border px-2 py-1 text-sm">{{ t('timetable') }}</Link>
                    <Link v-if="isSuperAdmin" :href="route('admin.user-approvals.index')" class="rounded border px-2 py-1 text-sm">{{ t('userApprovals') }}</Link>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <button class="rounded border px-2 py-1" @click="switchLocale('en')" :disabled="locale === 'en'">EN</button>
                    <button class="rounded border px-2 py-1" @click="switchLocale('zh')" :disabled="locale === 'zh'">中文</button>
                    <template v-if="user">
                        <Link :href="route('profile.edit')" class="rounded border px-2 py-1">{{ t('profile') }}</Link>
                        <Link :href="route('logout')" method="post" as="button" class="rounded border px-2 py-1">{{ t('logout') }}</Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="rounded border px-2 py-1">Login</Link>
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
