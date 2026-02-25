<script setup lang="ts">
import { ref, computed } from 'vue';
import "@inertiajs/core";
import { Coins, Dices } from 'lucide-vue-next';

declare module "@inertiajs/core" {
    interface PageProps {
        flash: {
            success?: string
            error?: string
        }
    }
}

const props = defineProps<{
    assets: Asset[]
}>()

interface Asset {
    asset_name: string
    fingerprint: string
    quantity: number
    reserved_quantity: number
    decimals: number
    token_type: string
    logo_url?: string
    is_user_token: boolean
}

const safeAssets = computed(() => Array.isArray(props.assets) ? props.assets : []);

const editableAssets = ref<Asset[]>(Array.isArray(props.assets) ?
    safeAssets.value.map((a) => ({
        ...a,
        asset_name: a.asset_name,
        fingerprint: a.fingerprint,
        reserved_quantity: a.reserved_quantity,
        quantity: a.quantity,
        decimals: a.decimals,
        token_type: a.token_type,
        logo_url: a.logo_url ?? '/storage/logos/cardano-ada-logo.png',
        is_user_token: a.is_user_token,
    }))
    : []
);

</script>

<template>

    <div class="text-xs flex flex-col gap-4 overflow-x-auto rounded-xl p-4">

        <div class="overflow-x-auto rounded-lg">
            <table
                class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th
                            class="px-8 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer">
                            {{ $t('token') }}
                        </th>
                        <th
                            class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                            {{ $t('type') }}
                        </th>
                        <th
                            class="px-4 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                            {{ $t('balance') }}
                        </th>
                        <th
                            class="hidden md:table-cell px-4 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                            {{ $t('reserved') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="(asset) in editableAssets" :key="asset.fingerprint"
                        class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200 min-w-[100px] whitespace-nowrap">
                            <component :is="asset.fingerprint && (asset.token_type == 'BASE') ? 'a' : 'div'"
                                :href="asset.fingerprint && (asset.token_type == 'BASE') ? 'https://cexplorer.io/asset/' + asset.fingerprint : null"
                                target="_blank" rel="noopener noreferrer"
                                class="flex items-center space-x-2 group transition-shadow duration-200 rounded">
                                <img v-if="asset.logo_url" :src="asset.logo_url" alt="logo"
                                    class="w-8 h-8 rounded transition-transform duration-200"
                                    :class="{ 'group-hover:scale-105': asset.fingerprint }" />

                                <span
                                    class="transition-colors duration-200 truncate max-w-xs overflow-hidden text-ellipsis cursor-default"
                                    :class="{ 'group-hover:text-blue-600 cursor-pointer': asset.fingerprint && (asset.token_type == 'BASE') }">
                                    {{ asset.asset_name }}
                                </span>
                            </component>
                        </td>
                        <td class="px-4 py-2 text-center cursor-default">

                            <span v-if="asset.token_type === 'BASE'" title="Currency token">
                                <Coins :class="{ 'opacity-50 pointer-events-none': asset.is_user_token }"
                                    class="w-5 h-5 inline-block text-indigo-600 dark:text-indigo-400" />
                            </span>

                            <span v-else-if="asset.token_type === 'SHARE'" title="Market token">
                                <Dices :class="{ 'opacity-50 pointer-events-none': asset.is_user_token }"
                                    class="w-5 h-5 inline-block text-teal-600 dark:text-teal-400" />
                            </span>

                            <span v-else title="Currency">
                                <Coins :class="{ 'opacity-50 pointer-events-none': asset.is_user_token }"
                                    class="w-5 h-5 inline-block text-amber-600 dark:text-amber-400" />
                            </span>
                        </td>
                        <td class="px-4 py-2 text-xs text-right text-gray-900 dark:text-gray-200 cursor-default">
                            <tt>{{
                                (asset.asset_name === "ADA"
                                    ? asset.quantity / 1e6
                                    : asset.quantity / Math.pow(10, asset.decimals)
                                ).toLocaleString("en-US", {
                                    minimumFractionDigits: asset.decimals > 6 ? 6 : asset.decimals,
                                    maximumFractionDigits: asset.decimals > 6 ? 6 : asset.decimals
                                })
                            }}</tt>
                        </td>
                        <td
                            class="hidden md:table-cell px-4 py-2 text-xs text-right text-gray-900 dark:text-gray-200 cursor-default">
                            <tt>{{
                                (asset.asset_name === "ADA"
                                    ? asset.reserved_quantity / 1e6
                                    : asset.reserved_quantity / Math.pow(10, asset.decimals)
                                ).toLocaleString("en-US", {
                                    minimumFractionDigits: asset.decimals > 6 ? 6 : asset.decimals,
                                    maximumFractionDigits: asset.decimals > 6 ? 6 : asset.decimals
                                })
                            }}</tt>
                        </td>
                    </tr>
                    <tr v-if="!editableAssets.length">
                        <td colspan="4" class="text-center py-4 text-gray-500">
                            {{ $t('no_assets_found') }}
                        </td>
                    </tr>
                </tbody>
            </table>

        </div>

    </div>

</template>
