<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout :title="$t('forgot_password')" :description="$t('enter_your_email_to_receive_a_password_reset_link')">

        <Head :title="$t('forgot_password')" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <div class="space-y-6">
            <Form v-bind="email.form()" v-slot="{ errors, processing }">
                <div class="grid gap-2">
                    <Label for="email">{{ $t('email_address') }}</Label>
                    <Input id="email" type="email" name="email" autocomplete="off" autofocus
                        placeholder="email@example.com" />
                    <InputError :message="errors.email" />
                </div>

                <div class="my-6 flex items-center justify-start">
                    <Button class="w-full" :disabled="processing" data-test="email-password-reset-link-button">
                        <Spinner v-if="processing" />
                        {{ $t('send_password_reset_link') }}
                    </Button>
                </div>
            </Form>

            <div class="space-x-1 text-center text-sm text-muted-foreground">
                <span>{{ $t('or_return_to') }}</span>
                <TextLink :href="login()">{{ $t('log_in') }}</TextLink>
            </div>
        </div>
    </AuthLayout>
</template>
