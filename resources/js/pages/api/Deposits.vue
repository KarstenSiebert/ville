<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Dialog, DialogTrigger, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogClose } from "@/components/ui/dialog";
import { Coins, Dices } from 'lucide-vue-next';
import "@inertiajs/core";

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
    id: number
    asset_name: string
    fingerprint: string
    quantity: number
    decimals: number
    token_type: string
    logo_url?: string
}

const safeAssets = computed(() => Array.isArray(props.assets) ? props.assets : []);

const editableAssets = ref<Asset[]>(Array.isArray(props.assets) ?
    safeAssets.value.map((a) => ({
        ...a,
        id: a.id,
        asset_name: a.asset_name,
        fingerprint: a.fingerprint,
        quantity: a.quantity,
        decimals: a.decimals,
        token_type: a.token_type,
        logo_url: a.logo_url ?? '/storage/logos/cardano-ada-logo.png',
    }))
    : []
);

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

const assetToRedeem = ref<Asset | null>(null);

const loadingQR = ref(false)

async function confirmRedeem(asset: Asset) {
    assetToRedeem.value = asset
    qrBase64.value = null
    qrDialogOpen.value = false

    try {
        const url = `/deposit/${asset.id}/qrcode`

        loadingQR.value = true

        const base64 = await fetchQRCodeBase64(url)

        qrBase64.value = base64
        loadingQR.value = false
        qrDialogOpen.value = true

    } catch (error) {
        console.error('Failed to fetch QR code:', error)
    }
}

function confirmToRedeemConfirmed() {
    if (!assetToRedeem.value) return;

    const previousAsset = [...editableAssets.value];

    editableAssets.value = editableAssets.value.filter(
        (c) => c.id !== assetToRedeem.value!.id
    );

    router.delete(`/api/deposits/${assetToRedeem.value.id}`, {
        data: {
            asset: assetToRedeem.value
        },
        preserveScroll: true,
        onError: () => {
            editableAssets.value = previousAsset;
        },
        onSuccess: () => {
            assetToRedeem.value = null;
        },
    });
}

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
                            class="px-4 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                            {{ $t('actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="(asset) in editableAssets" :key="asset.fingerprint"
                        class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200 min-w-[100px] whitespace-nowrap">
                            <div class="flex items-center space-x-2 group transition-shadow duration-200 rounded">
                                <img v-if="asset.logo_url" :src="asset.logo_url" alt="logo"
                                    class="w-8 h-8 rounded transition-transform duration-200" />
                                <span
                                    class="transition-colors duration-200 truncate max-w-xs overflow-hidden text-ellipsis cursor-default">
                                    {{ asset.asset_name }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-2 text-center cursor-default">

                            <span v-if="asset.token_type === 'BASE'" title="Currency token">
                                <Coins class="w-5 h-5 inline-block text-indigo-600 dark:text-indigo-400" />
                            </span>

                            <span v-else-if="asset.token_type === 'SHARE'" title="Market token">
                                <Dices class="w-5 h-5 inline-block text-teal-600 dark:text-teal-400" />
                            </span>

                        </td>
                        <td
                            class="px-4 py-2 font-mono text-xs text-right text-gray-900 dark:text-gray-200 cursor-default">
                            {{
                                (asset.asset_name === "ADA"
                                    ? asset.quantity / 1e6
                                    : asset.quantity / Math.pow(10, asset.decimals)
                                ).toLocaleString("en-US", {
                                    minimumFractionDigits: asset.decimals > 6 ? 6 : asset.decimals,
                                    maximumFractionDigits: asset.decimals > 6 ? 6 : asset.decimals
                                })
                            }}
                        </td>
                        <td class="px-8 py-2 text-right cursor-default">
                            <Dialog v-model:open="qrDialogOpen">

                                <button type="button" @click="confirmRedeem(asset)" class="p-1" :class="asset.token_type == 'BASE'
                                    ? 'text-green-500 hover:text-green-700 dark:hover:text-green-400 cursor-pointer'
                                    : 'text-gray-400 cursor-not-allowed'" :disabled="asset.token_type !== 'BASE'"
                                    :aria-label="$t('redeem')">

                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="lucide lucide-banknote-arrow-up-icon lucide-banknote-arrow-up h-4 w-4">
                                        <path d="M12 18H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5" />
                                        <path d="M18 12h.01" />
                                        <path d="M19 22v-6" />
                                        <path d="m22 19-3-3-3 3" />
                                        <path d="M6 12h.01" />
                                        <circle cx="12" cy="12" r="2" />
                                    </svg>

                                </button>

                                <DialogContent v-if="assetToRedeem && assetToRedeem.id === asset.id">
                                    <DialogHeader>
                                        <DialogTitle>
                                            {{ $t('redeem') }}: {{
                                                asset.asset_name }}
                                        </DialogTitle>
                                        <DialogDescription>
                                            {{ $t('this_action_cannot_be_undone') }}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div v-if="qrBase64" class="flex justify-center items-center p-4">
                                        <img :src="qrBase64" :alt="$t('qr_code')"
                                            class="max-w-xs rounded-lg shadow-lg" />
                                    </div>
                                    <DialogFooter class="gap-8">
                                        <DialogClose as-child>
                                            <Button variant="secondary">{{ $t('cancel') }}</Button>
                                        </DialogClose>
                                        <Button variant="destructive" @click="confirmToRedeemConfirmed">
                                            {{ $t('redeem') }}
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
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
