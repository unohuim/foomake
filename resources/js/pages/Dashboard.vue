<script setup>
import { Head } from "@inertiajs/vue3";

import DashboardTodo from "../components/DashboardTodo.vue";
import AuthShell from "../layouts/AuthShell.vue";

defineProps({
    shell: {
        type: Object,
        required: true,
    },
    todo: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthShell :shell="shell" title="Dashboard">
        <div class="px-4 pb-10 pt-5 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <DashboardTodo :todo="todo" />
            </div>
        </div>
    </AuthShell>
</template>
