<script setup lang="ts">
import { login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);
</script>

<template>

    <Head title="Welcome">
    </Head>
    <div
        class="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8 dark:bg-[#0a0a0a]">
        <header class="mb-6 w-full max-w-[335px] text-sm not-has-[nav]:hidden lg:max-w-4xl">
            <nav class="flex items-center justify-end gap-4">
                <Link :href="login()"
                    class="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#19140035] dark:text-[#EDEDEC] dark:hover:border-[#3E3E3A]">
                    {{ $t('log_in') }}
                </Link>
                <Link v-if="canRegister" :href="register()"
                    class="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]">
                    {{ $t('register') }}
                </Link>
            </nav>
        </header>
        <div
            class="flex w-full items-center justify-center opacity-100 transition-opacity duration-750 lg:grow starting:opacity-0">

            <main
                class="flex w-full max-w-[335px] lg:max-w-4xl items-center flex-col-reverse overflow-hidden justify-center rounded-lg min-h-[60vh] lg:min-h-0">
                <img src="/storage/logos/tokenville.png" alt="Welcome to Tokenville"
                    class="w-full h-auto max-h-[768px] object-contain mx-auto dark:brightness-90" />

            </main>
        </div>
        <div class="hidden h-14.5 lg:block"></div>
    </div>
</template>
