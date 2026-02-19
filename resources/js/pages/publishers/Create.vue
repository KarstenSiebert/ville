<script setup lang="ts">
import { ref, reactive, onMounted } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { create } from '@/routes/publishers';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage, useForm } from '@inertiajs/vue3';
import FlashMessage from "@/components/FlashMessage.vue";
import "@inertiajs/core";
import '@vuepic/vue-datepicker/dist/main.css'
import Button from "@/components/ui/button/Button.vue";
import Checkbox from "@/components/ui/checkbox/Checkbox.vue";

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
        title: 'create_operator',
        href: create().url,
    },
];

interface User {
    id: number
    name?: string | null
    email?: string | null
}

const searchResults = ref<User[]>([])
const loading = ref(false)

const searchCache = reactive<Record<string, User[]>>({})

let searchTimeout: number | undefined

const selectedUser = ref<User | null>(null)
const userInput = ref('')

function selectDestination(user: User) {
    selectedUser.value = user
    form.user_id = user.id
    userInput.value = `${user.name} (${user.email ?? ''})`
    searchResults.value = []
}

async function searchUsers(query: string) {
    if (!query) {
        searchResults.value = []
        return
    }

    const prefix = Object.keys(searchCache).find(k => query.startsWith(k))

    const safeIncludes = (field: string | null | undefined, q: string) =>
        (field ?? '').toLowerCase().includes(q.toLowerCase())

    if (prefix) {
        const filtered = searchCache[prefix].filter(c =>
            safeIncludes(c.email, query) || safeIncludes(c.name, query)
        )
        searchResults.value = filtered
        return
    }

    loading.value = true

    try {
        const res = await fetch(`/users/search?q=${encodeURIComponent(query)}`)
        if (res.ok) {
            const data: User[] = await res.json()
            searchResults.value = data
            searchCache[query] = data
        }
    } finally {
        loading.value = false
    }
}

function handleInput(query: string) {
    selectedUser.value = null
    form.user_id = 0

    clearTimeout(searchTimeout)

    searchTimeout = window.setTimeout(() => {
        searchUsers(query)
    }, 300)
}

const isDarkMode = ref(false);

const page = usePage()

onMounted(() => {
    isDarkMode.value = document.documentElement.classList.contains('dark');
});

interface FormData {
    name: string;
    user_id: number;
    description: string;
    processing: boolean;

    settings: {
        max_markets: number | string,
        max_shadows: number | string,
        rate_limit: number | string,
        features: {
            tracking: boolean,
            reports: boolean,
        },
        active: boolean,
    },
}

const form = useForm<FormData>({
    name: '',
    user_id: 0,
    description: '',
    processing: false,

    settings: {
        max_markets: 100,
        max_shadows: 1000000,
        rate_limit: 1000,
        features: {
            tracking: true,
            reports: false,
        },
        active: true,
    }
});

const processing = ref(false);

const errors = reactive<Partial<Record<keyof FormData, string>>>({});

function validate() {
    errors.name = form.name ? '' : 'Operator name is required.';

    return !errors.name;
}

const flashMessage = ref(page.props.flash?.success || page.props.flash?.error || '');

function setFlashFromErrors(errors: Record<string, string | string[]>) {
    flashMessage.value = Object.values(errors)
        .map(val => (Array.isArray(val) ? val[0] : val))
        .join(' ');
}

function submitForm() {
    if (!validate()) return;
    processing.value = true

    form
        .transform(data => ({
            ...data,
        }))
        .post('/publishers', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                flashMessage.value = page.props.flash?.success || 'Operator created successfully!';
            },
            onError: () => {
                setFlashFromErrors(form.errors);
            },
            onFinish: () => {
                processing.value = false;
            },
        });
}

</script>

<template>

    <Head :title="$t('create_operator')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative text-xs flex flex-col gap-4 overflow-x-auto rounded-xl p-4">

            <div class="absolute top-2 left-1/2 -translate-x-1/2 z-20 w-full max-w-sm">
                <FlashMessage type="success" :message="page.props.flash?.success ? $t(page.props.flash.success) : ''" />
                <FlashMessage type="error" :message="page.props.flash?.error ? $t(page.props.flash.error) : ''" />
            </div>

            <form @submit.prevent="submitForm" id="createPublisherForm"
                class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow">

                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $t('create_operator') }}
                    </h2>
                </div>

                <div class="flex flex-col gap-4 md:flex-row md:gap-4">

                    <div class="flex-1">
                        <label id="nameLabel" class="block text-sm text-left font-medium mb-1">{{ $t('name')
                            }}*</label>
                        <input v-model="form.name" type="text" maxlength="255"
                            class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 w-full p-2" />
                        <div class="h-5 text-red-500 text-sm"> {{ errors.name || form.errors.name }}</div>
                    </div>

                    <div class="relative flex-1">
                        <label id="selectOwner" class="block text-sm text-left font-medium mb-1">{{ $t('select_owner')
                            }}*</label>
                        <input type="text" :placeholder="$t('type_to_search_user')" v-model="userInput"
                            @input="handleInput(userInput)"
                            class="w-full px-2 py-1.5 text-sm rounded border bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        <ul v-if="searchResults.length > 0"
                            class="absolute z-50 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg mt-1 w-full max-h-40 overflow-y-auto shadow-lg">

                            <li v-for="c in searchResults.slice(0, 3)" :key="c.id" @click="selectDestination(c)"
                                class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer truncate">
                                {{ c.name }} {{ c.email || '' }}
                            </li>
                        </ul>
                        <!-- Loading indicator -->
                        <div v-if="loading" class="absolute right-2 top-2">
                            <svg class="w-4 h-4 animate-spin text-gray-500" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z">
                                </path>
                            </svg>
                        </div>
                        <div class="h-5 text-red-500 text-sm"> {{ errors.name || form.errors.name }}</div>
                    </div>
                </div>

                <div class="flex flex-col gap-4 md:flex-row md:gap-4 mt-3 md:mt-0">

                    <div class=" flex-1">
                        <label id="rateLimit" class="block text-sm text-left font-medium mb-1">{{ $t('rate_limit')
                            }}*</label>
                        <input v-model.number="form.settings.rate_limit" type="number" min="1" step="1" max="1000"
                            class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 w-full p-2" />
                        <div class="h-5 text-red-500 text-sm">{{ form.errors["settings.rate_limit"] }}</div>
                    </div>

                    <div class=" flex-1">
                        <label id="maxMarkets" class="block text-sm text-left font-medium mb-1">{{ $t('max_markets')
                            }}*</label>
                        <input v-model.number="form.settings.max_markets" type="number" min="1" step="1" max="100"
                            class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 w-full p-2" />
                        <div class="h-5 text-red-500 text-sm">{{ form.errors["settings.max_markets"] }}</div>
                    </div>

                    <div class=" flex-1">
                        <label id="maxShadows" class="block text-sm text-left font-medium mb-1">{{ $t('max_shadows')
                            }}*</label>
                        <input v-model.number="form.settings.max_shadows" type="number" min="1" step="1" max="1000000"
                            class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 w-full p-2" />
                        <div class="h-5 text-red-500 text-sm">{{ form.errors["settings.max_shadows"] }}</div>
                    </div>

                </div>

                <div class="flex flex-col gap-4 md:flex-row md:gap-4 mt-3 md:mt-0">
                    <div class="flex-1 mb-4">
                        <label class="block text-sm font-medium mb-1">{{ $t('features') }}</label>

                        <div class="flex items-center gap-6">
                            <div class="flex items-center">
                                <Checkbox class="dark:bg-gray-900 text-gray-900 dark:text-gray-200 dark:border-gray-600"
                                    v-model="form.settings.features.tracking" />
                                <span class="ml-2">{{ $t('tracking') }}</span>
                            </div>
                            <div class="flex items-center">
                                <Checkbox class="dark:bg-gray-900 text-gray-900 dark:text-gray-200 dark:border-gray-600"
                                    v-model="form.settings.features.reports" />
                                <span class="ml-2">{{ $t('reports') }}</span>
                            </div>

                            <div class="flex-1 flex items-center">
                                <Checkbox class="dark:bg-gray-900 text-gray-900 dark:text-gray-200 dark:border-gray-600"
                                    v-model="form.settings.active" id="active" />
                                <label class="ml-2" for="active">{{ $t('active') }}</label>
                            </div>
                        </div>
                    </div>
                </div>

                <label class="block text-left text-sm font-medium mt-3 mb-1">
                    {{ $t('description') }}
                </label>
                <textarea v-model="form.description" maxlength="384" rows="4"
                    :placeholder="$t('short_description_of_the_market')"
                    class=" w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 p-2 resize-none" />

                <div class="text-xs text-right text-gray-500">
                    {{ form.description.length }} / 384
                </div>

                <div class="mt-5 flex justify-end">
                    <Button type="submit"
                        class="px-4 py-2 rounded-lg shadow cursor-pointer text-white bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed disabled:opacity-70"
                        :disabled="form.processing">
                        {{ $t('create_operator') }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>