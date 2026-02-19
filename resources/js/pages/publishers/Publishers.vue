<script setup lang="ts">
import { ref, computed, watch } from "vue";
import AppLayout from '@/layouts/AppLayout.vue';
import { index } from '@/routes/publishers';
import { type BreadcrumbItem } from '@/types';
import { Dialog, DialogTrigger, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogClose } from "@/components/ui/dialog";
import Button from "@/components/ui/button/Button.vue";
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import FlashMessage from "@/components/FlashMessage.vue";
import Checkbox from "@/components/ui/checkbox/Checkbox.vue";
import "@inertiajs/core"

declare module "@inertiajs/core" {
    interface PageProps {
        flash: {
            success?: string
            error?: string
        }
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'operators', href: index().url },
];

type Publisher = {
    id: number
    user_id: number
    name: string
    owner: string
    api_key: string
    api_secret: string
    max_markets: number
    max_shadows: number
    rate_limit: number
    tracking: boolean
    reports: boolean
    active: boolean
}

const props = defineProps<{
    publishers: {
        data: Publisher[]
        links: { url: string | null, label: string, active: boolean }[]
        meta: { current_page: number, last_page: number, per_page: number, total: number }
        user: { can_create: boolean, can_delete: boolean }
    }
}>()

const publisherToDelete = ref<Publisher | null>(null);

function confirmDelete(publisher: Publisher) {
    publisherToDelete.value = publisher;
}

function deletePublisherConfirmed() {
    if (!publisherToDelete.value) return;

    const previousPublisher = [...editablePublishers.value];

    editablePublishers.value = editablePublishers.value.filter(
        (c) => c.id !== publisherToDelete.value!.id
    );

    router.delete(`/publishers/${publisherToDelete.value.id}`, {
        data: {
            publisher: publisherToDelete.value
        },
        preserveScroll: true,
        onError: () => {
            editablePublishers.value = previousPublisher;
        },
        onSuccess: () => {
            publisherToDelete.value = null;
        },
    });
}

const editablePublishers = ref<Publisher[]>([])

function savePublisherSettings(publisher: Publisher) {

    router.post(`/publishers/limit/${publisher.id}`, {
        max_markets: publisher.max_markets,
        max_shadows: publisher.max_shadows,
        rate_limit: publisher.rate_limit,
        tracking: publisher.tracking,
        reports: publisher.reports,
        active: publisher.active,
    }, {
        preserveScroll: true,
        preserveState: true,

        onSuccess: (page) => {
            const updatedPublisher = props.publishers.data.find(a => a.id === publisher.id);
            if (updatedPublisher) {
                const index = editablePublishers.value.findIndex(a => a.id === publisher.id);
                if (index !== -1) {
                    editablePublishers.value[index].max_markets = updatedPublisher.max_markets;
                    editablePublishers.value[index].max_shadows = updatedPublisher.max_shadows;
                    editablePublishers.value[index].rate_limit = updatedPublisher.rate_limit;
                    editablePublishers.value[index].active = updatedPublisher.active;
                }
            }
        }
    });
}

watch(
    () => props.publishers,
    (newPublishers) => {
        editablePublishers.value = newPublishers.data.map(a => ({
            id: a.id,
            user_id: a.user_id,
            name: a.name,
            owner: a.owner,
            api_key: a.api_key,
            api_secret: a.api_secret,
            max_markets: a.max_markets,
            max_shadows: a.max_shadows,
            rate_limit: a.rate_limit,
            tracking: a.tracking,
            reports: a.reports,
            active: a.active
        }))
    },
    { immediate: true }
)

const page = usePage()

const form = useForm({
    selected_publishers: [] as Publisher[],
})

const searchQuery = ref("")

const sortField = ref<keyof Publisher>("id")
const sortAsc = ref(true)

function sort(field: keyof Publisher) {
    if (sortField.value === field) sortAsc.value = !sortAsc.value
    else {
        sortField.value = field
        sortAsc.value = true
    }
}

const sortedPublishers = computed(() => {
    return [...editablePublishers.value].sort((a, b) => {
        let valA = a[sortField.value] ?? (sortField.value === "name" ? 0 : "")
        let valB = b[sortField.value] ?? (sortField.value === "name" ? 0 : "")

        if (valA < valB) return sortAsc.value ? -1 : 1
        if (valA > valB) return sortAsc.value ? 1 : -1
        return 0
    })
})

const pagesToShow = computed<(number | string)[]>(() => {
    const total = props.publishers.meta.last_page
    const current = props.publishers.meta.current_page
    const delta = 2
    const range: number[] = []
    const rangeWithDots: (number | string)[] = []
    let last: number | undefined

    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) {
            range.push(i)
        }
    }

    for (const i of range) {
        if (last !== undefined) {
            if (i - last === 2) rangeWithDots.push(last + 1)
            else if (i - last > 2) rangeWithDots.push('...')
        }
        rangeWithDots.push(i)
        last = i
    }

    return rangeWithDots
})

function goTo(pageNum: number) {
    router.get('/publishers', { page: pageNum }, { preserveScroll: true })
}

function submitForm() {
    form.get("/publishers/create")
}

</script>

<template>

    <Head :title="$t('operators')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative text-xs flex flex-col gap-4 overflow-x-auto rounded-xl p-4">

            <div class="absolute top-2 left-1/2 -translate-x-1/2 z-20 w-full max-w-sm">
                <FlashMessage type="success" :message="page.props.flash?.success ? $t(page.props.flash.success) : ''" />
                <FlashMessage type="error" :message="page.props.flash?.error ? $t(page.props.flash.error) : ''" />
            </div>

            <!-- Form -->
            <form @submit.prevent="submitForm" class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow">

                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $t('operators') }}
                    </h2>
                    <input v-model="searchQuery" id="search" type="text" :placeholder="$t('search') + '...'"
                        class="ml-4 px-3 py-2 border rounded-lg text-sm w-64 dark:bg-gray-700 dark:text-gray-200" />
                </div>

                <!-- Table -->
                <div class="overflow-x-auto rounded-lg">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer"
                                    @click="sort('name')">
                                    {{ $t('name') }}
                                </th>
                                <th
                                    class="hidden md:table-cell px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('api_key') }}
                                </th>
                                <th
                                    class="hidden md:table-cell pr-8 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('shadows') }}
                                </th>
                                <th
                                    class="hidden md:table-cell pr-8 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('markets') }}
                                </th>
                                <th
                                    class="hidden md:table-cell pr-8 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('limit') }}
                                </th>
                                <th
                                    class="hidden md:table-cell px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('tracking') }}
                                </th>
                                <th
                                    class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('reports') }}
                                </th>
                                <th
                                    class="px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('status') }}
                                </th>
                                <th
                                    class="px-4 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-default">
                                    {{ $t('actions') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="publisher in sortedPublishers" :key="publisher.id"
                                class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
                                <td
                                    class="px-4 py-2 text-sm text-left text-gray-900 dark:text-gray-200 cursor-default max-w-xs truncate overflow-hidden whitespace-nowrap">
                                    <Link :href="`/deposits/publisher/${publisher.user_id}`"
                                        class="text-blue-600 hover:text-blue-700 dark:text-blue-500 dark:hover:text-blue-400 ">
                                        {{ publisher.name }}
                                    </Link>
                                </td>
                                <td
                                    class="hidden md:table-cell px-4 py-2 text-left text-gray-900 dark:text-gray-200 cursor-default max-w-xs truncate overflow-hidden whitespace-nowrap">
                                    {{ publisher.api_key }}
                                </td>
                                <td
                                    class="hidden md:table-cell px-4 py-2 text-left text-gray-900 dark:text-gray-200 cursor-default max-w-xs overflow-hidden">

                                    <input type="number" v-model.number="publisher.max_shadows" min="1" max="1000000"
                                        step="1" @blur="savePublisherSettings(publisher)"
                                        class="w-20 px-2 py-1 border rounded text-right" />
                                </td>
                                <td
                                    class="hidden md:table-cell px-4 py-2 text-left text-gray-900 dark:text-gray-200 cursor-default max-w-xs overflow-hidden">

                                    <input type="number" v-model.number="publisher.max_markets" min="1" max="100"
                                        step="1" @blur="savePublisherSettings(publisher)"
                                        class="w-20 px-2 py-1 border rounded text-right" />
                                </td>
                                <td
                                    class="hidden md:table-cell px-4 py-2 text-left text-gray-900 dark:text-gray-200 cursor-default max-w-xs overflow-hidden">

                                    <input type="number" v-model.number="publisher.rate_limit" min="1" max="1000"
                                        step="1" @blur="savePublisherSettings(publisher)"
                                        class="w-20 px-2 py-1 border rounded text-right" />
                                </td>
                                <td class="hidden md:table-cell px-4 py-2 text-center">
                                    <Checkbox v-model="publisher.tracking" binary
                                        class="w-4 h-4 dark:bg-gray-900 text-gray-900 dark:text-gray-200 dark:border-gray-600"
                                        @update:modelValue="savePublisherSettings(publisher)" />
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <Checkbox v-model="publisher.reports" binary
                                        class="w-4 h-4 dark:bg-gray-900 text-gray-900 dark:text-gray-200 dark:border-gray-600"
                                        @update:modelValue="savePublisherSettings(publisher)" />
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <Checkbox v-model="publisher.active" binary
                                        class="w-4 h-4 dark:bg-gray-900 text-gray-900 dark:text-gray-200 dark:border-gray-600"
                                        @update:modelValue="savePublisherSettings(publisher)" />
                                </td>

                                <td class="px-8 py-2 text-right cursor-default">
                                    <Dialog>
                                        <DialogTrigger as-child>
                                            <button type="button" @click="confirmDelete(publisher)"
                                                class="relative group p-1" :class="props.publishers.user.can_delete
                                                    ? 'text-red-500 hover:text-red-700 dark:hover:text-red-400 cursor-pointer'
                                                    : 'text-gray-400 cursor-not-allowed'"
                                                :disabled="!props.publishers.user.can_delete"
                                                :aria-label="$t('delete_market')">

                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-trash-icon lucide-trash h-4 w-4">
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" />
                                                    <path d="M3 6h18" />
                                                    <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                                </svg>

                                                <span v-if="props.publishers.user.can_delete"
                                                    class="absolute bottom-full mb-1 left transform -translate-x-[80%] bg-gray-700 text-white text-xs rounded py-1 px-2 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50">
                                                    {{ $t('delete_operator') }}
                                                </span>
                                            </button>

                                        </DialogTrigger>
                                        <DialogContent
                                            v-if="publisherToDelete && publisherToDelete.id === publisher.id">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    {{ $t('delete') }}: {{
                                                        publisher.name }}
                                                </DialogTitle>
                                                <DialogDescription>
                                                    {{ $t('this_action_cannot_be_undone') }}
                                                </DialogDescription>
                                            </DialogHeader>
                                            <DialogFooter class="gap-8">
                                                <DialogClose as-child>
                                                    <Button variant="secondary">{{ $t('cancel') }}</Button>
                                                </DialogClose>
                                                <Button variant="destructive" @click="deletePublisherConfirmed">
                                                    {{ $t('delete') }}
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                </td>
                            </tr>
                            <tr v-if="!sortedPublishers.length">
                                <td colspan="9" class="text-center py-4 text-gray-500">
                                    {{ $t('no_operators_found') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="sortedPublishers.length > 0 && props.publishers.meta.last_page > 1"
                        class="flex justify-center mt-4 mb-4 space-x-1">
                        <button type="button" v-if="props.publishers.meta.current_page > 1"
                            @click="goTo(props.publishers.meta.current_page - 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">{{ $t('prev') }}</button>
                        <template v-for="page in pagesToShow" :key="page">
                            <span v-if="page === '...'" class="px-3 py-1 text-gray-500 select-none">...</span>
                            <button type="button" v-else @click="goTo(Number(page))" class="px-3 py-1 border rounded"
                                :class="page === props.publishers.meta.current_page ? 'bg-blue-500 text-white' : 'bg-gray-200 hover:bg-gray-300'">
                                {{ page }}
                            </button>
                        </template>
                        <button type="button"
                            v-if="props.publishers.meta.current_page < props.publishers.meta.last_page"
                            @click="goTo(props.publishers.meta.current_page + 1)"
                            class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">{{
                                $t('next') }}</button>
                    </div>
                </div>

                <div class="h-5"></div>

                <div class="mt-4 flex justify-end">
                    <Button type="submit"
                        class="px-4 py-2 rounded-lg shadow cursor-pointer text-white bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed disabled:opacity-70"
                        :disabled="form.processing || !props.publishers.user.can_create">
                        {{ $t('create_operator') }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
