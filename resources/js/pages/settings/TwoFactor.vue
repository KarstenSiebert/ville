<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { disable, enable, show } from '@/routes/two-factor';
import { BreadcrumbItem } from '@/types';
import { Form, Head } from '@inertiajs/vue3';
import { ShieldBan, ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';

interface Props {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
}

withDefaults(defineProps<Props>(), {
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'two_factor_authentication',
        href: show.url(),
    },
];

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => {
    clearTwoFactorAuthData();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head :title="$t('two_factor_authentication')" />
        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall :title="$t('two_factor_authentication')"
                    :description="$t('manage_your_two_factor_authentication_settings')" />

                <div v-if="!twoFactorEnabled" class="flex flex-col items-start justify-start space-y-4">
                    <Badge variant="destructive">{{ $t('disabled') }}</Badge>

                    <p class="text-muted-foreground">
                        {{ $t('when_you_enable_two_factor_authentication') }}
                    </p>

                    <div>
                        <Button v-if="hasSetupData" @click="showSetupModal = true">
                            <ShieldCheck />{{ $t('continue_setup') }}
                        </Button>
                        <Form v-else v-bind="enable.form()" @success="showSetupModal = true" #default="{ processing }">
                            <Button type="submit" :disabled="processing">
                                <ShieldCheck />{{ $t('enable_2FA') }}
                            </Button>
                        </Form>
                    </div>
                </div>

                <div v-else class="flex flex-col items-start justify-start space-y-4">
                    <Badge variant="default">{{ $t('enabled') }}</Badge>

                    <p class="text-muted-foreground">
                        {{ $t('with_two_factor_authentication_enabled') }}
                    </p>

                    <TwoFactorRecoveryCodes />

                    <div class="relative inline">
                        <Form v-bind="disable.form()" #default="{ processing }">
                            <Button variant="destructive" type="submit" :disabled="processing">
                                <ShieldBan />
                                {{ $t('disable_2FA') }}
                            </Button>
                        </Form>
                    </div>
                </div>

                <TwoFactorSetupModal v-model:isOpen="showSetupModal" :requiresConfirmation="requiresConfirmation"
                    :twoFactorEnabled="twoFactorEnabled" />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
