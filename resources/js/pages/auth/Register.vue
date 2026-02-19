<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Form, Head } from '@inertiajs/vue3';
</script>

<template>
    <AuthBase :title="$t('create_an_account')" :description="$t('enter_your_details_below_to_create_your_account')">

        <Head :title="$t('register')" />

        <Form v-bind="store.form()" :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">{{ $t('name') }}</Label>
                    <Input id="name" type="text" required autofocus :tabindex="1" autocomplete="name" name="name"
                        :placeholder="$t('full_name')" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">{{ $t('email_address') }}</Label>
                    <Input id="email" type="email" required :tabindex="2" autocomplete="email" name="email"
                        placeholder="email@example.com" />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ $t('password') }}</Label>
                    <Input id="password" type="password" required :tabindex="3" autocomplete="new-password"
                        name="password" :placeholder="$t('password')" />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">{{ $t('confirm_password') }}</Label>
                    <Input id="password_confirmation" type="password" required :tabindex="4" autocomplete="new-password"
                        name="password_confirmation" :placeholder="$t('confirm_password')" />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button type="submit" class="mt-2 w-full" tabindex="5" :disabled="processing"
                    data-test="register-user-button">
                    <Spinner v-if="processing" />
                    {{ $t('create_account') }}
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                {{ $t('already_have_an_account') }}
                <TextLink :href="login()" class="underline underline-offset-4" :tabindex="6">{{ $t('log_in') }}
                </TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
