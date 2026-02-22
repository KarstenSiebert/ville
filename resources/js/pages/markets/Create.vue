<script setup lang="ts">
import { ref, reactive, onMounted } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { create } from '@/routes/markets';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage, useForm } from '@inertiajs/vue3';
import FlashMessage from "@/components/FlashMessage.vue";
import "@inertiajs/core";
import { VueDatePicker } from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'
import Button from "@/components/ui/button/Button.vue";

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
        title: 'create_market',
        href: create().url,
    },
];

const isDarkMode = ref(false);

const formatDate = 'YYYY-MM-DD'

type OutcomeInput = {
    name: string
    link: string | null
    logo_url?: File | null
}

function addOutcome(): void {
    if (form.outcomes.length < 3) {
        form.outcomes.push({ name: '', link: null, logo_url: null })
    }
}

function removeOutcome(index: number): void {
    if (form.outcomes.length > 2) {
        form.outcomes.splice(index, 1)
    }
}

const page = usePage()

interface Publisher {
    id: number
    name?: string | null
}

const searchResults = ref<Publisher[]>([])
const loading = ref(false)

let searchTimeout: number | undefined

const searchCache = reactive<Record<string, Publisher[]>>({})

const selectedUser = ref<Publisher | null>(null)
const publisherInput = ref('')

function selectDestination(publisher: Publisher) {
    selectedUser.value = publisher
    form.publisher_id = publisher.id
    publisherInput.value = `${publisher.name}`
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
        const filtered = searchCache[prefix].filter(c => safeIncludes(c.name, query)
        )
        searchResults.value = filtered
        return
    }

    loading.value = true

    try {
        const res = await fetch(`/publishers/search?q=${encodeURIComponent(query)}`)
        if (res.ok) {
            const data: Publisher[] = await res.json()
            searchResults.value = data
            searchCache[query] = data
        }
    } finally {
        loading.value = false
    }
}

function handleInput(query: string) {
    selectedUser.value = null
    form.publisher_id = 0

    clearTimeout(searchTimeout)

    searchTimeout = window.setTimeout(() => {
        searchUsers(query)
    }, 300)
}

const marketLogoInput = ref<HTMLInputElement | null>(null)

function openMarketLogoDialog() {
    marketLogoInput.value?.click()
}

function onMarketLogoChange(event: Event) {
    const target = event.target as HTMLInputElement
    if (!target.files || !target.files[0]) return

    form.logo_url = target.files[0]
}

const outcomeInputs = ref<(HTMLInputElement | null)[]>([])

function openOutcomeDialog(index: number) {
    outcomeInputs.value[index]?.click()
}

function onOutcomeLogoChange(event: Event, index: number) {
    const target = event.target as HTMLInputElement
    if (!target.files?.[0]) return

    form.outcomes[index].logo_url = target.files[0]
}

onMounted(() => {
    isDarkMode.value = document.documentElement.classList.contains('dark');
});

interface FormData {
    title: string;
    publisher_id: number | null;
    category: string | null;
    liquidity_b: number | null;
    currency: string;
    start_date: string;
    end_date: string;
    latitude: number | null;
    longitude: number | null;
    logo_url?: File | null;
    description: string;
    processing: boolean;
    outcomes: OutcomeInput[];
}

const form = useForm<FormData>({
    title: '',
    publisher_id: null,
    category: 'General',
    liquidity_b: null,
    currency: 'ADA',
    start_date: '',
    end_date: '',
    latitude: null,
    longitude: null,
    logo_url: null,
    description: '',
    processing: false,
    outcomes: [
        { name: '', link: null, logo_url: null },
        { name: '', link: null, logo_url: null }
    ],
});

const processing = ref(false);

const currencyOptions = ['ADA', 'USDCx', 'USDM', 'USDA', 'USDX', 'SNEK', 'HOSKY', 'NIGHT', 'CHKS', 'WNT'];

const categoryOptions = [
    'general',
    'sports',
    'entertainment',
    'celebrities',
    'politics',
    'business',
    'shopping',
    'fun'
];

const errors = reactive<Partial<Record<keyof FormData, string>>>({});

function validate() {
    errors.title = form.title ? '' : 'Market title is required.';

    if (!form.liquidity_b || form.liquidity_b <= 0) {
        errors.liquidity_b = 'Liquidity must be a positive number.';
    } else if (form.liquidity_b > 1000000000) {
        errors.liquidity_b = 'Liquidity cannot exceed 1,000,000,000.';
    } else {
        errors.liquidity_b = '';
    }

    return !errors.title && !errors.liquidity_b;
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

    const invalidOutcomes = form.outcomes.some(o => !o.name.trim())
    if (invalidOutcomes) {
        errors.outcomes = 'All outcomes must be filled.'
        return
    }

    form
        .transform(data => ({
            ...data,
        }))
        .post('/markets', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                flashMessage.value = page.props.flash?.success || 'Market created successfully!';
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

    <Head :title="$t('create_market')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative text-xs flex flex-col gap-4 overflow-x-auto rounded-xl p-4">

            <div class="absolute top-2 left-1/2 -translate-x-1/2 z-20 w-full max-w-sm">
                <FlashMessage type="success" :message="page.props.flash?.success ? $t(page.props.flash.success) : ''" />
                <FlashMessage type="error" :message="page.props.flash?.error ? $t(page.props.flash.error) : ''" />
            </div>

            <form @submit.prevent="submitForm" id="createMarketForm"
                class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow">

                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $t('create_market') }}
                    </h2>
                </div>

                <div class="flex flex-col gap-4 md:flex-row md:gap-4">

                    <div class="flex-1">
                        <label id="titleLabel" class="block text-sm text-left font-medium mb-1">{{ $t('title')
                            }}*</label>
                        <input v-model="form.title" type="text" maxlength="255"
                            class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 w-full p-2" />
                        <div class="h-5 text-red-500 text-sm"> {{ errors.title || form.errors.title }}</div>
                    </div>

                    <div class="flex-1">
                        <label id="latitudeLabel" class="block text-sm text-left font-medium mb-1">{{ $t('latitude')
                            }}</label>
                        <input v-model="form.latitude" type="number" step="0.00001" min="-90" max="90"
                            class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 w-full p-2" />
                        <div class="h-5 text-red-500 text-sm"> {{ errors.latitude || form.errors.latitude }}</div>
                    </div>

                    <div class="flex-1">
                        <label id="longitudeLabel" class="block text-sm text-left font-medium mb-1">{{ $t('longitude')
                            }}</label>
                        <input v-model="form.longitude" type="number" step="0.00001" min="-180" max="180"
                            class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 w-full p-2" />
                        <div class="h-5 text-red-500 text-sm"> {{ errors.longitude || form.errors.longitude }}</div>
                    </div>

                    <div class="flex-1">
                        <label id="categoryLabel" class="block text-sm text-left font-medium mb-1">{{ $t('category')
                        }}*</label>
                        <select v-model="form.category"
                            class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 w-full p-2">
                            <option value="">{{ $t('select_category') }}</option>
                            <option v-for="s in categoryOptions" :key="s" :value="s">{{ $t(s) }}</option>
                        </select>
                        <div class="h-5"></div>
                    </div>
                </div>

                <div class="flex flex-col gap-4 md:flex-row md:gap-4 mt-3 md:mt-0">

                    <div class=" flex-1">
                        <label id="liquidityLabel" class="block text-sm text-left font-medium mb-1">{{ $t('liquidity')
                            }}*</label>
                        <input v-model.number="form.liquidity_b" type="number" min="1" step="1"
                            class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 w-full p-2" />
                        <div class="h-5 text-red-500 text-sm">{{ errors.liquidity_b || form.errors.liquidity_b }}</div>
                    </div>

                    <div class="flex-1">
                        <label id="currencyLabel" class="block text-sm text-left font-medium mb-1">{{ $t('currency')
                        }}*</label>
                        <select v-model="form.currency"
                            class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 w-full p-2">
                            <option value="">{{ $t('select_currency') }}</option>
                            <option v-for="s in currencyOptions" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>

                    <div class="relative flex-1">
                        <label id="publisher" class="block text-sm text-left font-medium mb-1 mt-3 md:mt-0">{{
                            $t('select_operator')
                            }}*</label>
                        <input type="text" :placeholder="$t('type_to_search_operator')" v-model="publisherInput"
                            @input="handleInput(publisherInput)"
                            class="w-full px-2 py-1.5 text-sm rounded border bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2" />
                        <ul v-if="searchResults.length > 0"
                            class="absolute z-50 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg mt-1 w-full max-h-40 overflow-y-auto shadow-lg">

                            <li v-for="c in searchResults.slice(0, 3)" :key="c.id" @click="selectDestination(c)"
                                class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer truncate">
                                {{ c.name }}
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
                    </div>
                </div>

                <div class="mt-8 sm:mt-6 md:mt-2">

                    <div class="space-y-5">
                        <label id="outcomesLabel" class="block text-sm text-left font-medium mb-1">{{ $t('outcomes')
                        }}*</label>

                        <div v-for="(outcome, index) in form.outcomes" :key="index"
                            class="flex flex-col gap-4 md:flex-row md:gap-4 mt-3 md:mt-0">

                            <div class="flex gap-2 items-center">
                                <input type="file" accept="image/*" class="hidden"
                                    :ref="el => outcomeInputs[index] = el as HTMLInputElement"
                                    @change="e => onOutcomeLogoChange(e, index)" />

                                <Button type="button" size="sm" @click="openOutcomeDialog(index)">
                                    {{ $t('upload_photo') }}
                                </Button>
                                <span v-if="outcome.logo_url" class="text-xs text-gray-500">
                                    {{ outcome.logo_url.name }}
                                </span>
                            </div>
                            <div class="w-full flex gap-2 items-center">
                                <input v-model="outcome.link" type="text" :placeholder="$t('link')"
                                    class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 w-full p-2" />
                            </div>

                            <div class="w-full flex gap-2 items-center">
                                <input v-model="outcome.name" type="text"
                                    :placeholder="$t('e_g_yes_no') + ' (photo < 1 MB)'"
                                    class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 w-full p-2" />
                                <button type="button" class="text-red-500 font-bold" @click="removeOutcome(index)"
                                    :disabled="form.outcomes.length <= 2">
                                    X
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <div class="text-red-500 text-sm min-h-[1rem]">
                                {{ errors.outcomes || form.errors.outcomes }}
                            </div>
                            <div>
                                <Button v-if="form.outcomes.length < 3" type="button" @click="addOutcome"
                                    class="w-full md:w-auto px-4 py-2 rounded-lg shadow cursor-pointer text-white bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed disabled:opacity-70">
                                    {{ $t('add_outcome') }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <label id="editorLabel" class="block text-sm mt-4 text-left font-medium mb-1">{{
                    $t('market_image')
                }}</label>

                <div class="flex gap-3 items-center">
                    <input ref="marketLogoInput" type="file" accept="image/*" class="hidden"
                        @change="onMarketLogoChange" />

                    <Button type="button" size="sm" @click="openMarketLogoDialog">
                        {{ $t('upload_photo') }}
                    </Button>

                    <span v-if="form.logo_url" class="text-xs text-gray-500">
                        {{ form.logo_url.name }}
                    </span>
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
                <div class="flex flex-col gap-4 md:flex-row md:items-end">
                    <div class="flex-1">
                        <label id="startDateLabel" for="startDate" class="block text-sm font-medium mb-1">
                            {{ $t('startdate') }}*
                        </label>
                        <VueDatePicker id="startDate" v-model="form.start_date" :format="formatDate" :dark="isDarkMode"
                            class="w-full" />
                    </div>
                    <div class="flex-1">
                        <label id="endDateLabel" for="endDate" class="block text-sm font-medium mb-1">
                            {{ $t('enddate') }}*
                        </label>

                        <VueDatePicker id="endDate" v-model="form.end_date" :format="formatDate" :dark="isDarkMode"
                            class="w-full" />
                    </div>
                    <div class="md:ml-auto md:mt-0 mt-5">
                        <Button type="submit"
                            class="w-full md:w-auto px-4 py-2 rounded-lg shadow cursor-pointer text-white bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed disabled:opacity-70"
                            :disabled="form.processing">
                            {{ $t('create_market') }}
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>