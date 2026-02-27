<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { useI18n } from '@/composables/useI18n';

const page = usePage();
const { t } = useI18n();
const roleLabel = (role) => {
    if (role === 'super_admin') return t('roleSuperAdmin');
    if (role === 'admin') return t('roleAdmin');
    if (role === 'viewer') return t('roleViewer');
    return role || '-';
};
</script>

<template>
    <Head :title="t('dashboard')" />

    <MainLayout :title="t('dashboard')">
        <div class="rounded border bg-white p-4 text-sm text-gray-700">
            <p>
                {{ t('dashboardSignedInAs') }}
                <strong>{{ page.props.auth.user?.name }}</strong>
                ({{ roleLabel(page.props.auth.user?.role) }}).
            </p>
            <p class="mt-2" v-if="page.props.auth.user?.role === 'admin' && !page.props.auth.user?.approved_at">
                {{ t('adminPendingApproval') }}
            </p>
            <div class="mt-4 border-t pt-3">
                <p class="font-semibold">{{ t('acknowledgementsTitle') }}</p>
                <p class="mt-1">
                    {{ t('acknowledgementsBodyPrefix') }}
                    <a
                        href="https://dev.d31wy9mb32wu6b.amplifyapp.com/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-blue-600 hover:underline"
                    >
                        {{ t('legacySiteLabel') }}
                    </a>
                    {{ t('acknowledgementsBodySuffix') }}
                </p>
            </div>
        </div>
    </MainLayout>
</template>
