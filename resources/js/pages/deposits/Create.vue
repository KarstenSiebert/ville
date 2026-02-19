<script setup lang="ts">
import { ref, reactive, shallowReactive, computed, onMounted, watch, nextTick } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { index } from '@/routes/deposits';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import FlashMessage from "@/components/FlashMessage.vue";
import Button from "@/components/ui/button/Button.vue";
import "@inertiajs/core";

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
        title: 'wallet',
        href: index().url,
    },
];

const page = usePage()

interface User {
    id: number
    name?: string | null
    email?: string | null
}

const searchResults = ref<User[]>([])
const activeRow = ref<number | null>(null)
const loading = ref(false)

const searchCache = reactive<Record<string, User[]>>({})

let searchTimeout: number | undefined

async function searchUsers(query: string) {
    if (!query) {
        searchResults.value = []
        return
    }

    const prefix = Object.keys(searchCache).find(k => query.startsWith(k))

    const safeIncludes = (field: string | null | undefined, q: string) =>
        (field ?? '').toLowerCase().includes(q.toLowerCase())

    if (prefix) {
        const filtered = searchCache[prefix].filter(c =>
            safeIncludes(c.email, query) || safeIncludes(c.name, query)
        )
        searchResults.value = filtered
        return
    }

    loading.value = true

    try {

        var res;

        if (props.user_context.type === 'publisher') {
            res = await fetch(`/shadows/${props.user_context.id}/search?q=${encodeURIComponent(query)}`)
        }
        else {
            res = await fetch(`/users/search?q=${encodeURIComponent(query)}`)
        }

        if (res.ok) {
            const data: User[] = await res.json()

            searchResults.value = data
            searchCache[query] = data
        }
    } finally {
        loading.value = false
    }
}

function handleInput(query: string, rowIndex: number) {
    activeRow.value = rowIndex
    clearTimeout(searchTimeout)

    searchTimeout = window.setTimeout(() => {
        searchUsers(query)
    }, 300)
}

interface Asset {
    token_number: number
    policy_id: string | null
    asset_name: string
    asset_hex: string
    fingerprint: string
    quantity: number
    reserved_quantity: number
    decimals: number
    is_product: boolean
    logo_url: string
    destination: string | null
    receiver_id: number
    token_id: number
}

const props = defineProps<{
    assets: Asset[],
    payout: string | null,
    user_context: { type: string, name: string, id: number }
}>()

interface EditableAsset {
    id: string
    policy_id: string | null
    asset_name: string
    asset_hex: string
    fingerprint: string
    token_number: number
    quantity: number
    reserved_quantity: number
    decimals: number
    is_product: boolean
    logo_url: string
    destination: string
    receiver_id: number
    token_id: number
    readonlyFromBackend?: boolean
    originalAsset?: Asset
}

const editableAssets = ref<EditableAsset[]>(props.assets.map((a) => ({
    id: crypto.randomUUID(),
    policy_id: a.policy_id,
    asset_name: a.asset_name,
    asset_hex: a.asset_hex,
    fingerprint: a.fingerprint,
    token_number: a.token_number ?? 0,
    quantity: a.quantity,
    reserved_quantity: a.reserved_quantity,
    decimals: a.decimals ?? 0,
    is_product: a.is_product ?? false,
    destination: a.destination ?? "",
    receiver_id: a.receiver_id,
    token_id: a.token_id,
    logo_url: a.logo_url ?? '/storage/logos/cardano-ada-logo.png',
    readonlyFromBackend: a.token_number > 0,
    originalAsset: a
})))

const selected = ref<string[]>([])

const destinationRefs = ref<HTMLInputElement[]>([])

const form = useForm({
    selected_assets: [] as {
        policy_id: string | null
        asset_name: string
        asset_hex: string
        fingerprint: string
        token_number: number
        quantity: number
        reserved_quantity: number
        decimals: number
        is_product: boolean
        destination: string
        receiver_id: number
        token_id: number
    }[],
})

const allSelected = computed({
    get: () =>
        editableAssets.value.length > 0 &&
        editableAssets.value.every(a => selected.value.includes(a.id)),
    set: (val: boolean) => {
        selected.value = val ? editableAssets.value.map(a => a.id) : []
    }
})

const isNumberInvalid = (asset: EditableAsset) => {
    if (!selected.value.includes(asset.id)) return false

    if (asset.token_number <= 0) return true

    if (asset.is_product && !Number.isInteger(asset.token_number)) return true

    return false
}

const isFormValid = computed(() => {
    return editableAssets.value
        .filter(a => selected.value.includes(a.id))
        .every(a =>
            a.token_number > 0 &&
            (!a.is_product || Number.isInteger(a.token_number))
        )
})

function duplicateRow(index: number) {
    const original = editableAssets.value[index]
    if (!original) return

    const duplicate: EditableAsset = {
        ...original,
        id: crypto.randomUUID(),
        token_number: 0,
        destination: "",
        receiver_id: 0,
        readonlyFromBackend: false,
        originalAsset: original.originalAsset
    }

    editableAssets.value.splice(index + 1, 0, duplicate)
    nextTick(() => {
        destinationRefs.value[index + 1]?.focus()
    })
}

function deleteRow(index: number) {
    const asset = editableAssets.value[index]
    if (!asset) return

    editableAssets.value.splice(index, 1)

    nextTick(() => {
        const nextIndex = index < editableAssets.value.length ? index : editableAssets.value.length - 1
        destinationRefs.value[nextIndex]?.focus()
    })
}

const isReadonly = (index: number) => {
    const asset = editableAssets.value[index]
    return asset?.readonlyFromBackend ?? false
}

const selectAllCheckbox = ref<HTMLInputElement | null>(null)

watch(selected, () => {
    if (!selectAllCheckbox.value) return
    const total = editableAssets.value.length
    const checked = selected.value.length
    selectAllCheckbox.value.indeterminate =
        checked > 0 && checked < total
})

onMounted(() => {
    if (!selectAllCheckbox.value) return
    const total = editableAssets.value.length
    const checked = selected.value.length
    selectAllCheckbox.value.indeterminate =
        checked > 0 && checked < total
})

function buildDestination(user: User): string {
    return user.email ?? user.name ?? ''
}

function selectDestination(user: User, rowIndex: number) {
    const asset = editableAssets.value[rowIndex]

    asset.destination = buildDestination(user)
    asset.receiver_id = user.id
    activeRow.value = null
}

function isQuantityExceeded(asset: EditableAsset) {
    const originalAsset = asset.originalAsset
    if (!originalAsset) return false
    const inputValueInBaseUnits = Math.round(asset.token_number * Math.pow(10, asset.decimals))
    return inputValueInBaseUnits > Math.max(originalAsset.quantity - originalAsset.reserved_quantity, 0)
}

const validationErrors = shallowReactive<Record<string, string>>({})

function validateAsset(asset: EditableAsset) {
    const original = asset.originalAsset
    if (!original) return false

    const totalAssigned = editableAssets.value
        .filter(a => a.originalAsset === original)
        .reduce((sum, a) => sum + Math.round(a.token_number * Math.pow(10, a.decimals)), 0)

    if (asset.token_number <= 0) {
        delete validationErrors[asset.id]
        return false
    }

    if (totalAssigned > Math.max(original.quantity - original.reserved_quantity, 0)) {
        validationErrors[asset.id] = 'amount_exceeds_available'
        return false
    }

    delete validationErrors[asset.id]
    return true
}

function isKnownUser(asset: EditableAsset): boolean {
    return asset.receiver_id > 0 || asset.destination.startsWith('addr1')
}

const isDestinationInvalid = (asset: EditableAsset) =>
    selected.value.includes(asset.id) &&
    (asset.destination.trim() === "" || !isKnownUser(asset))

watch(() => editableAssets.value.map(a => a.token_number), () => {
    editableAssets.value.forEach(validateAsset)
})

function submitForm() {
    const hasExceeded = editableAssets.value.some((asset, i) =>
        isQuantityExceeded(asset)
    )

    if (hasExceeded) {
        page.props.flash.error = 'amount_exceeds_available'
        return
    }

    form.selected_assets = editableAssets.value
        .filter(a => selected.value.includes(a.id))
        .map(a => {
            const realValue =
                a.asset_name === "ADA"
                    ? Math.round(a.token_number * 1e6)
                    : Math.round(a.token_number * Math.pow(10, a.decimals))

            return {
                policy_id: a.policy_id,
                asset_name: a.asset_name,
                asset_hex: a.asset_hex,
                fingerprint: a.fingerprint,
                token_number: realValue,
                quantity: a.quantity,
                reserved_quantity: a.reserved_quantity,
                decimals: a.decimals,
                is_product: a.is_product,
                destination: a.destination,
                receiver_id: a.receiver_id,
                token_id: a.token_id,
            }
        })

    if (form.selected_assets.length === 0) {
        return
    }

    form.post(props.user_context.type === 'publisher'
        ? `/deposits/publisher/${props.user_context.id}`
        : `/deposits`
    )
}

</script>

<template>

    <Head :title="$t('select_amount')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative text-xs flex flex-col gap-4 overflow-x-auto rounded-xl p-4">

            <div class="absolute top-2 left-1/2 -translate-x-1/2 z-20 w-full max-w-sm">
                <FlashMessage type="success" :message="page.props.flash?.success ? $t(page.props.flash.success) : ''" />
                <FlashMessage type="error" :message="page.props.flash?.error ? $t(page.props.flash.error) : ''" />
            </div>

            <form @submit.prevent="submitForm" class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $t('number') }}
                    </h2>
                    <input class="px-3 py-2 border rounded-lg text-sm w-56" style="visibility: hidden;" />
                </div>

                <div class="overflow-x-auto rounded-lg">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-center">
                                    <input type="checkbox" id="checkbox" ref="selectAllCheckbox"
                                        v-model="allSelected" />
                                </th>
                                <th
                                    class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('token') }}
                                </th>
                                <th
                                    class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('number') }}
                                </th>
                                <th
                                    class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('recipient') }}
                                </th>
                                <th
                                    class="px-4 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('actions') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="(asset, index) in editableAssets" :key="asset.id"
                                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
                                <td class="px-4 py-2 text-center">
                                    <input type="checkbox" :value="asset.id" v-model="selected"
                                        :id="`checkbox-${index}`" />
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200">
                                    <component :is="asset.fingerprint ? 'a' : 'div'"
                                        :href="asset.fingerprint ? 'https://cexplorer.io/asset/' + asset.fingerprint : null"
                                        target="_blank" rel="noopener noreferrer"
                                        class="flex items-center space-x-2 group transition-shadow duration-200 rounded">
                                        <img v-if="asset.logo_url" :src="asset.logo_url" alt="logo"
                                            class="w-6 h-6 rounded transition-transform duration-200"
                                            :class="{ 'group-hover:scale-105': asset.fingerprint }" />
                                        <span class="transition-colors duration-200 truncate cursor-default"
                                            :class="{ 'group-hover:text-blue-600': asset.fingerprint }">
                                            {{ asset.asset_name }}
                                        </span>
                                    </component>
                                </td>
                                <td class="px-4 py-2 text-sm font-mono text-gray-900 dark:text-gray-200">
                                    <div class="flex flex-col">
                                        <input type="number" min="0"
                                            :step="asset.is_product ? 1 : (asset.decimals > 0 ? 1 / Math.pow(10, asset.decimals) : 1)"
                                            v-model.number="editableAssets[index].token_number"
                                            :disabled="!selected.includes(asset.id)" :class="[
                                                'w-full px-2 py-1 rounded border bg-white dark:bg-gray-700',
                                                'text-gray-900 dark:text-gray-200 border-gray-300 dark:border-gray-600',
                                                'focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 dark:disabled:bg-gray-800 disabled:text-gray-400',
                                                { 'border-red-500 focus:ring-red-500': isNumberInvalid(asset) },
                                                validationErrors[asset.id]
                                                    ? 'border-red-500 focus:ring-red-500'
                                                    : 'focus:ring-blue-500'
                                            ]" />
                                        <span v-if="validationErrors[asset.id]" class="text-red-500 text-xs mt-1">
                                            {{ $t(validationErrors[asset.id]) }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-4 py-2 font-mono text-gray-900 dark:text-gray-200">
                                    <input type="text" :placeholder="$t('type_to_search_user')"
                                        v-model="editableAssets[index].destination" :id="`destination-${index}`"
                                        @input="handleInput(editableAssets[index].destination, index)"
                                        :disabled="!selected.includes(asset.id)" :class="[
                                            'w-full px-2 py-1 text-sm font-mono rounded border bg-white dark:bg-gray-700',
                                            'text-gray-900 dark:text-gray-200 border-gray-300 dark:border-gray-600',
                                            'focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 dark:disabled:bg-gray-800 disabled:text-gray-400',
                                            { 'border-red-500 focus:ring-red-500': isDestinationInvalid(asset) }
                                        ]" :readonly="isReadonly(index)" />
                                    <ul v-if="activeRow === index && searchResults.length > 0"
                                        class="absolute z-50 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg mt-1 w-full max-w-[340px] max-h-40 overflow-y-auto shadow-lg">

                                        <li v-for="c in searchResults.slice(0, 3)" :key="c.id"
                                            @click="selectDestination(c, index)"
                                            class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer font-mono truncate">
                                            {{ c.name }} {{ c.email || '' }}
                                        </li>
                                    </ul>
                                    <!-- Loading indicator -->
                                    <div v-if="activeRow === index && loading" class="absolute right-2 top-2">
                                        <svg class="w-4 h-4 animate-spin text-gray-500"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z">
                                            </path>
                                        </svg>
                                    </div>
                                    <div v-if="validationErrors[asset.id]" class="text-red-500 text-xs h-5 mt-1">
                                        {{ '' }}
                                    </div>
                                </td>

                                <td class="px-4 py-2 text-right cursor-default">
                                    <button type="button" @click="duplicateRow(index)"
                                        class="text-green-500 hover:text-green-700 dark:hover:text-green-400 p-1 cursor-pointer">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                                d="M12 4v16m8-8H4" />
                                        </svg>
                                    </button>
                                    <button type="button" @click="deleteRow(index)"
                                        class="text-red-500 hover:text-red-700 dark:hover:text-red-400 p-1 cursor-pointer">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-trash-icon lucide-trash h-4 w-4">
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" />
                                            <path d="M3 6h18" />
                                            <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="h-5"></div>
                <div class="mt-4 flex justify-end">
                    <Button type="submit"
                        class="px-4 py-2 rounded-lg shadow cursor-pointer text-white bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed disabled:opacity-70"
                        :disabled="form.processing || selected.length === 0 || !isFormValid || Object.keys(validationErrors).length > 0">
                        {{ $t('submit') }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
