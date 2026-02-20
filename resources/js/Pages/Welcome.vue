<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';

const page = usePage();
const { t } = useI18n();
const user = computed(() => page.props.auth?.user || null);
const canAccessDisplay = computed(() => {
    if (!user.value) {
        return false;
    }

    if (user.value.role === 'super_admin') {
        return true;
    }

    return user.value.role === 'admin' && !!user.value.approved_at;
});
</script>

<template>
    <Head :title="t('welcomeTitle')" />

    <MainLayout :title="t('welcomeTitle')">
        <div class="grid gap-4 md:grid-cols-2">
            <Link
                v-if="canAccessDisplay"
                :href="route('display.index')"
                class="rounded border bg-white p-4 hover:bg-gray-50"
            >
                {{ t('publicDisplay') }}
            </Link>
            <Link :href="route('timetable.index')" class="rounded border bg-white p-4 hover:bg-gray-50">{{ t('timetableAndResults') }}</Link>
            <Link :href="route('login')" class="rounded border bg-white p-4 hover:bg-gray-50">{{ t('adminLogin') }}</Link>
            <Link :href="route('register')" class="rounded border bg-white p-4 hover:bg-gray-50">{{ t('registerAdmin') }}</Link>
        </div>
    </MainLayout>
</template>
