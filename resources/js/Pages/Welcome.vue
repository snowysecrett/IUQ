<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
</script>

<template>
    <Head title="Welcome to Cambridge IUQ!" />

    <MainLayout title="Welcome to Cambridge IUQ!">
        <div class="grid gap-4 md:grid-cols-2">
            <Link :href="route('display.index')" class="rounded border bg-white p-4 hover:bg-gray-50">Public Display</Link>
            <Link :href="route('timetable.index')" class="rounded border bg-white p-4 hover:bg-gray-50">Timetable & Results</Link>
            <Link :href="route('login')" class="rounded border bg-white p-4 hover:bg-gray-50">Admin Login</Link>
            <Link :href="route('register')" class="rounded border bg-white p-4 hover:bg-gray-50">Register Admin</Link>
        </div>
    </MainLayout>
</template>
