<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
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
    download?: string | null
    minimal_tokens: number
}

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

function isRedeemable(asset: Asset) {
    return asset.token_type === 'BASE' &&
        Number(asset.minimal_tokens) > 0
}

function confirmToRedeemConfirmed() {
    if (!assetToRedeem.value) return;

    if (assetToRedeem.value.download) {
        const link = document.createElement('a');
        link.href = assetToRedeem.value.download;
        link.download = assetToRedeem.value.asset_name;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    router.delete(`/deposit/${assetToRedeem.value.id}`, {
        only: ['assets'],
        preserveScroll: true,
        onSuccess: () => {
            assetToRedeem.value = null;
            qrDialogOpen.value = false;
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
                    <tr v-for="asset in props.assets" :key="asset.fingerprint"
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

                                <button type="button" @click="confirmRedeem(asset)" class="p-1" :class="isRedeemable(asset)
                                    ? 'text-blue-500 hover:text-blue-700 dark:hover:text-blue-400 cursor-pointer'
                                    : 'text-gray-400 cursor-not-allowed'" :disabled="!isRedeemable(asset)"
                                    :aria-label="$t('redeem')">

                                    <svg v-if="asset.token_type == 'BASE'" xmlns="http://www.w3.org/2000/svg" width="24"
                                        height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-coins-icon lucide-coins h-5 w-5">
                                        <path d="M13.744 17.736a6 6 0 1 1-7.48-7.48" />
                                        <path d="M15 6h1v4" />
                                        <path d="m6.134 14.768.866-.5 2 3.464" />
                                        <circle cx="16" cy="8" r="6" />
                                    </svg>

                                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-dices-icon lucide-dices h-5 w-5">
                                        <rect width="12" height="12" x="2" y="10" rx="2" ry="2" />
                                        <path
                                            d="m17.92 14 3.5-3.5a2.24 2.24 0 0 0 0-3l-5-4.92a2.24 2.24 0 0 0-3 0L10 6" />
                                        <path d="M6 18h.01" />
                                        <path d="M10 14h.01" />
                                        <path d="M15 6h.01" />
                                        <path d="M18 9h.01" />
                                    </svg>

                                </button>

                                <DialogContent v-if="assetToRedeem && assetToRedeem.id === asset.id">
                                    <DialogHeader>
                                        <DialogTitle>
                                            {{ asset.asset_name }}
                                        </DialogTitle>
                                    </DialogHeader>
                                    <div v-if="qrBase64" class="flex justify-center items-center p-4">
                                        <img :src="qrBase64" :alt="$t('qr_code')"
                                            class="max-w-full max-h-[400px] rounded-lg shadow-lg object-contain" />
                                    </div>
                                    <DialogFooter class="gap-8">
                                        <Button variant="default" @click="confirmToRedeemConfirmed">
                                            {{ $t('redeem') }}
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </td>
                    </tr>
                    <tr v-if="!props.assets.length">
                        <td colspan="4" class="text-center py-4 text-gray-500">
                            {{ $t('no_assets_found') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</template>
