<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch, nextTick } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { create } from '@/routes/onchain';
import { type BreadcrumbItem } from '@/types';
import { Button } from "@/components/ui/button";
import { Head, useForm, usePage } from '@inertiajs/vue3';
import FlashMessage from "@/components/FlashMessage.vue";
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
        href: create().url,
    },
];

const page = usePage()

interface Asset {
    token_number: number
    policy_id: string | null
    asset_name: string
    asset_hex: string
    fingerprint: string
    quantity: number
    decimals: number
    logo_url: string
}

const props = defineProps<{
    assets: Asset[],
    payout: string
}>()

interface EditableAsset {
    id: string
    policy_id: string | null
    asset_name: string
    asset_hex: string
    fingerprint: string
    token_number: number
    quantity: number
    decimals: number
    logo_url: string
    readonlyFromBackend?: boolean
}

const editableAssets = ref<EditableAsset[]>(props.assets.map((a) => ({
    id: crypto.randomUUID(),
    policy_id: a.policy_id,
    asset_name: a.asset_name,
    asset_hex: a.asset_hex,
    fingerprint: a.fingerprint,
    token_number: a.token_number ?? 0,
    quantity: a.quantity,
    decimals: a.decimals ?? 0,
    logo_url: a.logo_url ?? '/storage/logos/cardano-ada-logo.png',
    readonlyFromBackend: a.token_number > 0
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
        decimals: number
        destination: string
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

const isFormValid = computed(() => {
    return editableAssets.value
        .filter(a => selected.value.includes(a.id))
        .every(a => a.token_number > 0)
})

function deleteRow(index: number) {
    const asset = editableAssets.value[index]
    if (!asset) return

    editableAssets.value.splice(index, 1)

    nextTick(() => {
        const nextIndex = index < editableAssets.value.length ? index : editableAssets.value.length - 1
        destinationRefs.value[nextIndex]?.focus()
    })
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

function isQuantityExceeded(asset: EditableAsset, originalAsset: Asset) {
    const inputValueInBaseUnits = Math.round(asset.token_number * Math.pow(10, asset.decimals))
    const maxAvailable = originalAsset.quantity
    return inputValueInBaseUnits > maxAvailable
}

const validationErrors = reactive<Record<string, string>>({})

function validateAsset(asset: EditableAsset, originalAsset: Asset) {
    const inputValueInBaseUnits = Math.round(asset.token_number * Math.pow(10, asset.decimals))
    const maxAvailable = originalAsset.quantity

    if (asset.token_number <= 0) {
        // validationErrors[asset.id] = 'Bitte einen positiven Wert eingeben.'
        return false
    }

    if (inputValueInBaseUnits > maxAvailable) {
        validationErrors[asset.id] = 'amount_exceeds_available'
        return false
    }

    delete validationErrors[asset.id]
    return true
}

watch(
    editableAssets,
    (newAssets) => {
        newAssets.forEach((asset, i) => {
            const original = props.assets[i]
            validateAsset(asset, original)
        })
    },
    { deep: true }
)

function submitForm() {
    const hasExceeded = editableAssets.value.some((asset, i) =>
        isQuantityExceeded(asset, props.assets[i])
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
                decimals: a.decimals,
                destination: props.payout
            }
        })

    if (form.selected_assets.length === 0) {
        return
    }

    form.post("/onchain")
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
                        {{ $t('select_amount') }}
                    </h2>
                    <input class="px-3 py-2 border rounded-lg text-sm w-64" style="visibility: hidden;" />
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
                                    class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('number') }}
                                </th>
                                <th
                                    class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer">
                                    {{ $t('fingerprint') }}
                                </th>
                                <th
                                    class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
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
                                <td
                                    class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200 min-w-[100px] whitespace-nowrap">
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
                                            :step="asset.decimals > 0 ? (1 / Math.pow(10, asset.decimals)) : 1"
                                            v-model.number="editableAssets[index].token_number"
                                            :disabled="!selected.includes(asset.id)" :class="[
                                                'w-full px-2 py-1 font-mono rounded border bg-white dark:bg-gray-700',
                                                'text-gray-900 dark:text-gray-200 border-gray-300 dark:border-gray-600',
                                                'focus:outline-none focus:ring-2',
                                                validationErrors[asset.id]
                                                    ? 'border-red-500 focus:ring-red-500'
                                                    : 'focus:ring-blue-500'
                                            ]" />
                                        <span v-if="validationErrors[asset.id]" class="text-red-500 text-xs mt-1">
                                            {{ $t(validationErrors[asset.id]) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200 truncate max-w-xs">
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
                                <td class="px-4 py-2 text-center flex items-center justify-center gap-2">
                                    <button type="button" @click="deleteRow(index)"
                                        class="text-red-500 hover:text-red-700 dark:hover:text-red-400 p-1 cursor-pointer">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 0a1 1 0 00-1 1v1h6V4a1 1 0 00-1-1m-4 0h4" />
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
                        {{ $t('verify_transaction') }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
