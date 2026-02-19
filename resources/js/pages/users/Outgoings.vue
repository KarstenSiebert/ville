<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { outgoings } from '@/routes/users';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import FlashMessage from '@/components/FlashMessage.vue';
import debounce from "lodash/debounce";
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
        title: 'outgoing_tokens',
        href: outgoings().url,
    },
];

interface Token {
    token_id: number
    token_name: string
    quantity: number
    decimals: number
}

interface User {
    wallet_id: number
    wallet_address: string
    user_id: number
    user_name: string | null
    payout: string
    tokens: string
}

const props = defineProps<{
    users: {
        data: User[]
        links: { url: string | null, label: string, active: boolean }[]
        meta: { current_page: number, last_page: number, per_page: number, total: number }
    }
    is_admin: number
}>()

const page = usePage()

const isAdmin = computed(() => page.props.is_admin);

const safeUsers = computed(() => Array.isArray(props.users?.data) ? props.users.data : []);

const editableUsers = ref<User[]>(Array.isArray(props.users?.data) ?
    safeUsers.value.map((a) => ({
        ...a,
        user_id: a.user_id,
        wallet_id: a.wallet_id,
        user_name: a.user_name,
        wallet_address: a.wallet_address,
        payout: a.payout,
        tokens: a.tokens
    }))
    : []
);

const sortField = ref<keyof User>("user_name")
const sortAsc = ref(true)

const selected = ref<string[]>([])

const form = useForm({
    selected_assets: [] as User[],
})

function sort(field: keyof User) {
    if (sortField.value === field) {
        sortAsc.value = !sortAsc.value
    } else {
        sortField.value = field
        sortAsc.value = true
    }
}

const sortedUsers = computed(() => {
    return [...editableUsers.value].sort((a, b) => {
        let valA = a[sortField.value] ?? (sortField.value === "user_id" ? 0 : "")
        let valB = b[sortField.value] ?? (sortField.value === "user_id" ? 0 : "")

        if (sortField.value === "user_id") {
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
        sortedUsers.value.length > 0 &&
        sortedUsers.value.every(
            (a) => selected.value.includes(a.wallet_address)
        ),
    set: (val: boolean) => {
        if (val) {
            selected.value = sortedUsers.value.map(
                (a) => a.wallet_address
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
        "/users/outgoings",
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
    const total = props.users.meta.last_page
    const current = props.users.meta.current_page
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
        '/users/outgoings',
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

watch(selected, () => {
    if (!selectAllCheckbox.value) return
    const total = sortedUsers.value.length
    const checked = selected.value.length
    selectAllCheckbox.value.indeterminate = checked > 0 && checked < total
})

watch(
    () => props.users,
    (newUsers) => {
        editableUsers.value = newUsers.data.map(a => ({
            user_id: a.user_id,
            wallet_id: a.wallet_id,
            user_name: a.user_name,
            wallet_address: a.wallet_address,
            payout: a.payout,
            tokens: a.tokens
        }))

        const availableKeys = newUsers.data.map(a => a.wallet_address)
        selected.value = selected.value.filter(key => availableKeys.includes(key))
    },
    { immediate: true }
)

onMounted(() => {
    if (!selectAllCheckbox.value) return
    const total = sortedUsers.value.length
    const checked = selected.value.length
    selectAllCheckbox.value.indeterminate = checked > 0 && checked < total
})

const users = ref(props.users)

function parseTokens(tokensStr: string): Token[] {
    try {
        return JSON.parse(tokensStr) as Token[]
    } catch (e) {
        return []
    }
}

function formatQuantity(quantity: number, decimals: number): string {

    return (quantity / Math.pow(10, decimals)
    ).toLocaleString("en-US", {
        minimumFractionDigits: decimals > 6 ? 6 : decimals,
        maximumFractionDigits: decimals > 6 ? 6 : decimals
    })
}

</script>

<template>

    <Head :title="$t('onchain_requests')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative text-xs flex flex-col gap-4 overflow-x-auto rounded-xl p-4">

            <div class="absolute top-2 left-1/2 -translate-x-1/2 z-20 w-full max-w-sm">
                <FlashMessage type="success" :message="page.props.flash?.success ? $t(page.props.flash.success) : ''" />
                <FlashMessage type="error" :message="page.props.flash?.error ? $t(page.props.flash.error) : ''" />
            </div>

            <div class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow">

                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $t('outgoing_requests') }}
                    </h2>
                    <input v-if="isAdmin" v-model="searchQuery" id="search" type="text"
                        :placeholder="$t('search') + '...'"
                        class="ml-4 px-3 py-2 border rounded-lg text-sm w-56 dark:bg-gray-700 dark:text-gray-200" />
                </div>

                <div class="overflow-x-auto rounded-lg">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th v-if="isAdmin"
                                    class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('user_name')">
                                    {{ $t('name') }}
                                </th>
                                <th class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('payout')">
                                    {{ $t('payout_wallet_address') }}
                                </th>
                                <th
                                    class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer">
                                    {{ $t('tokens') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="(user) in sortedUsers" :key="user.user_id"
                                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
                                <td v-if="isAdmin" class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200">
                                    {{ user.user_name }}
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-900 dark:text-gray-200 break-all">
                                    {{ user.payout }}
                                </td>
                                <td
                                    class="px-4 py-2 text-xs text-right text-gray-900 dark:text-gray-200 cursor-default">
                                    <table
                                        class="table-auto w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
                                        <thead>
                                            <tr class="bg-gray-100 dark:bg-gray-800">
                                                <th class="px-2 py-1">{{ $t('token_name') }}</th>
                                                <th class="px-2 py-1">{{ $t('quantity') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="token in parseTokens(user.tokens)" :key="token.token_id">
                                                <td class="px-2 py-1">{{ token.token_name }}</td>
                                                <td class="px-2 py-1">{{ formatQuantity(token.quantity, token.decimals)
                                                    }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>

                                </td>
                            </tr>
                            <tr v-if="!sortedUsers.length">
                                <td colspan="4" class="text-center py-4 text-gray-500">
                                    {{ $t('no_users_found') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="sortedUsers.length > 0 && props.users.meta.last_page > 1"
                        class="flex justify-center mt-4 mb-4 space-x-1">
                        <button type="button" v-if="props.users.meta.current_page > 1"
                            @click="goTo(props.users.meta.current_page - 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700">{{
                                $t('prev') }}</button>
                        <template v-for="page in pagesToShow" :key="page">
                            <span v-if="page === '...'" class="px-3 py-1 text-gray-500 select-none">...</span>
                            <button type="button" v-else @click="goTo(Number(page))" class="px-3 py-1 border rounded"
                                :class="page === props.users.meta.current_page ? 'bg-blue-500 text-white' : 'bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700'">
                                {{ page }}
                            </button>
                        </template>
                        <button type="button" v-if="props.users.meta.current_page < props.users.meta.last_page"
                            @click="goTo(props.users.meta.current_page + 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700">{{
                                $t('next') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
