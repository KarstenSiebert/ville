<script setup lang="ts">
import { ref, computed, watch } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { index } from '@/routes/markets';
import { type BreadcrumbItem } from '@/types';
import { Dialog, DialogTrigger, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogClose } from "@/components/ui/dialog";
import Button from "@/components/ui/button/Button.vue";
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import FlashMessage from "@/components/FlashMessage.vue";
import Checkbox from "@/components/ui/checkbox/Checkbox.vue";
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
    { title: 'markets', href: index().url },
];

interface Outcome {
    id: number;
    name: string;
    buyAmount: number;
    priceDisplay: string | number;
}

type Market = {
    id: number
    title: string
    category: string | null
    description: string | null
    close_time: string
    liquidity_b: number
    outcomes_count: number
    max_trade_amount: number
    logo_url?: string
    allow_limit_orders: boolean
    status: string
    outcomes: Outcome[];
}

const props = defineProps<{
    assets: {
        data: Market[]
        links: { url: string | null, label: string, active: boolean }[]
        meta: { current_page: number, last_page: number, per_page: number, total: number }
        user: { can_create: boolean, can_resolve: boolean, can_close: boolean, can_cancel: boolean, can_delete: boolean }
    }
}>()

const marketToResolve = ref<Market | null>(null)
const selectedOutcome = ref<number | null>(null)
const resolveDialogOpen = ref(false)

async function resolveMarketConfirmed(marketId: number) {
    if (!selectedOutcome.value) return

    router.post(`admin/markets/${marketId}/resolve`, {
        winning_outcome_id: selectedOutcome.value,
    },
        {
            preserveScroll: true,
            onSuccess: () => {
                resolveDialogOpen.value = false;
                marketToResolve.value = null;
            }
        });
}

const marketToCancel = ref<Market | null>(null);
const marketToClose = ref<Market | null>(null);
const marketToDelete = ref<Market | null>(null);

function cancelMarketConfirmed() {
    if (!marketToCancel.value) return;

    editableMints.value = editableMints.value.filter(
        (c) => c.id !== marketToCancel.value!.id
    );

    router.post(`admin/markets/${marketToCancel.value.id}/cancel`)
}

function closeMarketConfirmed() {
    if (!marketToClose.value) return;

    editableMints.value = editableMints.value.filter(
        (c) => c.id !== marketToClose.value!.id
    );

    router.post(`admin/markets/${marketToClose.value.id}/close`)
}

function confirmResolve(market: Market) {
    marketToResolve.value = market
    selectedOutcome.value = null
    resolveDialogOpen.value = true
}

function saveParameterSettings(market: Market) {

    router.post(`/markets/${market.id}/orders`, {
        allow_limit_orders: market.allow_limit_orders,
        max_trade_amount: market.max_trade_amount,
    }, {
        preserveScroll: true,
        preserveState: true,

        onSuccess: () => {
            const updatedMarket = props.assets.data.find(a => a.id === market.id);
            if (updatedMarket) {
                const index = editableMints.value.findIndex(a => a.id === market.id);
                if (index !== -1) {
                    editableMints.value[index].allow_limit_orders = updatedMarket.allow_limit_orders;
                    editableMints.value[index].max_trade_amount = updatedMarket.max_trade_amount;
                }
            }
        }
    });
}

function confirmCancel(market: Market) {
    marketToCancel.value = market;
}
function confirmClose(market: Market) {
    marketToClose.value = market;
}
function confirmDelete(market: Market) {
    marketToDelete.value = market;
}

function deleteMarketConfirmed() {
    if (!marketToDelete.value) return;

    const previousMarket = [...editableMints.value];

    editableMints.value = editableMints.value.filter(
        (c) => c.id !== marketToDelete.value!.id
    );

    router.delete(`markets/${marketToDelete.value.id}`, {
        data: {
            asset: marketToDelete.value
        },
        preserveScroll: true,
        onError: () => {
            editableMints.value = previousMarket;
        },
        onSuccess: () => {
            marketToDelete.value = null;
        },
    });
}

const editableMints = ref<Market[]>([])

watch(
    () => props.assets,
    (newMints) => {
        editableMints.value = newMints.data.map(a => ({
            id: a.id,
            title: a.title,
            category: a.category,
            description: a.description,
            close_time: a.close_time,
            liquidity_b: a.liquidity_b,
            outcomes_count: a.outcomes_count,
            max_trade_amount: a.max_trade_amount,
            status: a.status,
            allow_limit_orders: a.allow_limit_orders,
            logo_url: a.logo_url,
            outcomes: a.outcomes,
        }))
    },
    { immediate: true }
)

const page = usePage()

const form = useForm({
    selected_assets: [] as Market[],
})

const qrDialogOpen = ref(false)
const qrBase64 = ref<string | null>(null)

async function fetchQRCodeBase64(url: string): Promise<string> {
    const response = await fetch(url)

    if (!response.ok) throw new Error('Failed to fetch QR code')

    let base64String = await response.text()

    base64String = base64String.replace(/^"(.*)"$/, '$1');

    if (!base64String.startsWith('data:image')) {
        base64String = `data:image/png;base64,${base64String}`;
    }

    return base64String;
}

async function openQRCodeDialog(market: Market) {
    try {
        const url = `markets/${market.id}/qrcode`

        const base64 = await fetchQRCodeBase64(url)

        qrBase64.value = base64
        qrDialogOpen.value = true

    } catch (error) {
        console.error('Failed to fetch QR code:', error)
    }
}

const searchQuery = ref("")

const sortField = ref<keyof Market>("title")
const sortAsc = ref(true)

function sort(field: keyof Market) {
    if (sortField.value === field) sortAsc.value = !sortAsc.value
    else {
        sortField.value = field
        sortAsc.value = true
    }
}

const sortedMarkets = computed(() => {
    return [...editableMints.value].sort((a, b) => {
        const valA = a[sortField.value] ?? (sortField.value === "title" ? 0 : "")
        const valB = b[sortField.value] ?? (sortField.value === "title" ? 0 : "")

        if (valA < valB) return sortAsc.value ? -1 : 1
        if (valA > valB) return sortAsc.value ? 1 : -1
        return 0
    })
})

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

function goTo(pageNum: number) {
    router.get('/markets', { page: pageNum }, { preserveScroll: true })
}

function submitForm() {
    form.get("/markets/create")
}

</script>

<template>

    <Head :title="$t('markets')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative text-xs flex flex-col gap-4 overflow-x-auto rounded-xl p-4">

            <div class="absolute top-2 left-1/2 -translate-x-1/2 z-20 w-full max-w-sm">
                <FlashMessage type="success" :message="page.props.flash?.success ? $t(page.props.flash.success) : ''" />
                <FlashMessage type="error" :message="page.props.flash?.error ? $t(page.props.flash.error) : ''" />
            </div>

            <!-- Form -->
            <form @submit.prevent="submitForm" class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow">

                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $t('markets') }}
                    </h2>
                    <input v-model="searchQuery" id="search" type="text" :placeholder="$t('search') + '...'"
                        class="ml-4 px-3 py-2 border rounded-lg text-sm w-56 dark:bg-gray-700 dark:text-gray-200" />
                </div>

                <!-- Table -->
                <div class="overflow-x-auto rounded-lg">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th class="px-8 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('title')">
                                    {{ $t('title') }}
                                </th>
                                <th class="hidden md:table-cell px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('category')">
                                    {{ $t('category') }}
                                </th>
                                <th
                                    class="hidden md:table-cell px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('description') }}
                                </th>
                                <th
                                    class="hidden md:table-cell px-4 px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('orders') }}
                                </th>
                                <th
                                    class="pr-8 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('limit') }}
                                </th>
                                <th class="hidden md:table-cell px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('status')">
                                    {{ $t('status') }}
                                </th>
                                <th
                                    class="px-10 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('actions') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="asset in sortedMarkets" :key="asset.id"
                                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
                                <td
                                    class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-200 cursor-default max-w-xs overflow-hidden">

                                    <div
                                        class="flex items-center space-x-2 group transition-shadow duration-200 rounded">
                                        <a :href="`/marketdetails/${asset.id}`" class="flex-shrink-0">
                                            <img v-if="asset.logo_url" :src="asset.logo_url" alt="logo"
                                                class="w-8 h-8 rounded transition-transform duration-200 hover:scale-105" />
                                        </a>
                                        <span @click="openQRCodeDialog(asset)"
                                            class="transition-colors duration-200 truncate max-w-xs overflow-hidden cursor-pointer text-ellipsis hover:text-blue-600">
                                            {{ asset.title }}
                                        </span>
                                    </div>
                                </td>
                                <td class="hidden md:table-cell px-4 py-2 text-sm text-left text-gray-900
                                            dark:text-gray-200 cursor-default max-w-xs overflow-hidden">
                                    {{ $t(asset.category || '') }}
                                </td>
                                <td class="hidden md:table-cell px-4 py-2 text-sm text-left text-gray-900 dark:text-gray-200
                                            truncate max-w-xs overflow-hidden text-ellipsis cursor-default">
                                    {{ asset.description }}
                                </td>
                                <td class="hidden md:table-cell px-4 px-4 py-2 text-center">
                                    <Checkbox v-model="asset.allow_limit_orders" binary
                                        class="w-4 h-4 dark:bg-gray-900 text-gray-900 dark:text-gray-200 dark:border-gray-600"
                                        @update:modelValue="saveParameterSettings(asset)" />
                                </td>
                                <td
                                    class="px-4 py-2 text-right text-gray-900 dark:text-gray-200 cursor-default max-w-xs overflow-hidden">

                                    <input type="number" v-model.number="asset.max_trade_amount" min="1" max="1000"
                                        step="1" @blur="saveParameterSettings(asset)"
                                        class="w-20 px-2 py-1 border rounded text-right" />
                                </td>
                                <td class="hidden md:table-cell px-4 py-2 text-sm text-center text-gray-900 dark:text-gray-200 truncate text-ellipsis 
                                            cursor-default max-w-xs overflow-hidden">
                                    {{ $t(asset.status || '') }}
                                </td>

                                <td class="px-4 py-2 text-right cursor-default">
                                    <a :href="`/analytics/${asset.id}`">
                                        <button type="button"
                                            class="relative group p-1 text-green-500 hover:text-green-700 cursor-pointer">

                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round"
                                                class="lucide lucide-trending-up-down-icon lucide-trending-up-down h-4 w-4">
                                                <path d="M14.828 14.828 21 21" />
                                                <path d="M21 16v5h-5" />
                                                <path d="m21 3-9 9-4-4-6 6" />
                                                <path d="M21 8V3h-5" />
                                            </svg>

                                            <span
                                                class="absolute bottom-full mb-1 left transform -translate-x-[80%] bg-gray-700 text-white text-xs rounded py-1 px-2 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">
                                                {{ $t('market_analytics') }}
                                            </span>
                                        </button>
                                    </a>
                                    <Dialog v-model:open="resolveDialogOpen">
                                        <DialogTrigger as-child>
                                            <button type="button" v-if="['CLOSED'].includes(asset.status)"
                                                @click="confirmResolve(asset)" class="relative group p-1" :class="props.assets.user.can_resolve
                                                    ? 'text-green-500 hover:text-green-700 cursor-pointer'
                                                    : 'text-gray-400 cursor-not-allowed'"
                                                :disabled="!props.assets.user.can_resolve">

                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-hand-coins-icon lucide-hand-coins h-4 w-4">
                                                    <path d="M11 15h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 17" />
                                                    <path
                                                        d="m7 21 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9" />
                                                    <path d="m2 16 6 6" />
                                                    <circle cx="16" cy="9" r="2.9" />
                                                    <circle cx="6" cy="5" r="3" />
                                                </svg>
                                                <span v-if="props.assets.user.can_resolve"
                                                    class="absolute bottom-full mb-1 left transform -translate-x-[80%] bg-gray-700 text-white text-xs rounded py-1 px-2 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">
                                                    {{ $t('resolve_market') }}
                                                </span>
                                            </button>
                                        </DialogTrigger>
                                        <DialogContent v-if="marketToResolve?.id === asset.id">
                                            <DialogHeader>
                                                <DialogTitle>{{ $t('resolve_market') }}</DialogTitle>
                                                <DialogDescription>
                                                    {{ asset.title }}
                                                </DialogDescription>
                                            </DialogHeader>

                                            <select v-model="selectedOutcome"
                                                class="w-full p-2 rounded mb-3 bg-white text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600 dark:focus:ring-blue-400">

                                                <option v-for="o in asset.outcomes" :key="o.id" :value="o.id"
                                                    class="dark:bg-gray-800 dark:text-gray-100">
                                                    {{ o.name }}
                                                </option>
                                            </select>

                                            <DialogFooter class="gap-4">
                                                <DialogClose as-child>
                                                    <Button variant="secondary">{{ $t('cancel') }}</Button>
                                                </DialogClose>
                                                <Button variant="default" :disabled="!selectedOutcome"
                                                    @click="resolveMarketConfirmed(asset.id)">
                                                    {{ $t('confirm') }}
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>

                                    <Dialog>
                                        <DialogTrigger as-child>
                                            <button type="button" v-if="['OPEN'].includes(asset.status)"
                                                @click="confirmCancel(asset)" class="relative group p-1" :class="props.assets.user.can_cancel
                                                    ? 'text-orange-500 hover:text-orange-700 dark:hover:text-orange-400 cursor-pointer'
                                                    : 'text-gray-400 cursor-not-allowed'
                                                    " :disabled="!props.assets.user.can_cancel"
                                                :aria-label="$t('cancel_market')">

                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-circle-off-icon lucide-circle-off h-4 w-4">
                                                    <path d="m2 2 20 20" />
                                                    <path d="M8.35 2.69A10 10 0 0 1 21.3 15.65" />
                                                    <path d="M19.08 19.08A10 10 0 1 1 4.92 4.92" />
                                                </svg>

                                                <span v-if="props.assets.user.can_cancel"
                                                    class="absolute bottom-full mb-1 left transform -translate-x-[80%] bg-gray-700 text-white text-xs rounded py-1 px-2 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">
                                                    {{ $t('cancel_market') }}
                                                </span>
                                            </button>
                                        </DialogTrigger>
                                        <DialogContent v-if="marketToCancel && marketToCancel.id === asset.id">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    {{ $t('cancel') }}: {{
                                                        asset.title }}
                                                </DialogTitle>
                                                <DialogDescription>
                                                    {{ $t('this_action_cannot_be_undone') }}
                                                </DialogDescription>
                                            </DialogHeader>
                                            <DialogFooter class="gap-8">
                                                <DialogClose as-child>
                                                    <Button variant="secondary">{{ $t('cancel') }}</Button>
                                                </DialogClose>
                                                <Button variant="destructive" @click="cancelMarketConfirmed">
                                                    {{ $t('proceed') }}
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>

                                    <Dialog>
                                        <DialogTrigger as-child>
                                            <button type="button" v-if="asset.status === 'OPEN'"
                                                @click="confirmClose(asset)" class="relative group p-1" :class="props.assets.user.can_close
                                                    ? 'text-blue-500 hover:text-blue-700 dark:hover:text-blue-400 cursor-pointer'
                                                    : 'text-gray-400 cursor-not-allowed'"
                                                :disabled="!props.assets.user.can_close"
                                                :aria-label="$t('close_market')">

                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-calendar-off-icon lucide-calendar-off h-4 w-4">
                                                    <path
                                                        d="M4.2 4.2A2 2 0 0 0 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 1.82-1.18" />
                                                    <path d="M21 15.5V6a2 2 0 0 0-2-2H9.5" />
                                                    <path d="M16 2v4" />
                                                    <path d="M3 10h7" />
                                                    <path d="M21 10h-5.5" />
                                                    <path d="m2 2 20 20" />
                                                </svg>

                                                <span v-if="props.assets.user.can_close"
                                                    class="absolute bottom-full mb-1 left transform -translate-x-[80%] bg-gray-700 text-white text-xs rounded py-1 px-2 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">
                                                    {{ $t('close_market') }}
                                                </span>
                                            </button>
                                        </DialogTrigger>
                                        <DialogContent v-if="marketToClose && marketToClose.id === asset.id">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    {{ $t('close') }}: {{
                                                        asset.title }}
                                                </DialogTitle>
                                                <DialogDescription>
                                                    {{ $t('this_action_cannot_be_undone') }}
                                                </DialogDescription>
                                            </DialogHeader>
                                            <DialogFooter class="gap-8">
                                                <DialogClose as-child>
                                                    <Button variant="secondary">{{ $t('cancel') }}</Button>
                                                </DialogClose>
                                                <Button variant="destructive" @click="closeMarketConfirmed">
                                                    {{ $t('close') }}
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>

                                    <Dialog>
                                        <DialogTrigger as-child>
                                            <button type="button" @click="confirmDelete(asset)"
                                                class="relative group p-1" :class="props.assets.user.can_delete
                                                    ? 'text-red-500 hover:text-red-700 dark:hover:text-red-400 cursor-pointer'
                                                    : 'text-gray-400 cursor-not-allowed'"
                                                :disabled="!props.assets.user.can_delete"
                                                :aria-label="$t('delete_market')">

                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-trash-icon lucide-trash h-4 w-4">
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" />
                                                    <path d="M3 6h18" />
                                                    <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                                </svg>

                                                <span v-if="props.assets.user.can_delete"
                                                    class="absolute bottom-full mb-1 left transform -translate-x-[80%] bg-gray-700 text-white text-xs rounded py-1 px-2 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">
                                                    {{ $t('delete_market') }}
                                                </span>
                                            </button>

                                        </DialogTrigger>
                                        <DialogContent v-if="marketToDelete && marketToDelete.id === asset.id">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    {{ $t('delete') }}: {{
                                                        asset.title }}
                                                </DialogTitle>
                                                <DialogDescription>
                                                    {{ $t('this_action_cannot_be_undone') }}
                                                </DialogDescription>
                                            </DialogHeader>
                                            <DialogFooter class="gap-8">
                                                <DialogClose as-child>
                                                    <Button variant="secondary">{{ $t('cancel') }}</Button>
                                                </DialogClose>
                                                <Button variant="destructive" @click="deleteMarketConfirmed">
                                                    {{ $t('delete') }}
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                </td>
                            </tr>
                            <tr v-if="!sortedMarkets.length">
                                <td colspan="6" class="text-center py-4 text-gray-500">
                                    {{ $t('no_markets_found') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="sortedMarkets.length > 0 && props.assets.meta.last_page > 1"
                        class="flex justify-center mt-4 mb-4 space-x-1">
                        <button type="button" v-if="props.assets.meta.current_page > 1"
                            @click="goTo(props.assets.meta.current_page - 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">{{ $t('prev') }}</button>
                        <template v-for="page in pagesToShow" :key="page">
                            <span v-if="page === '...'" class="px-3 py-1 text-gray-500 select-none">...</span>
                            <button type="button" v-else @click="goTo(Number(page))" class="px-3 py-1 border rounded"
                                :class="page === props.assets.meta.current_page ? 'bg-blue-500 text-white' : 'bg-gray-200 hover:bg-gray-300'">
                                {{ page }}
                            </button>
                        </template>
                        <button type="button" v-if="props.assets.meta.current_page < props.assets.meta.last_page"
                            @click="goTo(props.assets.meta.current_page + 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">{{
                                $t('next') }}</button>
                    </div>
                </div>

                <div class="h-5"></div>

                <div class="mt-4 flex justify-end">
                    <Button type="submit"
                        class="px-4 py-2 rounded-lg shadow cursor-pointer text-white bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed disabled:opacity-70"
                        :disabled="form.processing || !props.assets.user.can_create">
                        {{ $t('create_market') }}
                    </Button>
                </div>
            </form>
        </div>
        <Dialog v-model:open="qrDialogOpen">
            <DialogContent class="max-w-sm flex flex-col items-center justify-center">
                <DialogHeader>
                    <DialogTitle>{{ $t('qr_code') }}</DialogTitle>
                    <DialogDescription>{{ $t('scan_QR_code_to_see_market_details') }}</DialogDescription>
                </DialogHeader>

                <div v-if="qrBase64" class="flex justify-center items-center p-4">
                    <img :src="qrBase64" :alt="$t('qr_code')" class="max-w-xs rounded-lg shadow-lg" />
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
