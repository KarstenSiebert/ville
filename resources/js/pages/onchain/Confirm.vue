<script setup lang="ts">
import { ref } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { confirm } from '@/routes/onchain';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogClose } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
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
        href: confirm().url,
    },
];

interface Token {
    quantity: number;
    policy_id: string;
    asset_hex: string;
    asset_name: string;
}

interface Utxo {
    address: string;
    ada: number;
    tokens: Token[];
}

const page = usePage()

interface Transaction {
    tx_fee: string | ''
    tx_net: string | ''
    tx_rate: string | null
    tx_prefix: string | null
}

const props = defineProps<{
    transaction: Transaction,
    utxos: Utxo[]
}>()

const form = useForm<{
    transaction: Transaction
}>({
    transaction: {
        tx_fee: props.transaction.tx_fee ?? '',
        tx_net: props.transaction.tx_net ?? '',
        tx_rate: props.transaction.tx_rate ?? '',
        tx_prefix: props.transaction.tx_prefix ?? ''
    }
})

const showDialog = ref(false);

function approveSubmit() {
    form.post("/onchain/confirm", {
        preserveScroll: true,
        onSuccess: () => {
            showDialog.value = false;
        }
    });
}

console.log(props.transaction.tx_fee + ' ' + props.transaction.tx_net)
</script>

<template>

    <Head :title="$t('confirm')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative text-xs flex flex-col gap-4 overflow-x-auto rounded-xl p-4">

            <div class="absolute top-2 left-1/2 -translate-x-1/2 z-20 w-full max-w-sm">
                <FlashMessage type="success" :message="page.props.flash?.success ? $t(page.props.flash.success) : ''" />
                <FlashMessage type="error" :message="page.props.flash?.error ? $t(page.props.flash.error) : ''" />
            </div>

            <form @submit.prevent="showDialog = true" class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $t('details') }}
                    </h2>
                </div>

                <div class="mb-5 flex flex-col sm:flex-row gap-4">

                    <div class="mb-4 flex-1">
                        <label for="tx_net" class="block text-sm font-medium">{{ $t('network_fee') }}</label>
                        <input id="tx_net"
                            class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-0 cursor-default"
                            :value="Number(Math.max(0, parseFloat(form.transaction.tx_net) || 0)).toLocaleString(undefined, { minimumFractionDigits: 6, maximumFractionDigits: 6 })"
                            type="text" readonly />
                    </div>

                    <div class="mb-4 flex-1">
                        <label for="tx_fee" class="block text-sm font-medium">{{ $t('accesspay_fee') }}</label>
                        <input id="tx_fee"
                            class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-0 cursor-default"
                            :value="Number(Math.max(0, (parseFloat(form.transaction.tx_fee)) || 0)).toLocaleString(undefined, { minimumFractionDigits: 6, maximumFractionDigits: 6 })"
                            type="text" readonly />
                    </div>

                    <div class="mb-4 flex-1">
                        <label for="tx_fee" class="block text-sm font-medium">{{ $t('your_fee') }}</label>
                        <input id="tx_fee"
                            class="font-mono w-full px-1 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-0 cursor-default"
                            :value="Number(Math.max(0, (parseFloat(form.transaction.tx_fee) || 0) - parseFloat(form.transaction.tx_net) || 0)).toLocaleString(undefined, { minimumFractionDigits: 6, maximumFractionDigits: 6 })"
                            type="text" readonly />
                    </div>

                </div>

                <div v-if="utxos.length > 0" class="overflow-x-auto rounded-lg shadow">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th
                                    class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('address') }}
                                </th>
                                <th
                                    class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    ADA
                                </th>
                                <th
                                    class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('tokens') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="(utxo, i) in utxos" :key="i"
                                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
                                <td class="border px-4 py-2 break-all cursor-default">{{ utxo.address }}</td>
                                <td class="border px-6 py-2 text-right cursor-default">
                                    {{ utxo.ada.toLocaleString(undefined, {
                                        minimumFractionDigits: 6,
                                        maximumFractionDigits: 6
                                    }) }}
                                </td>
                                <td class="border px-4 py-2 cursor-default">
                                    <ul v-if="utxo.tokens && utxo.tokens.length > 0">
                                        <li v-for="(token, j) in utxo.tokens" :key="j">
                                            <strong>{{ token.asset_name }}</strong>
                                            ({{ token.quantity }})<br>
                                            <small class="text-gray-500">{{ token.policy_id }}</small>
                                        </li>
                                    </ul>
                                    <span v-else
                                        class="invisible select-none"><small>a2ae2d62dad1353a28c9ff4af79c7be9ad5d1adf919d2c1efa2f2fc8</small></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="utxos.length > 0" class="h-5"></div>
                <div class="mt-4 flex justify-end">
                    <Button type="submit"
                        class="px-4 py-2 rounded-lg shadow cursor-pointer text-white bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed disabled:opacity-70"
                        :disabled="form.processing">
                        {{ $t('submit_transaction') }}
                    </Button>
                </div>
            </form>
        </div>

        <Dialog v-model:open="showDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ $t('confirm_transaction') }}</DialogTitle>
                    <DialogDescription>
                        {{ $t('are_you_sure_you_want_to_submit_this_transaction') }}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-8 mt-4">
                    <DialogClose as-child>
                        <Button variant="secondary">
                            {{ $t('cancel') }}
                        </Button>
                    </DialogClose>
                    <Button variant="destructive" :disabled="form.processing" @click="approveSubmit">
                        {{ $t('submit') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>