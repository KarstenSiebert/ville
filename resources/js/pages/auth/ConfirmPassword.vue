<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/password/confirm';
import { Form, Head } from '@inertiajs/vue3';
</script>

<template>
    <AuthLayout :title="$t('confirm_your_password')"
        :description="$t('please_confirm_your_password_before_continuing')">

        <Head :title="$t('confirm_password')" />

        <Form v-bind="store.form()" reset-on-success v-slot="{ errors, processing }">
            <div class="space-y-6">
                <div class="grid gap-2">
                    <Label htmlFor="password">{{ $t('password') }}</Label>
                    <Input id="password" type="password" name="password" class="mt-1 block w-full" required
                        autocomplete="current-password" autofocus />

                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center">
                    <Button class="w-full" :disabled="processing" data-test="confirm-password-button">
                        <Spinner v-if="processing" />
                        {{ $t('confirm_password') }}
                    </Button>
                </div>
            </div>
        </Form>
    </AuthLayout>
</template>
