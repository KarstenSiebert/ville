<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { wallet } from '@/routes/settings';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'wallet_settings',
        href: wallet().url,
    },
];

const props = defineProps({
    address: String,
    qrcode: String
})

</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">

        <Head :title="$t('wallet_settings')" />

        <SettingsLayout>
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <HeadingSmall :title="$t('wallet_information')" :description="$t('this_is_your_wallet_address')" />
                </div>

                <HeadingSmall :title="$t('deposit_wallet_address')" :description="address" />

                <div class="gap-2">
                    <img :src="qrcode" width="234" height="234">
                </div>

            </div>
        </SettingsLayout>
    </AppLayout>
</template>
