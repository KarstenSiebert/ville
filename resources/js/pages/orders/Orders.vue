<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { index } from '@/routes/orders';
import { Head, router, usePage } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { Dialog, DialogTrigger, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogClose } from "@/components/ui/dialog";
import Button from "@/components/ui/button/Button.vue";
import FlashMessage from '@/components/FlashMessage.vue';
import debounce from "lodash/debounce";
import "@inertiajs/core";

const props = defineProps<{
    orders: {
        data: Order[]
        links: { url: string | null, label: string, active: boolean }[]
        meta: { current_page: number, last_page: number, per_page: number, total: number }
    }
}>()

const formatDate = (value: string | null | undefined) => {
    if (!value || isNaN(Date.parse(value))) return ''
    return new Date(value).toLocaleString('de-DE', {
        year: '2-digit',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    })
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "orders",
        href: index().url,
    },
];

type Order = {
    id: number
    market_id: number
    market_title: string
    market_logo_url: string | null
    outcome_name: string
    outcome_logo_url: string | null
    share_amount: number
    limit_price: number
    base_token_name: string
    decimals: number
    type: string
    filled: number
    status: string
    valid_until: string
}

const page = usePage()

const urlParams = new URLSearchParams(window.location.search)
const searchQuery = ref(urlParams.get("search") || "")

const sortField = ref<keyof Order>("id")
const sortAsc = ref(true)

function sort(field: keyof Order) {
    if (sortField.value === field) sortAsc.value = !sortAsc.value
    else {
        sortField.value = field
        sortAsc.value = true
    }
}

const sortedOrders = computed(() => {
    return [...editableOrders.value].sort((a, b) => {
        let valA = a[sortField.value] ?? (sortField.value === "id" ? 0 : "")
        let valB = b[sortField.value] ?? (sortField.value === "id" ? 0 : "")

        if (valA < valB) return sortAsc.value ? -1 : 1
        if (valA > valB) return sortAsc.value ? 1 : -1
        return 0
    })
})

function openMarket(marketId: number) {
    router.get(`/marketdetails/${marketId}`);
}

const triggerSearch = debounce(() => {
    const query = searchQuery.value.trim();

    router.get(
        "/orders",
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

const safeOrders = computed(() => Array.isArray(props.orders?.data) ? props.orders.data : []);

const editableOrders = ref<Order[]>(Array.isArray(props.orders?.data) ?
    safeOrders.value.map((a) => ({
        ...a,
    }))
    : []
);

watch(
    () => props.orders.data,
    (newOrders) => {
        editableOrders.value = Array.isArray(newOrders)
            ? newOrders.map(o => ({ ...o }))
            : []
    },
    { immediate: true }
)

const orderToDelete = ref<Order | null>(null);

function confirmDelete(order: Order) {
    orderToDelete.value = order;
}

function deleteOrderConfirmed() {
    if (!orderToDelete.value) return;

    const previousOrder = [...editableOrders.value];

    editableOrders.value = editableOrders.value.filter(
        (c) => c.id !== orderToDelete.value!.id
    );

    router.delete(`/orders/${orderToDelete.value.id}`, {
        data: {
            order: orderToDelete.value
        },
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onError: () => {
            editableOrders.value = previousOrder;
        },
        onSuccess: () => {
            orderToDelete.value = null;
        },
    });
}

const pagesToShow = computed<(number | string)[]>(() => {
    const total = props.orders.meta.last_page
    const current = props.orders.meta.current_page
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
        '/orders',
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

    <Head :title="$t('orders')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative text-xs flex flex-col gap-4 overflow-x-auto rounded-xl p-4">

            <div class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow">

                <div class="absolute top-2 left-1/2 -translate-x-1/2 z-20 w-full max-w-sm">
                    <FlashMessage type="success"
                        :message="page.props.flash?.success ? $t(page.props.flash.success) : ''" />
                    <FlashMessage type="error" :message="page.props.flash?.error ? $t(page.props.flash.error) : ''" />
                </div>


                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $t('orders') }}
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
                                    {{ $t('market') }}</th>
                                <th
                                    class="pl-8 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('outcome') }}</th>
                                <th
                                    class="px-4 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('price') }}</th>
                                <th
                                    class="hidden md:table-cell pr-8 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('filled') }}</th>
                                <th
                                    class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('type') }}</th>
                                <th
                                    class="hidden md:table-cell px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('status') }}</th>
                                <th
                                    class="hidden md:table-cell px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('valid_until') }}</th>
                                <th
                                    class="px-4 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('action') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="order in sortedOrders" :key="order.id"
                                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
                                <td
                                    class="hidden md:table-cell px-4 py-2 text-sm text-gray-900 dark:text-gray-200 truncate max-w-xs cursor-default">
                                    <a :href="'/marketdetails/' + order.market_id"
                                        class="flex items-center justify-center space-x-2 mx-auto group transition-shadow duration-200 rounded">
                                        <img v-if="order.market_logo_url" :src="order.market_logo_url" alt="logo"
                                            class="w-8 h-8 rounded group-hover:scale-105 transition-transform duration-200" />
                                    </a>
                                </td>

                                <td
                                    class="px-4 py-2 justify-left text-sm text-gray-900 dark:text-gray-200 whitespace-nowrap">
                                    <a :href="'/marketdetails/' + order.market_id"
                                        class="flex items-center space-x-2 mx-auto group transition-shadow duration-200 rounded">
                                        <img v-if="order.outcome_logo_url" :src="order.outcome_logo_url" alt="logo"
                                            class="w-8 h-8 rounded group-hover:scale-105 transition-transform duration-200" />
                                        <span
                                            class="truncate transition-colors duration-200 cursor-pointer group-hover:text-blue-600">
                                            {{ order.outcome_name }}
                                        </span>
                                    </a>
                                </td>

                                <td
                                    class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-200 truncate max-w-xs cursor-default">
                                    <tt>{{
                                        (order.base_token_name === "ADA"
                                            ? order.limit_price / 1e6
                                            : order.limit_price / Math.pow(10, order.decimals)
                                        ).toLocaleString("en-US", {
                                            minimumFractionDigits: order.decimals > 6 ? 6 : order.decimals,
                                            maximumFractionDigits: order.decimals > 6 ? 6 : order.decimals
                                        })
                                    }}</tt>
                                </td>
                                <td
                                    class="hidden md:table-cell px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-200 truncate max-w-xs cursor-default">
                                    <tt>{{ order.filled }} / {{ order.share_amount }}</tt>
                                </td>
                                <td
                                    class="px-4 py-2 text-sm text-center text-gray-900 dark:text-gray-200 truncate max-w-xs cursor-default">
                                    <tt>{{ order.type }}</tt>
                                </td>
                                <td class="hidden md:table-cell px-4 py-2 text-sm text-center truncate max-w-xs cursor-default"
                                    :class="{
                                        'text-green-600 dark:text-green-400': order.status === 'OPEN',
                                        'text-yellow-500 dark:text-yellow-400': order.status === 'PARTIAL',
                                        'text-gray-500 dark:text-gray-400': order.status === 'FILLED' || order.status === 'CANCELED',
                                        'text-red-600 dark:text-red-400': order.status === 'EXPIRED'
                                    }">
                                    <tt>{{ order.status }}</tt>
                                </td>
                                <td
                                    class="hidden md:table-cell px-4 py-2 text-sm text-center text-gray-900 dark:text-gray-200 truncate max-w-xs cursor-default">
                                    <span class="font-mono py-0.5">
                                        {{ formatDate(order.valid_until) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right cursor-default">
                                    <button @click="openMarket(order.market_id)"
                                        class="relative group text-blue-500 hover:text-blue-600 dark:hover:text-blue-400 p-1 cursor-pointer">
                                        <svg xmlns=" http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-link-icon lucide-link h-4 w-4">
                                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" />
                                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
                                        </svg>
                                        <span
                                            class="absolute bottom-full mb-1 left transform -translate-x-[80%] bg-gray-700 text-white text-xs rounded py-1 px-2 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">
                                            {{ $t('open_market') }}
                                        </span>
                                    </button>

                                    <Dialog>
                                        <DialogTrigger as-child>
                                            <button type="button" @click="confirmDelete(order)"
                                                class="relative group text-red-500 hover:text-red-700 dark:hover:text-red-400 p-1 cursor-pointer">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-trash-icon lucide-trash h-4 w-4">
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" />
                                                    <path d="M3 6h18" />
                                                    <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                                </svg>
                                                <span
                                                    class="absolute bottom-full mb-1 left transform -translate-x-[80%] bg-gray-700 text-white text-xs rounded py-1 px-2 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">
                                                    {{ $t('delete_order') }}
                                                </span>
                                            </button>

                                        </DialogTrigger>
                                        <DialogContent v-if="orderToDelete && orderToDelete.id === order.id">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    {{ order.market_title }} → {{ order.outcome_name }}
                                                </DialogTitle>
                                                <DialogDescription>
                                                    {{ $t('this_action_cannot_be_undone') }}
                                                </DialogDescription>
                                            </DialogHeader>
                                            <DialogFooter class="gap-8">
                                                <DialogClose as-child>
                                                    <Button variant="secondary">{{ $t('cancel') }}</Button>
                                                </DialogClose>
                                                <Button variant="destructive" @click="deleteOrderConfirmed">
                                                    {{ $t('delete_order') }}
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                </td>

                            </tr>
                            <tr v-if="!sortedOrders.length">
                                <td colspan="10" class="text-center py-4 text-gray-500">
                                    {{ $t('no_orders_found') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="sortedOrders.length > 0 && props.orders.meta.last_page > 1"
                        class="flex justify-center mt-4 mb-4 space-x-1">
                        <button v-if="props.orders.meta.current_page > 1"
                            @click="goTo(props.orders.meta.current_page - 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700">{{
                                $t('prev') }}</button>
                        <template v-for="page in pagesToShow" :key="page">
                            <span v-if="page === '...'" class="px-3 py-1 text-gray-500 select-none">...</span>
                            <button v-else @click="goTo(Number(page))" class="px-3 py-1 border rounded"
                                :class="page === props.orders.meta.current_page ? 'bg-blue-500 text-white' : 'bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700'">
                                {{ page }}
                            </button>
                        </template>
                        <button v-if="props.orders.meta.current_page < props.orders.meta.last_page"
                            @click="goTo(props.orders.meta.current_page + 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700">{{
                                $t('next') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>