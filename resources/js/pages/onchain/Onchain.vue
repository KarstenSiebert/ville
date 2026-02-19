<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { index } from '@/routes/onchain';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { Button } from "@/components/ui/button";
import FlashMessage from '@/components/FlashMessage.vue';
import debounce from "lodash/debounce";
import { is } from 'laravel-permission-to-vuejs';
import "@inertiajs/core"

declare module "@inertiajs/core" {
    interface PageProps {
        flash: {
            success?: string
            error?: string
        }
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'onchain',
        href: index().url,
    },
];

interface Asset {
    policy_id: string | null
    asset_name: string
    asset_hex: string
    fingerprint: string
    quantity: number
    decimals: number
    status: string
    logo_url?: string
}

const canSelectAssets = is('superadmin')

const title = computed(() => {
    if (canSelectAssets) return 'select_coins'
    return 'coins_on_chain'
})

const props = defineProps<{
    assets: {
        data: Asset[]
        links: { url: string | null, label: string, active: boolean }[]
        meta: { current_page: number, last_page: number, per_page: number, total: number }
    }
}>()

const page = usePage()

const safeAssets = computed(() => Array.isArray(props.assets?.data) ? props.assets.data : []);

const editableAssets = ref<Asset[]>(Array.isArray(props.assets?.data) ?
    safeAssets.value.map((a) => ({
        ...a,
        policy_id: a.policy_id,
        asset_name: a.asset_name,
        asset_hex: a.asset_hex,
        fingerprint: a.fingerprint,
        quantity: a.quantity,
        decimals: a.decimals,
        status: a.status,
        logo_url: a.logo_url ?? '/storage/logos/cardano-ada-logo.png'
    }))
    : []
);

const sortField = ref<keyof Asset>("asset_name")
const sortAsc = ref(true)

const selected = ref<string[]>([])

const form = useForm({
    selected_assets: [] as Asset[],
})

function sort(field: keyof Asset) {
    if (sortField.value === field) {
        sortAsc.value = !sortAsc.value
    } else {
        sortField.value = field
        sortAsc.value = true
    }
}

const sortedAssets = computed(() => {
    return [...editableAssets.value].sort((a, b) => {
        let valA = a[sortField.value] ?? (sortField.value === "quantity" ? 0 : "")
        let valB = b[sortField.value] ?? (sortField.value === "quantity" ? 0 : "")

        if (sortField.value === "quantity") {
            valA = Number(valA)
            valB = Number(valB)
        }

        if (valA < valB) return sortAsc.value ? -1 : 1
        if (valA > valB) return sortAsc.value ? 1 : -1
        return 0
    })
})

const allSelected = computed({
    get: () =>
        sortedAssets.value.length > 0 &&
        sortedAssets.value.every(
            (a) => selected.value.includes(a.fingerprint)
        ),
    set: (val: boolean) => {
        if (val) {
            selected.value = sortedAssets.value
                .filter(a => a.asset_name !== "CHIMERA")
                .map(
                    (a) => a.fingerprint
                )
        } else {
            selected.value = []
        }
    },
})

const urlParams = new URLSearchParams(window.location.search)
const searchQuery = ref(urlParams.get("search") || "")

const triggerSearch = debounce(() => {
    const query = searchQuery.value.trim();

    router.get(
        "/onchain",
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
    const total = props.assets.meta.last_page
    const current = props.assets.meta.current_page
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
        '/onchain',
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

const selectAllCheckbox = ref<HTMLInputElement | null>(null)

let intervalId: ReturnType<typeof setInterval> | null = null

watch(selected, () => {
    if (!selectAllCheckbox.value) return
    const total = sortedAssets.value.length
    const checked = selected.value.length
    selectAllCheckbox.value.indeterminate = checked > 0 && checked < total
})

watch(
    () => props.assets,
    (newAssets) => {
        editableAssets.value = newAssets.data.map(a => ({
            policy_id: a.policy_id,
            asset_name: a.asset_name,
            asset_hex: a.asset_hex,
            fingerprint: a.fingerprint,
            quantity: a.quantity,
            decimals: a.decimals,
            status: a.status,
            logo_url: a.logo_url ?? '/storage/logos/cardano-ada-logo.png'
        }))

        const availableKeys = newAssets.data.map(a => a.fingerprint)
        selected.value = selected.value.filter(key => availableKeys.includes(key))
    },
    { immediate: true }
)

onMounted(() => {
    if (!selectAllCheckbox.value) return
    const total = sortedAssets.value.length
    const checked = selected.value.length
    selectAllCheckbox.value.indeterminate = checked > 0 && checked < total
})

function submitForm() {
    form.selected_assets = props.assets.data.filter((a) =>
        selected.value.includes(a.fingerprint)
    )

    form.post("/onchain/create")
}

intervalId = setInterval(() => {
    router.reload({
        only: ["assets"],
        data: { search: searchQuery.value || undefined },
    });
}, 60000)

onUnmounted(() => {
    if (intervalId) clearInterval(intervalId);
})

</script>

<template>

    <Head :title="$t('onchain')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative text-xs flex flex-col gap-4 overflow-x-auto rounded-xl p-4">

            <div class="absolute top-2 left-1/2 -translate-x-1/2 z-20 w-full max-w-sm">
                <FlashMessage type="success" :message="page.props.flash?.success ? $t(page.props.flash.success) : ''" />
                <FlashMessage type="error" :message="page.props.flash?.error ? $t(page.props.flash.error) : ''" />
            </div>

            <form @submit.prevent="submitForm" class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow">

                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $t('onchain') }}
                    </h2>
                    <input v-model="searchQuery" id="search" type="text" :placeholder="$t('search') + '...'"
                        class="ml-4 px-3 py-2 border rounded-lg text-sm w-56 dark:bg-gray-700 dark:text-gray-200" />
                </div>

                <div class="overflow-x-auto rounded-lg">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th v-if="canSelectAssets" class="px-4 py-2 text-center">
                                    <input type="checkbox" id="checkbox" ref="selectAllCheckbox"
                                        v-model="allSelected" />
                                </th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('asset_name')">
                                    {{ $t('token') }}
                                </th>
                                <th class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('quantity')">
                                    {{ $t('number') }}
                                </th>
                                <th class="hidden md:table-cell px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('fingerprint')">
                                    {{ $t('fingerprint') }}
                                </th>
                                <th
                                    class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('status') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="(asset, index) in sortedAssets" :key="asset.fingerprint"
                                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
                                <td v-if="canSelectAssets" class="px-4 py-2 text-center">
                                    <input type="checkbox" :value="asset.fingerprint" :id="`asset-${index}`"
                                        :disabled="asset.asset_name === 'CHIMERA'" v-model="selected" />
                                </td>
                                <td
                                    class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200 min-w-[100px] whitespace-nowrap">
                                    <component :is="asset.asset_name ? 'a' : 'div'"
                                        :href="asset.fingerprint ? '/users?f=' + asset.fingerprint : '/users?f=ADA'"
                                        class="flex items-center space-x-2 group transition-shadow duration-200 rounded">
                                        <img v-if="asset.logo_url" :src="asset.logo_url" alt="logo"
                                            class="w-8 h-8 rounded transition-transform duration-200"
                                            :class="{ 'group-hover:scale-105': asset.fingerprint }" />
                                        <span class="transition-colors duration-200 truncate cursor-pointer"
                                            :class="{ 'group-hover:text-blue-600': asset.fingerprint }">
                                            {{ asset.asset_name }}
                                        </span>
                                    </component>
                                </td>
                                <td
                                    class="px-4 py-2 font-mono text-right text-gray-900 dark:text-gray-200 cursor-default">
                                    <component :is="asset.asset_name ? 'a' : 'div'"
                                        :href="asset.fingerprint ? '/users?f=' + asset.fingerprint : '/users?f=ADA'"
                                        class="space-x-2 group transition-shadow duration-200 rounded">
                                        <span class="transition-colors duration-200 group-hover:text-blue-600">
                                            {{
                                                (asset.asset_name === "ADA"
                                                    ? asset.quantity / 1e6
                                                    : asset.quantity / Math.pow(10, asset.decimals)
                                                ).toLocaleString("en-US", {
                                                    minimumFractionDigits: asset.decimals > 6 ? 6 : asset.decimals,
                                                    maximumFractionDigits: asset.decimals > 6 ? 6 : asset.decimals
                                                })
                                            }}
                                        </span>
                                    </component>
                                </td>
                                <td
                                    class="hidden md:table-cell px-4 py-2 text-gray-900 dark:text-gray-200 truncate max-w-xs">
                                    <component :is="asset.fingerprint ? 'a' : 'div'"
                                        :href="asset.fingerprint ? 'https://cexplorer.io/asset/' + asset.fingerprint : null"
                                        target="_blank" rel="noopener noreferrer"
                                        class="flex items-center space-x-2 group transition-shadow duration-200 rounded">
                                        <span class="transition-colors duration-200"
                                            :class="{ 'group-hover:text-blue-600': asset.fingerprint }">
                                            {{ asset.fingerprint || '\u00A0' }}
                                        </span>
                                    </component>
                                </td>
                                <td class="px-4 py-2 text-center cursor-default">{{ asset.status }}</td>
                            </tr>
                            <tr v-if="!sortedAssets.length">
                                <td colspan="4" class="text-center py-4 text-gray-500">
                                    {{ $t('no_assets_found') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="sortedAssets.length > 0 && props.assets.meta.last_page > 1"
                        class="flex justify-center mt-4 mb-4 space-x-1">
                        <button type="button" v-if="props.assets.meta.current_page > 1"
                            @click="goTo(props.assets.meta.current_page - 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700">{{
                                $t('prev') }}</button>
                        <template v-for="page in pagesToShow" :key="page">
                            <span v-if="page === '...'" class="px-3 py-1 text-gray-500 select-none">...</span>
                            <button type="button" v-else @click="goTo(Number(page))" class="px-3 py-1 border rounded"
                                :class="page === props.assets.meta.current_page ? 'bg-blue-500 text-white' : 'bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700'">
                                {{ page }}
                            </button>
                        </template>
                        <button type="button" v-if="props.assets.meta.current_page < props.assets.meta.last_page"
                            @click="goTo(props.assets.meta.current_page + 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700">{{
                                $t('next') }}</button>
                    </div>
                </div>
                <div v-if="canSelectAssets" class="h-5"></div>
                <div v-if="canSelectAssets" class="mt-4 flex justify-end">
                    <Button type="submit"
                        class="px-4 py-2 rounded-lg shadow cursor-pointer text-white bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed disabled:opacity-70"
                        :disabled="form.processing || selected.length === 0">
                        {{ $t('select_amount') }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
