<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import { loadLanguageAsync, trans } from 'laravel-vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { languages } from '@/routes';

import { getAvailableLanguages } from '@/app';

import usFlag from '@/assets/flags/us.svg';
import deFlag from '@/assets/flags/de.svg';
import frFlag from '@/assets/flags/fr.svg';
import esFlag from '@/assets/flags/es.svg';
import inFlag from '@/assets/flags/in.svg';
import seFlag from '@/assets/flags/se.svg';
import plFlag from '@/assets/flags/pl.svg';
import fiFlag from '@/assets/flags/fi.svg';
import grFlag from '@/assets/flags/gr.svg';
import ieFlag from '@/assets/flags/ie.svg';
import eeFlag from '@/assets/flags/ee.svg';
import nlFlag from '@/assets/flags/nl.svg';
import dkFlag from '@/assets/flags/dk.svg';
import bgFlag from '@/assets/flags/bg.svg';
import hrFlag from '@/assets/flags/hr.svg';
import huFlag from '@/assets/flags/hu.svg';
import irFlag from '@/assets/flags/ir.svg';
import itFlag from '@/assets/flags/it.svg';
import czFlag from '@/assets/flags/cz.svg';
import slFlag from '@/assets/flags/sl.svg';
import skFlag from '@/assets/flags/sk.svg';
import roFlag from '@/assets/flags/ro.svg';
import ptFlag from '@/assets/flags/pt.svg';
import mtFlag from '@/assets/flags/mt.svg';
import ltFlag from '@/assets/flags/lt.svg';
import lvFlag from '@/assets/flags/lv.svg';
import jpFlag from '@/assets/flags/jp.svg';
import ruFlag from '@/assets/flags/ru.svg';
import uaFlag from '@/assets/flags/ua.svg';
import zhFlag from '@/assets/flags/cn.svg';
import saFlag from '@/assets/flags/sa.svg';

const flags: Record<string, string> = {
    en: usFlag,
    de: deFlag,
    es: esFlag,
    fr: frFlag,
    hi: inFlag,
    se: seFlag,
    pl: plFlag,
    fi: fiFlag,
    gr: grFlag,
    ee: eeFlag,
    nl: nlFlag,
    dk: dkFlag,
    bg: bgFlag,
    hr: hrFlag,
    hu: huFlag,
    ie: ieFlag,
    ir: irFlag,
    it: itFlag,
    cz: czFlag,
    sl: slFlag,
    sk: skFlag,
    ro: roFlag,
    pt: ptFlag,
    mt: mtFlag,
    lt: ltFlag,
    lv: lvFlag,
    jp: jpFlag,
    ru: ruFlag,
    ua: uaFlag,
    zh: zhFlag,
    sa: saFlag,
};

const breadcrumbItems: BreadcrumbItem[] = [
    { title: "language_settings", href: languages().url },
];

const availableLanguages = getAvailableLanguages();

const selectedLanguage = ref(document.documentElement.lang || 'en');

function changeLanguage(lang: string) {
    selectedLanguage.value = lang;

    axios.defaults.headers.common['X-User-Locale'] = lang;

    router.post('/language', { locale: lang }, {
        preserveScroll: true,
        onFinish: () => {
            // window.location.reload();
            loadLanguageAsync(lang);
        }
    });
}

</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">

        <Head :title="trans('language_settings')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall :title="trans('language_settings')"
                    :description="trans('update_your_accounts_language_settings')" />

                <div class="grid grid-cols-3 sm:grid-cols-3 md:grid-cols-4 gap-1">
                    <label v-for="lang in availableLanguages" :key="lang" class="relative cursor-pointer">
                        <!-- Hidden radio input -->
                        <input type="radio" :value="lang" v-model="selectedLanguage"
                            @change="() => changeLanguage(lang)" class="sr-only" />

                        <!-- Pill -->
                        <span class="relative flex items-center justify-center w-10 h-10 select-none 
                   transition-transform transform hover:scale-110 hover:shadow-md">
                            <!-- Flag -->
                            <img v-if="flags[lang]" :src="flags[lang]" alt=""
                                class="object-contain w-full h-full rounded" />

                            <!-- Checkmark overlay -->
                            <svg v-if="selectedLanguage === lang" class="absolute top-0 right-0 w-4 h-4 text-white bg-blue-800 rounded-full p-0.5 
                       transition-all duration-200 ease-in-out scale-0 md:scale-100" fill="currentColor"
                                viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 00-1.414 0L8 12.586 4.707 9.293a1 1 0 00-1.414 1.414l4 4a1 1 0 001.414 0l8-8a1 1 0 000-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </span>
                    </label>
                </div>

            </div>
        </SettingsLayout>
    </AppLayout>
</template>
