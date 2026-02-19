<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, computed, onUnmounted, watch } from 'vue';
import { index } from '@/routes/history';
import { Head, router } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import debounce from "lodash/debounce";
import "@inertiajs/core";

type TxRow = {
    tx_id: number
    tx_hash: string
    timestamp: string
    balance_change: string
}

const props = defineProps<{
    history: {
        data: TxRow[]
        links: { url: string | null, label: string, active: boolean }[]
        meta: { current_page: number, last_page: number, per_page: number, total: number }
    }
}>()

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "history",
        href: index().url,
    },
];

let intervalId: ReturnType<typeof setInterval> | null = null

intervalId = setInterval(() => {
    router.reload({
        only: ["history"],
        data: { search: searchQuery.value || undefined },
    });
}, 60000)

onUnmounted(() => {
    if (intervalId) clearInterval(intervalId);
})

const urlParams = new URLSearchParams(window.location.search)
const searchQuery = ref(urlParams.get("search") || "")

const triggerSearch = debounce(() => {
    const query = searchQuery.value.trim();

    router.get(
        "/history",
        query ? { page: 1, search: query } : { page: 1 },
        {
            preserveScroll: true,
            replace: true,
            preserveState: false,
        }
    );
}, 800);

watch(searchQuery, () => {
    triggerSearch();
});

watch(
    () => window.location.search,
    () => {
        const params = new URLSearchParams(window.location.search);
        const newSearch = params.get("search") || "";
        if (newSearch !== searchQuery.value) {
            searchQuery.value = newSearch;
        }
    }
);

const pagesToShow = computed<(number | string)[]>(() => {
    const total = props.history.meta.last_page
    const current = props.history.meta.current_page
    const delta = 2
    const range: number[] = []
    const rangeWithDots: (number | string)[] = []
    let last: number | undefined

    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) {
            range.push(i)
        }
    }

    for (const i of range) {
        if (last !== undefined) {
            if (i - last === 2) rangeWithDots.push(last + 1)
            else if (i - last > 2) rangeWithDots.push('...')
        }
        rangeWithDots.push(i)
        last = i
    }

    return rangeWithDots
})

function goTo(page: number) {
    router.get(
        '/history',
        {
            page,
            search: searchQuery.value || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        }
    )
}

</script>

<template>

    <Head :title="$t('history')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative text-xs flex flex-col gap-4 overflow-x-auto rounded-xl p-4">

            <div class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow">

                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $t('history') }}
                    </h2>
                    <input v-model="searchQuery" id="search" type="text" :placeholder="$t('search') + '...'"
                        class="ml-4 px-3 py-2 border rounded-lg text-sm w-56 dark:bg-gray-700 dark:text-gray-200" />
                </div>

                <div class="overflow-x-auto rounded-lg">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">

                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th
                                    class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('tx_hash') }}</th>

                                <th
                                    class="hidden md:table-cell px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('date') }}</th>

                                <th
                                    class="hidden md:table-cell pr-5 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('change') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="tx in props.history.data" :key="tx.tx_id">
                                <td
                                    class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200 truncate max-w-xs cursor-default">
                                    <component :is="'a'" :href="'https://cexplorer.io/tx/' + tx.tx_hash" target="_blank"
                                        rel="noopener noreferrer"
                                        class="flex items-center space-x-2 group transition-shadow duration-200 rounded">
                                        <span class="py-0.5 font-mono transition-colors duration-200"
                                            :class="{ 'group-hover:text-blue-600': tx.tx_hash }">
                                            {{ tx.tx_hash }}
                                        </span>
                                    </component>
                                </td>
                                <td
                                    class="hidden md:table-cell px-4 py-2 text-sm text-center text-gray-900 dark:text-gray-200 truncate max-w-xs cursor-default">
                                    <span class="font-mono py-0.5">{{ new Date(tx.timestamp).toLocaleString() }}</span>
                                </td>

                                <td class="hidden md:table-cell pr-5 py-2 text-sm text-right text-gray-900 dark:text-gray-200 truncate max-w-xs cursor-default"
                                    :class="Number(tx.balance_change) >= 0 ? 'text-green-600' : 'text-red-600'">
                                    <span class="font-mono py-0.5">{{ (Number(tx.balance_change) /
                                        1000000).toLocaleString("en-US", {
                                            minimumFractionDigits: 6,
                                            maximumFractionDigits: 6
                                        }) }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!history.data.length">
                                <td colspan="4" class="text-center py-4 text-gray-500">
                                    {{ $t('no_assets_found') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="props.history.data.length > 0 && props.history.meta.last_page > 1"
                        class="flex justify-center mt-4 mb-4 space-x-1">
                        <button v-if="props.history.meta.current_page > 1"
                            @click="goTo(props.history.meta.current_page - 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700">{{
                                $t('prev') }}</button>
                        <template v-for="page in pagesToShow" :key="page">
                            <span v-if="page === '...'" class="px-3 py-1 text-gray-500 select-none">...</span>
                            <button v-else @click="goTo(Number(page))" class="px-3 py-1 border rounded"
                                :class="page === props.history.meta.current_page ? 'bg-blue-500 text-white' : 'bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700'">
                                {{ page }}
                            </button>
                        </template>
                        <button v-if="props.history.meta.current_page < props.history.meta.last_page"
                            @click="goTo(props.history.meta.current_page + 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700">{{
                                $t('next') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
