<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { Head, usePage, router } from '@inertiajs/vue3';
import { reactive, ref, watchEffect, computed, onMounted, watch } from 'vue';
import { type BreadcrumbItem } from '@/types';
import { useAuth } from '@/composables/useAuth'
import axios from 'axios';

declare module "@inertiajs/core" {
    interface PageProps {
        flash: {
            success?: string
            error?: string
        }
    }
}

const categoryOptions = [
    'all',
    'general',
    'sports',
    'entertainment',
    'celebrities',
    'politics',
    'business',
    'shopping',
    'fun',
];

const selectedCategory = ref<string>('all');

const filteredMarkets = computed(() => {
    if (selectedCategory.value === 'all') {
        return marketsData
    }

    return marketsData.filter(
        m => m.category === selectedCategory.value
    )
})

function outcomeColor(index: number) {
    const colors = [
        'bg-indigo-100 text-indigo-800 dark:bg-indigo-800 dark:text-indigo-200',
        'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200',
        'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200',
        'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-200',
        'bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-yellow-200',
        'bg-purple-100 text-purple-800 dark:bg-purple-800 dark:text-purple-200',
        'bg-pink-100 text-pink-800 dark:bg-pink-800 dark:text-pink-200',
        'bg-teal-100 text-teal-800 dark:bg-teal-800 dark:text-teal-200',
    ];

    return colors[index % colors.length];
}

const outcomeBarColorMap: Record<string, { light: string; dark: string }> = {
    'bg-indigo-100': { light: '#e0e7ff', dark: '#312e81' },
    'bg-green-100': { light: '#dcfce7', dark: '#166534' },
    'bg-red-100': { light: '#fee2e2', dark: '#7f1d1d' },
    'bg-yellow-100': { light: '#fef9c3', dark: '#854d0e' },
    'bg-purple-100': { light: '#f3e8ff', dark: '#6b21a8' },
    'bg-pink-100': { light: '#fce7f3', dark: '#9d174d' },
    'bg-blue-100': { light: '#dbeafe', dark: '#1e40af' },
    'bg-teal-100': { light: '#ccfbf1', dark: '#134e4a' },
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'dashboard', href: dashboard().url },
];

interface Outcome {
    id: number;
    name: string;
    link?: string;
    buyAmount: number;
    price: string | number;
    realPrice: number;
    min_price: number;
    total_value: number;
    amount: number;
    beforeProb?: number;
    afterProb?: number;
    chance: number;
    chanceIncrease?: number;
}

interface Token {
    id: number;
    name: string;
    decimals: number;
    minPrice: number;
    logo_url?: string;
}

interface Market {
    id: number;
    title: string;
    description: string;
    status: string;
    category: string;
    liquidity_b: number;
    currentLiquidity: number;
    b: number;
    close_time: string;
    logo_url?: string;
    base_token: Token;
    outcomes: Outcome[];
}

const props = defineProps<{
    markets: {
        data: Market[]
        links: { url: string | null, label: string, active: boolean }[]
        meta: { current_page: number, last_page: number, per_page: number, total: number }
    }
}>()

const page = usePage();

const auth = useAuth();

const authUser = ref(auth.value.user);

watchEffect(() => {
    authUser.value = auth.value.user;
});

const marketsData = reactive(props.markets.data.map(market => ({
    ...market,
    outcomes: market.outcomes.map(o => ({ ...o, buyAmount: 0 }))
})));

const outcomeTokenSums = reactive<Record<number, number>>({});

const popId = reactive<{ id: number | null }>({ id: null });

interface PriceData {
    price: number;
    realPrice: number;
    min_price: number;
    total_value: number;
    amount: number;
    before_probs: number;
    after_probs: number;
    chance: number;
    currentLiquidity: number;
    outcomes: Record<number, string>;
}

const pricesByMarket = reactive<Record<number, Record<number, PriceData>>>({});

function outcomeShare(market: Market, outcome: Outcome): number {
    const prices = pricesByMarket[market.id];

    if (!prices || Object.keys(prices).length === 0) return 0;

    const totalPrice = Object.values(prices).reduce((sum, data) => sum + data.realPrice, 0);

    if (totalPrice === 0) return 0;

    return prices[outcome.id] ? prices[outcome.id].realPrice / totalPrice : 0;
}

function outcomeNameBarStyleByShare(market: Market, outcome: Outcome) {
    const share = outcomeShare(market, outcome);
    const percent = (share * 100).toFixed(2);

    const index = market.outcomes.findIndex(o => o.id === outcome.id);
    const classString = outcomeColor(index);

    const bgClass = classString.split(' ').find(c => c.startsWith('bg-'))!;
    const colors = outcomeBarColorMap[bgClass];

    const isDark = document.documentElement.classList.contains('dark');
    const fillColor = isDark ? colors?.dark ?? '#374151' : colors?.light ?? '#e5e7eb';
    const emptyColor = isDark ? '#1f2933' : '#fff';

    return `
        background: linear-gradient(
            to right,
            ${fillColor} ${percent}%,
            ${emptyColor} ${percent}%
        );
        color: ${isDark ? '#f9fafb' : '#111827'};
    `;
}

function totalUserBaseTokens(market: Market) {
    if (
        market.currentLiquidity == null ||
        market.b == null ||
        !market.base_token
    ) return '';

    const liquidity = Number(market.currentLiquidity);

    // const decimals = market.base_token.decimals;

    const userTokens = Math.max(liquidity, 0);

    return Math.round(userTokens).toLocaleString("en-US");
    // return formatToken(userTokens, decimals);
}

function formatToken(val: number, decimals: number) {
    const afterComma = 6;

    if (decimals === 0) {
        return Math.round(val).toLocaleString("en-US");
    }

    return val.toLocaleString("en-US", {
        minimumFractionDigits: Math.min(decimals, afterComma),
        maximumFractionDigits: afterComma
    });
}

function parseBigNumber(val: string | number) {
    if (typeof val === 'string') {
        return Number(val.replace(/,/g, ''));
    }
    return Number(val);
}

function canBuy(o: Outcome) {
    const buyAmount = Number(o.buyAmount);

    if (!Number.isFinite(buyAmount)) return false;

    return buyAmount > 0;
}

function timeLeft(market: Market) {
    if (!market.close_time) return "—";

    const now = Date.now();
    const close = new Date(market.close_time).getTime();
    const diffMs = close - now;

    if (diffMs <= 0) return 'expired';

    const totalMinutes = Math.floor(diffMs / 1000 / 60);
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;

    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }
    return `${minutes}m`;
}

function animateSingleOutcome(id: number, newValue: number, duration = 500) {
    const startValue = outcomeTokenSums[id] || 0;
    const diff = newValue - startValue;
    const startTime = performance.now();

    popId.id = id;

    function step(currentTime: number) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);

        outcomeTokenSums[id] = Math.floor(startValue + diff * progress);

        if (progress < 1) {
            requestAnimationFrame(step);
        } else {
            outcomeTokenSums[id] = newValue;

            setTimeout(() => {
                if (popId.id === id) popId.id = null;
            }, 200);
        }
    }

    requestAnimationFrame(step);
}

async function updateAllPrices() {

    try {
        const response = await axios.post('/markets/prices', {
            market_ids: marketsData.map(m => m.id),
        });

        if (response.data.markets) {
            Object.entries(response.data.markets).forEach(([marketId, marketData]: any) => {
                const market = marketsData.find(m => m.id === Number(marketId));
                if (!market) return;

                pricesByMarket[Number(marketId)] = marketData.prices;

                if (marketData.liquidity) {
                    market.currentLiquidity = isFinite(parseBigNumber(marketData.liquidity))
                        ? parseBigNumber(marketData.liquidity)
                        : 0;
                }

                if (marketData.b) {
                    market.b = isFinite(parseBigNumber(marketData.b))
                        ? parseBigNumber(marketData.b)
                        : 0;
                }

                if (marketData.outcomes) {
                    Object.entries(marketData.outcomes).forEach(([outcomeId, qty]) => {
                        outcomeTokenSums[Number(outcomeId)] = Number(qty);
                    });
                }

                if (market.outcomes) {

                    market.outcomes.forEach(o => {
                        if (marketData.prices) {
                            const priceEntry = marketData.prices[String(o.id)];
                            const before = priceEntry.before_probs ?? 0;
                            const after = priceEntry.after_probs ?? 0;

                            o.total_value = priceEntry.total_value;
                            o.price = priceEntry.price;
                            o.realPrice = priceEntry.realPrice;
                            o.chance = priceEntry.chance;
                            o.buyAmount = priceEntry.amount;

                            o.beforeProb = before;
                            o.afterProb = after;

                            o.chanceIncrease = after - before;
                        }
                    });
                }
            });
        }

    } catch (e) {
        console.error('Bulk price update failed', e);
    }
}

async function updatePrice(market: Market, outcomeId: number, buyAmount: number) {
    const outcome = market.outcomes.find(o => o.id === outcomeId);
    if (!outcome) return;

    /*
    router.post(`/markets/${market.id}/price`, {
        outcome_id: outcomeId,
        buy_amount: buyAmount,
    });
    */

    try {
        const response = await axios.post(`/markets/${market.id}/price`, {
            outcome_id: outcomeId,
            buy_amount: buyAmount
        });

        const price = response.data.price;

        if (price) {
            outcome.total_value = price.total_value;
            outcome.price = price.price;
            outcome.realPrice = price.realPrice;
            outcome.beforeProb = price.before_probs ?? 0;
            outcome.afterProb = price.after_probs ?? 0;
            outcome.chance = price.chance;
            outcome.chanceIncrease = (outcome.afterProb ?? 0) - (outcome.beforeProb ?? 0);
        }

        const outcomes = response.data.outcomes;

        if (outcomes) {
            const locprice = outcomes.prices;

            if (locprice) {
                pricesByMarket[Number(market.id)] = locprice;
            }

            Object.entries(outcomes).forEach(([id, qty]) => {
                outcomeTokenSums[Number(id)] = Number(qty);
            });

            if (outcomes.liquidity) {
                market.currentLiquidity = isFinite(parseBigNumber(outcomes.liquidity))
                    ? parseBigNumber(outcomes.liquidity)
                    : 0;
            }
        }

    } catch (e) {
        console.error('Price calculation failed', e);
        outcome.price = 0;
    }
}

async function buyOutcome(market: Market, outcome: Outcome) {

    if (outcome.buyAmount <= 0) return;

    const price = Number(outcome.price);

    if (isNaN(price) || price <= 0) return;

    /*
    router.post(`/markets/${market.id}/buy`, {
        market_id: market.id,
        outcome_id: outcome.id,
        buy_amount: outcome.buyAmount,
        price: price
    });
 
    return;
    */

    try {
        const response = await axios.post(`/markets/${market.id}/buy`, {
            market_id: market.id,
            outcome_id: outcome.id,
            buy_amount: outcome.buyAmount,
            price: price
        });

        const outcomes = response.data.outcomes ?? null;

        if (outcomes) {
            Object.entries(outcomes).forEach(([id, qty]) => {
                outcomeTokenSums[Number(id)] = Number(qty);
            });

            if (outcomes.liquidity) {
                market.currentLiquidity = isFinite(parseBigNumber(outcomes.liquidity))
                    ? parseBigNumber(outcomes.liquidity)
                    : 0;
            }

            if (outcomes.outcomes) {
                Object.entries(outcomes.outcomes).forEach(([id, qty]) => {
                    const numericId = Number(id);
                    const numericQty = Number(qty);

                    if (numericId === outcome.id) {
                        animateSingleOutcome(numericId, numericQty, 500);
                    } else {
                        outcomeTokenSums[numericId] = numericQty;
                    }
                });
            }

            const locprice = outcomes.prices ?? null;

            if (locprice) {
                pricesByMarket[Number(market.id)] = locprice;
            }
        }

        const rprice = response.data.price ?? null;

        if (rprice) {
            outcome.total_value = rprice.total_value;
            outcome.price = rprice.price;
            outcome.realPrice = rprice.realPrice;
            outcome.beforeProb = rprice.before_probs ?? 0;
            outcome.afterProb = rprice.after_probs ?? 0;
            outcome.chance = rprice.chance;
            outcome.chanceIncrease = (outcome.afterProb ?? 0) - (outcome.beforeProb ?? 0);
        }

    } catch (e) {
        console.log('error ' + e);
    }
}

let debounceTimer: number | null = null;

function debouncedUpdatePrice(market: Market, outcomeId: number, buyAmount: number | null) {

    if (!Number.isFinite(buyAmount) || buyAmount! <= 0 || buyAmount == null) {
        return;
    }

    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = window.setTimeout(() => {
        updatePrice(market, outcomeId, buyAmount!);
    }, 400);
}

function onlyNumber(event: KeyboardEvent) {
    if (!/[0-9]/.test(event.key)) {
        event.preventDefault();
    }
}

onMounted(async () => {
    await updateAllPrices();
});

const pagesToShow = computed<(number | string)[]>(() => {
    const total = props.markets.meta.last_page
    const current = props.markets.meta.current_page
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

const urlParams = new URLSearchParams(window.location.search)
const searchQuery = ref(urlParams.get("search") || "")

function goTo(page: number) {
    router.get(
        '/dashboard',
        {
            page,
            search: searchQuery.value || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        }
    )
}

watch(
    () => props.markets.data,
    (newData) => {
        marketsData.splice(0, marketsData.length, ...newData.map(market => ({
            ...market,
            outcomes: market.outcomes.map(o => ({ ...o, buyAmount: 0 }))
        })));
    },
    { immediate: true }
);

</script>

<template>

    <Head :title="$t('dashboard')" />
    <AppLayout :breadcrumbs="breadcrumbs">

        <div class="relative text-xs flex flex-col gap-4 overflow-x-auto rounded-xl p-4">

            <div class="absolute top-2 left-1/2 -translate-x-1/2 z-20 w-full max-w-sm">
                <FlashMessage type="success" :message="page.props.flash?.success ? $t(page.props.flash.success) : ''" />
                <FlashMessage type="error" :message="page.props.flash?.error ? $t(page.props.flash.error) : ''" />
            </div>

            <!-- Category Filter -->
            <div class="flex flex-wrap justify-center gap-2 mb-6">
                <button v-for="cat in categoryOptions" :key="cat" @click="selectedCategory = cat"
                    class="px-3 py-1 rounded-full text-xs font-medium transition border dark:border-gray-600 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-400"
                    :class="selectedCategory === cat
                        ? 'bg-blue-600 text-white border-blue-600 dark:bg-blue-500'
                        : 'bg-gray-100 text-gray-700 border-gray-300 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'">
                    {{ $t(cat) }}
                </button>
            </div>

            <div v-if="filteredMarkets.length === 0" class="text-center text-sm text-gray-500 dark:text-gray-400 py-12">
                {{ $t('no_markets_found_for_this_category') }} 🤷‍♂️
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="market in filteredMarkets" :key="market.id"
                    class="bg-white dark:bg-gray-800 p-4 outcome-hover rounded-lg shadow flex flex-col gap-2 transition-all duration-200">

                    <!-- Title & Status -->
                    <div
                        class="flex justify-between items-center mb-2 group transition-shadow duration-200 cursor-default">
                        <a :href="`/marketdetails/${market.id}`" class="flex-shrink-0">
                            <img v-if="market.logo_url" :src="market.logo_url" :alt="market.title"
                                class="w-12 h-12 rounded-lg transition-transform duration-200 hover:scale-125" />
                        </a>
                        <h2
                            class="px-4 text-lg font-semibold hover:text-blue-600 truncate max-w-xs overflow-hidden text-ellipsis ">
                            <a :href="`/marketdetails/${market.id}`"
                                class="flex-shrink-0 transition-colors duration-200 truncate max-w-xs overflow-hidden text-ellipsis hover:text-blue-600">
                                {{ market.title }}
                            </a>
                        </h2>

                        <span :class="{
                            'text-green-600 text-lg font-semibold': market.status === 'OPEN',
                            'text-gray-500 text-lg font-semibold': market.status === 'CLOSED' || market.status === 'SETTLED',
                            'text-blue-500 text-lg font-semibold': market.status === 'RESOLVED',
                            'text-red-500 text-lg font-semibold': market.status === 'CANCELED'
                        }">
                            {{ $t(market.status || '') }}
                        </span>
                    </div>

                    <!-- Description -->
                    <p
                        class="text-sm text-gray-700 dark:text-gray-300 mb-2 truncate overflow-hidden text-ellipsis cursor-default">
                        {{ market.description || '\u00A0' }}</p>

                    <!-- Outcomes -->
                    <div class="flex flex-col gap-2 mb-2">
                        <div v-for="o in market.outcomes" :key="o.id"
                            class="flex flex-col gap-1 pt-2 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-2">
                                <component :is="o.link ? 'a' : 'span'" :href="o.link || null" target="_blank"
                                    rel="noopener noreferrer" class="flex-1 min-w-0 block">
                                    <span
                                        class="block w-full px-2 py-1 rounded text-xs truncate transition-all duration-500"
                                        :style="outcomeNameBarStyleByShare(market, o)">
                                        {{ o.name }}
                                    </span>
                                </component>

                                <input type="number" v-model.number="o.buyAmount" min="1" step="1"
                                    :disabled="market.status !== 'OPEN' || !authUser"
                                    @input="debouncedUpdatePrice(market, o.id, o.buyAmount)"
                                    @keypress="onlyNumber($event)"
                                    class="w-20 px-2 py-1 border rounded text-xs dark:bg-gray-700 dark:text-gray-200" />

                                <span class="w-12 text-xs text-gray-600 text-right dark:text-gray-400">
                                    <tt>{{ (outcomeShare(market, o) * 100).toFixed(2) + '%' }}</tt>
                                </span>

                                <button
                                    class="px-2 py-1 text-xs rounded text-white transition bg-blue-600 hover:bg-blue-700 cursor-pointer disabled:bg-gray-400 disabled:cursor-not-allowed disabled:hover:bg-gray-400"
                                    :disabled="market.status !== 'OPEN' || o.buyAmount <= 0 || !canBuy(o) || !authUser"
                                    @click="buyOutcome(market, o)">
                                    {{ $t('vote') }}
                                </button>

                            </div>
                            <div v-if="o.chanceIncrease !== undefined && Number(o.buyAmount) > 0"
                                class="px-2 text-xs text-gray-600 dark:text-gray-400 mt-1 flex justify-between items-center">
                                <!-- Linke Spalte -->
                                <span>{{ $t('change') }}:</span>

                                <!-- Rechte Spalte -->
                                <span class="flex font-mono items-center gap-1">
                                    {{ ((o.beforeProb ?? 0) * 100).toFixed(2) }}%
                                    <span class="text-gray-400">→</span>
                                    {{ ((o.afterProb ?? 0) * 100).toFixed(2) }}%
                                    <span class="ml-1 font-semibold text-green-600">
                                        (+{{ ((o.chanceIncrease ?? 0) * 100).toFixed(2) }}%)
                                    </span>
                                </span>
                            </div>

                            <div v-else class="px-2 text-xs text-gray-600 dark:text-gray-400 mt-1">
                                <span>{{ '\u00A0' }}</span>
                            </div>

                            <div v-if="o.chanceIncrease !== undefined && Number(o.buyAmount) > 0"
                                class="px-2 text-xs text-gray-600 dark:text-gray-400 mt-1 flex justify-between items-center">
                                <!-- Linke Spalte -->
                                <span>{{ $t('chance') }}:</span>

                                <!-- Rechte Spalte -->
                                <span class="flex font-mono items-center gap-1">
                                    <template v-if="Number.isFinite(o.price as number)">
                                        {{ formatToken(o.total_value, market.base_token.decimals) }}
                                        {{
                                            market.base_token.name }}
                                        <span class="text-gray-400">→</span>
                                        {{ formatToken(o.chance, market.base_token.decimals) }} {{
                                            market.base_token.name }}
                                    </template>
                                    <template v-else>
                                        –
                                    </template>
                                </span>
                            </div>

                            <div v-else class="px-2 text-xs text-gray-600 dark:text-gray-400 mt-1">
                                <span>{{ '\u00A0' }}</span>
                            </div>

                            <div class="flex items-center justify-between mt-auto cursor-default">
                                <span class="text-xs px-2 text-gray-600 dark:text-gray-400 font-semibold">
                                    {{ $t('token') }}:
                                </span>

                                <span class="px-2 font-mono font-bold transition-all duration-300 ease-out" :class="o.buyAmount > 0
                                    ? 'text-blue-600 dark:text-blue-300'
                                    : 'text-gray-600 dark:text-gray-400'">
                                    <template v-if="Number.isFinite(o.price as number)">
                                        {{ formatToken(o.total_value, market.base_token.decimals) }}
                                    </template>
                                    <template v-else>
                                        –
                                    </template>
                                    {{ market.base_token.name }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Base Token / Liquidity -->
                    <div
                        class="flex justify-between items-center mt-auto pt-2 border-t border-gray-200 dark:border-gray-700 text-sm">
                        <div class="flex items-start gap-2 cursor-default flex-col text-xs">
                            <div class="flex items-center gap-2 cursor-default">
                                <img v-if="market.base_token.logo_url" :src="market.base_token.logo_url" alt=""
                                    class="w-5 h-5 rounded" />
                                <span class="font-mono">
                                    {{
                                        isNaN(market.currentLiquidity) || (market.currentLiquidity == null)
                                            ? ''
                                            : totalUserBaseTokens(market) + ' ' + market.base_token.name
                                    }}
                                </span>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                ⏳ {{ $t(timeLeft(market)) }}
                            </span>
                        </div>
                        <div class="flex flex-col text-xs">
                            <div class="flex flex-wrap gap-2">
                                <div v-for="(o, index) in market.outcomes" :key="o.id" :class="[
                                    'flex flex-col items-center px-2 py-1 rounded text-center min-w-[60px]',
                                    outcomeColor(index)
                                ]">
                                    <span class="text-[10px] font-medium truncate max-w-[70px]">
                                        {{ o.name }}
                                    </span>
                                    <span class="inline-block transition-transform duration-200"
                                        :class="{ 'scale-125 text-blue-600 dark:text-blue-300 font-bold': popId.id === o.id }">
                                        {{ outcomeTokenSums[o.id] ?? 0 }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="props.markets.data.length > 0 && props.markets.meta.last_page > 1"
                class="flex justify-center mt-4 mb-4 space-x-1">
                <button v-if="props.markets.meta.current_page > 1" @click="goTo(props.markets.meta.current_page - 1)"
                    class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700">{{
                        $t('prev') }}</button>
                <template v-for="page in pagesToShow" :key="page">
                    <span v-if="page === '...'" class="px-3 py-1 text-gray-500 select-none">...</span>
                    <button v-else @click="goTo(Number(page))" class="px-3 py-1 border rounded"
                        :class="page === props.markets.meta.current_page ? 'bg-blue-500 text-white' : 'bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-700'">
                        {{ page }}
                    </button>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
