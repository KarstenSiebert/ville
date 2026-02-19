<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, computed, onUnmounted, watch } from 'vue';
import { index } from '@/routes/archives';
import { Head, router } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import debounce from "lodash/debounce";
import "@inertiajs/core";

type TxRow = {
    tx_id: number
    tx_hash: string
    timestamp: string
    token_name: string
    from_wallet_id: string
    to_wallet_id: string
    sender: string
    receiver: string
    fingerprint: string
    note: string
    type: string
    token_type: string
    quantity: number
    decimals: number
    status: string
    token_logo_url?: string | '/storage/logos/cardano-ada-logo.png'
}

const props = defineProps<{
    archive: {
        data: TxRow[]
        links: { url: string | null, label: string, active: boolean }[]
        meta: { current_page: number, last_page: number, per_page: number, total: number }
    }
}>()

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "archive",
        href: index().url,
    },
];

let intervalId: ReturnType<typeof setInterval> | null = null

intervalId = setInterval(() => {
    router.reload({
        only: ["archive"],
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
        "/archives",
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
    const total = props.archive.meta.last_page
    const current = props.archive.meta.current_page
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
        '/archives',
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

    <Head :title="$t('archive')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative text-xs flex flex-col gap-4 overflow-x-auto rounded-xl p-4">

            <div class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow">

                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $t('archive') }}
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
                                    class="hidden md:table-cell px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('id') }}</th>
                                <th
                                    class="px-8 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('token') }}</th>
                                <th
                                    class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('number') }}</th>
                                <th
                                    class="hidden md:table-cell px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('from') }}</th>
                                <th
                                    class="hidden md:table-cell px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('to') }}</th>
                                <th
                                    class="hidden md:table-cell px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('type') }}</th>
                                <th
                                    class="hidden md:table-cell pr-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('note') }}</th>
                                <th
                                    class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('date') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="tx in props.archive.data" :key="tx.tx_id"
                                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
                                <td class="hidden md:table-cell px-4 py-2 text-center cursor-default">{{ tx.tx_id }}
                                </td>
                                <td
                                    class="px-4 py-2 text-xs text-gray-900 dark:text-gray-200 min-w-[100px] whitespace-nowrap">
                                    <component :is="tx.fingerprint && (tx.token_type == 'BASE') ? 'a' : 'div'"
                                        :href="tx.fingerprint ? 'https://cexplorer.io/asset/' + tx.fingerprint : null"
                                        target="_blank" rel="noopener noreferrer"
                                        class="flex items-center space-x-2 group transition-shadow duration-200 rounded">
                                        <img v-if="tx.token_logo_url" :src="tx.token_logo_url" alt="logo"
                                            class="w-8 h-8 rounded transition-transform duration-200"
                                            :class="{ 'group-hover:scale-105': tx.fingerprint && (tx.token_type == 'BASE') }" />
                                        <span class="transition-colors duration-200 truncate cursor-default"
                                            :class="{ 'group-hover:text-blue-600 cursor-pointer': tx.fingerprint && (tx.token_type == 'BASE') }">
                                            {{ tx.token_name }}
                                        </span>
                                    </component>
                                </td>
                                <td class="mono-font-mono px-4 py-2 text-xs text-right text-gray-900 dark:text-gray-200 truncate max-w-xs cursor-default"
                                    :class="(tx.type == 'internal') ? 'text-green-600' : 'text-red-600'">
                                    {{
                                        (tx.token_name === "ADA"
                                            ? tx.quantity / 1e6
                                            : tx.quantity / Math.pow(10, tx.decimals)
                                        ).toLocaleString("en-US", {
                                            minimumFractionDigits: tx.decimals > 6 ? 6 : tx.decimals,
                                            maximumFractionDigits: tx.decimals > 6 ? 6 : tx.decimals
                                        })
                                    }}
                                </td>
                                <td class="hidden md:table-cell px-4 py-2 text-center cursor-default">
                                    {{ tx.sender ?? tx.from_wallet_id }}
                                </td>
                                <td class="hidden md:table-cell px-4 py-2 text-center cursor-default">
                                    {{ tx.receiver ?? tx.to_wallet_id }}
                                </td>
                                <td class="hidden md:table-cell px-4 py-2 text-center cursor-default">{{ tx.type }}</td>
                                <td
                                    class="hidden md:table-cell px-4 py-2 text-xs text-gray-900 dark:text-gray-200 truncate max-w-xs cursor-default">
                                    <component :is="tx.tx_hash && tx.tx_hash.startsWith('\\x') ? 'a' : 'div'"
                                        :href="tx.tx_hash ? 'https://cexplorer.io/tx/' + tx.tx_hash.slice(2) : null"
                                        target="_blank" rel="noopener noreferrer"
                                        class="flex items-center space-x-2 group transition-shadow duration-200 rounded">
                                        <span class="transition-colors duration-200"
                                            :class="{ 'group-hover:text-blue-600': tx.tx_hash }">
                                            {{ tx.note }}
                                        </span>
                                    </component>
                                </td>
                                <td
                                    class="px-4 py-2 text-sm text-center text-gray-900 dark:text-gray-200 truncate max-w-xs cursor-default">
                                    <span class="font-mono py-0.5">{{ new Date(tx.timestamp).toLocaleString('de-DE', {
                                        year: '2-digit',
                                        month: '2-digit',
                                        day: '2-digit',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        second: '2-digit'
                                    }) }}</span>
                                </td>
                            </tr>
                            <tr v-if="!archive.data.length">
                                <td colspan="10" class="text-center py-4 text-gray-500">
                                    {{ $t('no_assets_found') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="props.archive.data.length > 0 && props.archive.meta.last_page > 1"
                        class="flex justify-center mt-4 mb-4 space-x-1">
                        <button v-if="props.archive.meta.current_page > 1"
                            @click="goTo(props.archive.meta.current_page - 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700">{{
                                $t('prev') }}</button>
                        <template v-for="page in pagesToShow" :key="page">
                            <span v-if="page === '...'" class="px-3 py-1 text-gray-500 select-none">...</span>
                            <button v-else @click="goTo(Number(page))" class="px-3 py-1 border rounded"
                                :class="page === props.archive.meta.current_page ? 'bg-blue-500 text-white' : 'bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700'">
                                {{ page }}
                            </button>
                        </template>
                        <button v-if="props.archive.meta.current_page < props.archive.meta.last_page"
                            @click="goTo(props.archive.meta.current_page + 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700">{{
                                $t('next') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
