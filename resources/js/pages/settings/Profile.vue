<script setup lang="ts">
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import { Form, Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { getInitials } from '@/composables/useInitials';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { User, type BreadcrumbItem, type SharedData } from '@/types';
import { computed, ref } from 'vue';

interface ProfileForm {
    _method: string;
    name: string;
    email: string;
    payout?: string;
    photo?: File | null;
}

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'profile_settings',
        href: edit().url,
    },
];

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user as User);

const form = useForm<Required<ProfileForm>>({
    _method: 'patch',
    name: user.value.name,
    email: user.value.email,
    payout: user.value.payout ?? '',
    photo: null,
});

const photoPreview = ref<string | null>(null);
const photoInput = ref<HTMLInputElement | null>(null);

const submit = () => {
    const payload = new FormData();
    payload.append('_method', 'PATCH');
    payload.append('name', form.name);
    payload.append('email', form.email);
    payload.append('payout', form.payout);

    if (photoInput.value?.files?.length) {
        payload.append('photo', photoInput.value.files[0]);
    }

    router.post('/settings/profile', payload, {
        preserveScroll: true,
        onSuccess: () => clearPhotoFileInput(),
    });
};

const selectNewPhoto = () => {
    photoInput.value?.click();
};

const updatePhotoPreview = () => {
    const file = photoInput.value?.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        if (e.target?.result) {
            photoPreview.value = e.target.result.toString();
        }
    };
    reader.readAsDataURL(file);
};

const deletePhoto = () => {
    router.delete('/settings/profile-photo', {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
        },
    });
};

const clearPhotoFileInput = () => {
    if (photoInput.value) {
        photoInput.value.value = '';
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">

        <Head :title="$t('profile_settings')" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall :title="$t('profile_information')"
                    :description="$t('update_your_name_and_email_address')" />

                <Form @submit.prevent="submit" :data="form" class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }" enctype="multipart/form-data">
                    <div class="grid gap-2">
                        <Label for="photo">{{ $t('photo') }}</Label>
                        <input id="photo" ref="photoInput" type="file" class="hidden" @change="updatePhotoPreview"
                            accept="image/*" />

                        <div class="flex items-center gap-4">
                            <Avatar class="h-20 w-20">
                                <AvatarImage :src="photoPreview || user.avatar || ''" :alt="user.name" />
                                <AvatarFallback>
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>

                            <Button type="button" variant="outline" @click.prevent="selectNewPhoto">
                                {{ user.avatar ? $t('change_photo') : $t('upload_photo') }}
                            </Button>

                            <Button v-if="user.avatar || photoPreview" type="button" variant="outline"
                                @click.prevent="deletePhoto">
                                {{ $t('remove_photo') }}
                            </Button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.photo" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="name">{{ $t('name') }}</Label>
                        <Input id="name" class="mt-1 block w-full" name="name" :default-value="user.name"
                            v-model="form.name" required autocomplete="name" placeholder="Full name" />
                        <InputError class="mt-2" :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">{{ $t('email_address') }}</Label>
                        <Input id="email" type="email" class="mt-1 block w-full" name="email" v-model="form.email"
                            :default-value="user.email" required autocomplete="username" placeholder="Email address" />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            {{ $t('your_email_address_is_unverified') }}
                            <Link :href="send()" as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500">
                                {{ $t('click_here_to_resend_the_verification_email') }}
                            </Link>
                        </p>

                        <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                            {{ $t('a_new_verification_link_has_been_sent_to_your_email_address') }}
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="payout">{{ $t('payout_wallet_address') }}</Label>
                        <Input id="payout" class="mt-1 block w-full" name="payout" v-model="form.payout"
                            autocomplete="payout" :placeholder="$t('outgoing_payment_address')" />
                        <InputError class="mt-2" :message="form.errors.payout" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="processing" data-test="update-profile-button">{{ $t('save') }}</Button>

                        <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                            <p v-show="recentlySuccessful" class="text-sm text-neutral-600">
                                {{ $t('saved') }}
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
