<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const page = usePage();
</script>

<template>
    <Head title="Dashboard" />

    <MainLayout title="Dashboard">
        <div class="rounded border bg-white p-4 text-sm text-gray-700">
            <p>
                Signed in as
                <strong>{{ page.props.auth.user?.name }}</strong>
                ({{ page.props.auth.user?.role }}).
            </p>
            <p class="mt-2" v-if="page.props.auth.user?.role === 'admin' && !page.props.auth.user?.approved_at">
                Your admin account is pending superadmin approval.
            </p>
        </div>
    </MainLayout>
</template>
