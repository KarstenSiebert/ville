<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, reactive, onMounted, getCurrentInstance, watchEffect, watch, onUnmounted, toRaw } from 'vue';
import { ZoomOut } from 'lucide-vue-next';
import { type BreadcrumbItem } from '@/types';
import { useAuth } from '@/composables/useAuth'
import axios from 'axios';

import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    LineElement,
    BarElement,
    CategoryScale,
    LinearScale,
    PointElement,
    Filler,
    registerables,
} from 'chart.js';

import type { ChartData, ChartDataset } from 'chart.js';

import zoomPlugin from 'chartjs-plugin-zoom';

ChartJS.register(Filler);

ChartJS.register(
    Title,
    Tooltip,
    Legend,
    LineElement,
    BarElement,
    CategoryScale,
    LinearScale,
    PointElement
);

declare module "@inertiajs/core" {
    interface PageProps {
        flash: {
            success?: string
            error?: string
        }
    }
}

ChartJS.register(...registerables);

ChartJS.register(zoomPlugin);

let debounceTimer: number | null = null;

const loadingOutcomeId = ref<number | null>(null);

const chartRange = ref<'1H' | '6H' | '1D' | '1W' | '1M' | 'ALL'>('1H');

function debouncedUpdatePrice(market: Market, outcomeId: number, buyAmount: number | null) {

    inputAmounts[outcomeId] = buyAmount ?? 0;

    if (!Number.isFinite(buyAmount) || buyAmount! <= 0 || buyAmount == null) {
        return;
    }

    if (debounceTimer) clearTimeout(debounceTimer);

    debounceTimer = window.setTimeout(async () => {
        await updatePrice(market, outcomeId, buyAmount!);
    }, 400);
}

const isDarkMode = ref(false);

const vm = getCurrentInstance();
const $m = vm?.appContext.config.globalProperties.$t;

interface MarketTrade {
    outcome_id: number;
    price: number;
    market_opinion: number;
    quantity: number;
    created_at: string;
}

type BackendTrade = {
    outcome_id: number;
    time: string;
    price: number;
    market_opinion: number;
    volume: number;
};

const trades = ref<MarketTrade[]>([]);

type BackendTradesResponse = Record<string, BackendTrade[]>;

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
    logo_url?: string;
    color: string;
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
    liquidity_b: number;
    currentLiquidity: number;
    b: number;
    close_time: string;
    logo_url?: string;
    base_token: Token;
    allow_limit_orders: boolean;
    outcomes: Outcome[];
}

interface OrderRow {
    limit_price: number
    buy: number
    p: string
    sell: number
}

interface OrderTable {
    [id: string]: {
        [level: string]: OrderRow
    }
}

const props = defineProps<{
    market: Market;
    tokenValue: number;
    orderTable: OrderTable;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: props.market.title, href: `/markets/${props.market.id}` },
];

const page = usePage()

const chartPrice = ref<HTMLCanvasElement | null>(null);

let chartPriceInstance: ChartJS | null = null;

function hexToRgba(hex: string | undefined, alpha: number = 1) {
    if (!hex || !/^#([0-9A-Fa-f]{6})$/.test(hex)) {
        // fallback-Farbe, z.B. helles Grau
        hex = '#999999';
    }
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r},${g},${b},${alpha})`;
}

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

const isZoomed = ref(false)

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

// Limit orders

const limitOrders = reactive<Record<number, {
    side: 'buy' | 'sell'
    price: number | null
    amount: number | null
    expiry: 'GTC' | 'GTD';
    expiry_date: null
}>>({})

const reactiveOrderTable = reactive<{ [id: number]: any }>({})

async function fetchOrderBook() {

    try {
        const response = await axios.get(`/markets/${marketData.id}/orderbook`);

        const orderTable = response.data.orderTable;

        if (orderTable) {
            Object.entries(orderTable).forEach(([id, book]) => {
                reactiveOrderTable[Number(id)] = book;
            });
        }

    } catch (e) {
        console.error('Failed to fetch order book', e);
    }
}

function getLimit(o: Outcome) {

    if (!limitOrders[o.id]) {

        limitOrders[o.id] = {
            side: 'buy',
            price: null,
            amount: null,
            expiry: 'GTC',
            expiry_date: null
        }
    }

    return limitOrders[o.id]
}

function canPlaceLimit(o: Outcome) {
    const l = getLimit(o)

    return (
        activeOutcomeId.value === o.id &&
        l.amount !== null &&
        l.amount > 0 &&
        l.price !== null &&
        l.price > 0 &&
        marketData.status === 'OPEN' &&
        authUser.value
    )
}

const successFlash = reactive<Record<number, boolean>>({});
const errorFlash = reactive<Record<number, boolean>>({});

async function placeLimitOrder(market: Market, outcome: Outcome) {
    const l = getLimit(outcome)

    try {
        const response = await axios.post(`/markets/${market.id}/limit-order`, {
            outcome_id: outcome.id,
            side: l.side,
            price: roundByDecimals(l.price!, market.base_token.decimals),
            expire: l.expiry,
            expire_date: l.expiry_date,
            amount: l.amount,
        })

        if (response.data.success) {
            successFlash[outcome.id] = true;
            setTimeout(() => successFlash[outcome.id] = false, 800);

        } else {
            errorFlash[outcome.id] = true;
            setTimeout(() => errorFlash[outcome.id] = false, 800);
        }

        l.amount = null
        l.price = null

        await fetchOrderBook();

        await fetchTrades();

    } catch (e) {
        console.error('Limit order failed', e)

        errorFlash[outcome.id] = true;
        setTimeout(() => errorFlash[outcome.id] = false, 800);
    }
}

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

const prices = reactive<Record<number, PriceData>>({});

function outcomeShare(market: Market, outcome: Outcome) {
    if (!prices || Object.keys(prices).length === 0) return 0;

    const totalPrice = Object.values(prices).reduce((sum, data) => sum + data.realPrice, 0);

    if (totalPrice === 0) return 0;

    return prices[outcome.id] ? prices[outcome.id].realPrice / totalPrice : 0;
}

function buildProbabilityDataset(outcome: Outcome, lastLabels: string[]) {

    return lastLabels.map(label => {
        const tradesAtTime = trades.value.filter(t => t.created_at === label);

        const trade = tradesAtTime.find(t => t.outcome_id === outcome.id);

        if (!trade) return 0;

        return (trade.market_opinion ?? 0) * 100;
    });
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
    // const emptyColor = isDark ? '#1f2933' : '#e5e7eb';
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

const auth = useAuth();

const authUser = ref(auth.value.user);

watchEffect(() => {
    authUser.value = auth.value.user;
});

const activeOutcomeId = ref<number | null>(null);

const DEFAULT_COLORS = ['#3b82f6', '#10b981', '#ef4444', '#f59e0b', '#ec4899', '#8b5cf6', '#ff9f40'];

const marketData = reactive({
    ...props.market,
    outcomes: props.market.outcomes.map((o, i) => {
        const outcome = {
            ...o,
            buyAmount: 0,
            color: DEFAULT_COLORS[i % DEFAULT_COLORS.length]
        };

        return outcome;
    })
});

const outcomeTokenSums = reactive<Record<number, number>>({});

const tokenValue = ref<number>(props.tokenValue);

const popId = reactive<{ id: number | null }>({ id: null });

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

function roundByDecimals(val: number, decimals: number): number {
    const factor = Math.pow(10, decimals);
    return Math.round(val * factor) / factor;
}

function formatToLess(val: number | undefined | null, decimals: number) {
    if (val === undefined || val === null || !isFinite(val)) {
        return '–';
    }

    const afterComma = 2;

    if (decimals === 0) {
        return Math.round(val).toLocaleString("en-US");
    }

    return val.toLocaleString("en-US", {
        minimumFractionDigits: Math.min(decimals, afterComma),
        maximumFractionDigits: afterComma
    });
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

watchEffect(() => {
    for (const o of props.market.outcomes) {
        const l = limitOrders[o.id]
        if (!l) continue

        if (
            l.side ||
            (l.amount !== null && l.amount > 0) ||
            (l.price !== null && l.price > 0)
        ) {
            activeOutcomeId.value = o.id
            return
        }
    }

    activeOutcomeId.value = null
})

function updatePriceChartWithLabels(lastLabels: string[]): ChartData<'line'> {

    const datasets: ChartDataset<'line', (number | null)[]>[] =
        marketData.outcomes.map(outcome => ({
            type: 'line',
            label: outcome.name,
            data: buildProbabilityDataset(outcome, lastLabels),
            borderColor: hexToRgba(outcome.color, 1),
            tension: 0.25,
            spanGaps: true,
            pointRadius: 0,
            borderWidth: 2,
            fill: false
        }));

    const formattedLabels = lastLabels.length ? lastLabels.map(formatLabel) : [''];

    return {
        labels: formattedLabels,
        datasets
    };
}

async function updatePrice(market: Market, outcomeId: number, buyAmount: number) {
    activeOutcomeId.value = outcomeId;
    loadingOutcomeId.value = outcomeId;

    const outcome = market.outcomes.find(o => o.id === outcomeId);
    if (!outcome) return;

    /*
    router.post(`/markets/${market.id}/price`, {
        outcome_id: outcomeId,
        buy_amount: buyAmount

    });

    return;
    */

    try {
        const response = await axios.post(`/markets/${market.id}/price`, {
            outcome_id: outcomeId,
            buy_amount: buyAmount
        });

        const price = response.data.price;

        if (price) {
            outcome.total_value = price.total_value ?? 0;
            outcome.price = price.price ?? 0;
            outcome.realPrice = price.realPrice ?? 0;
            outcome.chance = price.chance ?? 1;
            outcome.beforeProb = price.before_probs ?? 0;
            outcome.afterProb = price.after_probs ?? 0;
            outcome.chanceIncrease = (outcome.afterProb ?? 0) - (outcome.beforeProb ?? 0);
        }

        const outcomes = response.data.outcomes;

        if (outcomes) {
            const locprice = outcomes.prices;

            if (locprice) {
                Object.entries(locprice).forEach(([id, data]) => {
                    prices[Number(id)] = data as PriceData;
                });
            }

            Object.entries(outcomes).forEach(([id, qty]) => {
                outcomeTokenSums[Number(id)] = Number(qty);
            });

            if (outcomes.liquidity) {
                marketData.currentLiquidity = isFinite(parseBigNumber(outcomes.liquidity))
                    ? parseBigNumber(outcomes.liquidity)
                    : 0;
            }
        }

    } catch (e) {
        console.error('Price calculation failed', e);
        outcome.price = 0;

    } finally {
        loadingOutcomeId.value = null;
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

            if (outcomes.liquidity) {
                marketData.currentLiquidity = isFinite(parseBigNumber(outcomes.liquidity))
                    ? parseBigNumber(outcomes.liquidity)
                    : 0;

                market.currentLiquidity = Number(outcomes.liquidity);
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

            Object.entries(outcomes).forEach(([id, qty]) => {
                outcomeTokenSums[Number(id)] = Number(qty);
            });

            const locprice = outcomes.prices ?? null;

            if (locprice) {
                Object.entries(locprice).forEach(([id, data]) => {
                    prices[Number(id)] = data as PriceData;
                });
            }
        }

        const rprice = response.data.price ?? null;

        if (rprice) {
            outcome.total_value = rprice.total_value ?? 0;
            outcome.price = rprice.price ?? 0;
            outcome.realPrice = rprice.realPrice ?? 0;
            outcome.chance = rprice.chance ?? 1;
            outcome.beforeProb = rprice.before_probs ?? 0;
            outcome.afterProb = rprice.after_probs ?? 0;
            outcome.chanceIncrease = (outcome.afterProb ?? 0) - (outcome.beforeProb ?? 0);
        }

        const token = response.data.tokenValue ?? 0;

        if (token !== undefined && token !== null) {
            tokenValue.value = Number(token);
        }

        await fetchOrderBook();

        await fetchTrades();

    } catch (e) {
        console.error('Buy failed', e);
    }
}

function onlyNumber(event: KeyboardEvent) {
    if (!/[0-9]/.test(event.key)) {
        event.preventDefault();
    }
}

function updatePriceChart(lastLabels: string[]) {
    if (!chartPriceInstance) {
        // renderPriceChart(lastLabels);
        return;
    }

    const priceData = updatePriceChartWithLabels(lastLabels);

    if (priceData) {
        chartPriceInstance.data.labels = priceData.labels;

        chartPriceInstance.data.datasets.forEach((dataset, index) => {
            dataset.data = priceData.datasets[index].data as number[];
        });

        chartPriceInstance.update('none');
    }
}

watch(chartRange, async () => {
    await fetchTrades(true);
});

const MAX_POINTS_BY_RANGE: Record<string, number> = {
    '1H': 60,
    '6H': 60,
    '1D': 48,
    '1W': 56,
    '1M': 30,
    'ALL': 30,
};

function formatLabel(date: string) {
    const d = new Date(date);

    switch (chartRange.value) {
        case '1H':
        case '6H':
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        case '1D':
            return d.toLocaleTimeString([], { hour: '2-digit' });
        case '1W':
        case '1M':
            return d.toLocaleDateString();
        case 'ALL':
            return d.toLocaleDateString([], { month: 'short' });
    }
}

function getMaxPoints() {
    return MAX_POINTS_BY_RANGE[chartRange.value] ?? 60;
}

async function fetchTrades(reset = false) {
    if (reset) {
        chartPriceInstance?.resetZoom();
        isZoomed.value = false;
        trades.value = [];
    }

    const lastTrade = trades.value[trades.value.length - 1];
    const startDate = lastTrade?.created_at;

    try {
        const response = await axios.post(`/markets/${props.market.id}/trades`, null, {
            params: {
                start_date: startDate,
                range: chartRange.value,
            }
        });
        const rawTrades = response.data.trades as BackendTradesResponse;

        if (!rawTrades) {
            console.error('No trades in response!');
            trades.value = [];
            return;
        }

        const newTrades = Object.entries(rawTrades).flatMap(([outcomeId, tradeList]) =>
            tradeList.map(t => ({
                outcome_id: Number(outcomeId),
                price: t.price,
                market_opinion: t.market_opinion,
                quantity: t.volume,
                created_at: t.time
            }))
        );

        newTrades.forEach(t => {
            const existingTrade = trades.value.find(
                e =>
                    e.outcome_id === t.outcome_id &&
                    e.created_at === t.created_at
            );

            if (existingTrade) {
                existingTrade.quantity = t.quantity;
                existingTrade.price = t.price;
                existingTrade.market_opinion = t.market_opinion;

            } else {
                trades.value.push(t);
            }
        });

        if (trades.value.length > getMaxPoints() * marketData.outcomes.length) {
            trades.value = trades.value.slice(-getMaxPoints() * marketData.outcomes.length);
        }

        const labels = [...new Set(trades.value.map(t => t.created_at))].sort(
            (a, b) => new Date(a).getTime() - new Date(b).getTime()
        );

        const lastLabels = labels.slice(-getMaxPoints());

        updatePriceChart(lastLabels);

    } catch (e) {
        console.error('Error fetching trades', e);
    }
}

function renderPriceChart(lastLabels: string[]) {
    if (!chartPrice.value) return;


    const data = updatePriceChartWithLabels(lastLabels);

    if (chartPriceInstance) {
        // chartPriceInstance.destroy();
        // chartPriceInstance = null;
    }

    chartPriceInstance = new ChartJS(chartPrice.value, {
        type: 'line',
        data,
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            animation: { duration: 300 },

            plugins: {
                legend: { position: 'top' },

                zoom: {
                    pan: {
                        enabled: true,
                        mode: 'x',
                        onPanComplete() {
                            isZoomed.value = true
                        },
                        modifierKey: 'shift',
                    },
                    zoom: {
                        wheel: {
                            enabled: true,
                        },
                        onZoomComplete() {
                            isZoomed.value = true
                        },
                        pinch: {
                            enabled: true,
                        },
                        drag: {
                            enabled: true,
                            backgroundColor: 'rgba(59,130,246,0.15)',
                        },
                        mode: 'x',
                    },
                    limits: {
                        x: { min: 'original', max: 'original' },
                        y: { min: 'original', max: 'original' },
                    },
                },

                tooltip: {
                    callbacks: {
                        label: ctx => `${ctx.parsed.y?.toFixed(2)} %`,
                    },
                },
            },

            scales: {
                x: {
                    type: 'category',
                },
                y: {
                    title: {
                        display: true,
                        text: $m ? $m('market_opinion') + '[%]' : 'Market Opinion [%]',
                    },
                },
            },
        },
    });

    chartPrice.value.style.opacity = '1';
}

function resetZoom() {
    chartPriceInstance?.resetZoom();
    isZoomed.value = false;
}

const intervalId = ref<number | null>(null);

onMounted(async () => {
    try {
        await fetchTrades();

        await updateAllPrices();

        await fetchOrderBook();

        const labels = [...new Set(trades.value.map(t => t.created_at))].sort(
            (a, b) => new Date(a).getTime() - new Date(b).getTime()
        );

        const lastLabels = labels.slice(-getMaxPoints());

        renderPriceChart(lastLabels);

        intervalId.value = setInterval(() => {
            fetchFullMarketData();

        }, 10_000);

        isDarkMode.value = document.documentElement.classList.contains('dark');

    } catch (e) {
        console.error(e);
    }
});

onUnmounted(() => {

    if (chartPriceInstance) {
        chartPriceInstance.destroy();
        chartPriceInstance = null;
    }

    if (intervalId.value) {
        clearInterval(intervalId.value);
        intervalId.value = null;
    }
});

async function updateAllPrices() {

    /*
    router.post(`/markets/prices`, {
        market_ids: marketsData.map(m => m.id),
    });
    */

    const marketsData = [marketData];

    try {
        const response = await axios.post('/markets/prices', {
            market_ids: marketsData.map(m => m.id),
        });

        Object.entries(response.data.markets).forEach(([marketId, marketData]: any) => {
            const market = marketsData.find(m => m.id === Number(marketId));
            if (!market) return;

            if (marketData.prices) {
                Object.entries(marketData.prices).forEach(([id, data]) => {
                    prices[Number(id)] = data as PriceData;
                });
            }

            market.currentLiquidity = isFinite(parseBigNumber(marketData.liquidity))
                ? parseBigNumber(marketData.liquidity)
                : 0;

            market.b = isFinite(parseBigNumber(marketData.b))
                ? parseBigNumber(marketData.b)
                : 0;

            Object.entries(marketData.outcomes).forEach(([outcomeId, qty]) => {
                outcomeTokenSums[Number(outcomeId)] = Number(qty);
            });

            market.outcomes.forEach(o => {
                const priceEntry = marketData.prices[String(o.id)];

                if (priceEntry) {
                    const before = priceEntry.before_probs ?? 0;
                    const after = priceEntry.after_probs ?? 0;

                    o.total_value = priceEntry.total_value;
                    o.price = priceEntry.price ?? 0;
                    o.realPrice = priceEntry.realPrice;
                    o.chance = priceEntry.chance ?? 1;
                    o.buyAmount = priceEntry.amount;
                    o.beforeProb = before;
                    o.afterProb = after;
                    o.chanceIncrease = after - before;
                }
            });
        });

    } catch (e) {
        console.error('Bulk price update failed', e);
    }
}

const inputAmounts = reactive<{ [outcomeId: number]: number | null }>({});

marketData.outcomes.forEach(o => {
    inputAmounts[o.id] = inputAmounts[o.id] ?? 0;
});

async function fetchFullMarketData() {

    try {

        const response = await axios.get(`/markets/${marketData.id}/full`, {
            params: { inputAmounts: toRaw(inputAmounts) }
        });

        Object.entries(response.data.orderTable).forEach(([id, book]) => {
            reactiveOrderTable[Number(id)] = book;
        });

        const outcomes = response.data.outcomes.outcomes;

        Object.entries(outcomes).forEach(([id, sum]) => {
            outcomeTokenSums[Number(id)] = Number(sum);
        });

        marketData.currentLiquidity = response.data.outcomes.liquidity;

        marketData.outcomes.forEach(o => {
            const priceData = response.data.outcomes.prices[o.id];
            if (!priceData) return;

            o.total_value = priceData.total_value ?? 0;
            o.price = priceData.price ?? 0;
            o.realPrice = priceData.realPrice ?? 0;
            o.beforeProb = priceData.before_probs;
            o.afterProb = priceData.after_probs;
            o.chance = priceData.chance ?? 1;
            o.chanceIncrease = priceData.after_probs - priceData.before_probs;
        });

        const receivedTrades = response.data.trades as BackendTradesResponse;

        const rawTrades: BackendTrade[] = Array.isArray(receivedTrades?.trades)
            ? receivedTrades.trades
            : Array.isArray(receivedTrades)
                ? receivedTrades
                : [];

        if (rawTrades) {

            const newTrades = rawTrades.map(t => ({
                outcome_id: t.outcome_id,
                price: t.price,
                market_opinion: t.market_opinion,
                quantity: t.volume,
                created_at: t.time
            }));

            newTrades.forEach(t => {
                const existingTrade = trades.value.find(
                    e =>
                        e.outcome_id === t.outcome_id &&
                        e.created_at === t.created_at
                );

                if (existingTrade) {
                    existingTrade.quantity = t.quantity;
                    existingTrade.price = t.price;
                    existingTrade.market_opinion = t.market_opinion;

                } else {
                    trades.value.push(t);
                }
            });

            if (trades.value.length > getMaxPoints() * marketData.outcomes.length) {
                trades.value = trades.value.slice(-getMaxPoints() * marketData.outcomes.length);
            }


            const labels = [...new Set(trades.value.map(t => t.created_at))].sort(
                (a, b) => new Date(a).getTime() - new Date(b).getTime()
            );

            const lastLabels = labels.slice(-getMaxPoints());

            if (chartPriceInstance) {
                updatePriceChart(lastLabels);
            }
        }

    } catch (e) {
        console.error('Failed to fetch full market data', e);
    }
}

</script>

<template>

    <Head :title="marketData.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative text-xs flex flex-col gap-4 overflow-x-auto rounded-xl p-4">

            <div class="absolute top-2 left-1/2 -translate-x-1/2 z-20 w-full max-w-sm">
                <FlashMessage type="success" :message="page.props.flash?.success ? $t(page.props.flash.success) : ''" />
                <FlashMessage type="error" :message="page.props.flash?.error ? $t(page.props.flash.error) : ''" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-4">

                <div
                    class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow flex flex-col gap-2 transition-all duration-200">

                    <!-- Titel + Status -->
                    <div class="flex justify-between items-center">
                        <h1 class="text-xl font-bold truncate">{{ marketData.title }}</h1>
                        <span :class="{
                            'text-green-600 text-lg font-semibold': marketData.status === 'OPEN',
                            'text-gray-500 text-lg font-semibold': marketData.status === 'CLOSED' || marketData.status === 'SETTLED',
                            'text-blue-500 text-lg font-semibold': marketData.status === 'RESOLVED',
                            'text-red-500 text-lg font-semibold': marketData.status === 'CANCELED'
                        }">
                            {{ $t(marketData.status || '') }}
                        </span>
                    </div>

                    <!-- Beschreibung -->
                    <p
                        class="text-sm text-gray-700 dark:text-gray-300 mb-2 text-justify max-h-36 overflow-auto cursor-default">
                        {{ marketData.description && marketData.description.length > 364
                            ? marketData.description.slice(0, 364) + '…'
                            : marketData.description || '\u00A0' }}
                    </p>

                    <!-- Unterer Bereich: Token + Zeit -->
                    <div class="flex items-center justify-between gap-2 cursor-default text-xs mt-auto">
                        <!-- Token Info -->
                        <div class="flex items-center gap-2">
                            <img v-if="marketData.base_token.logo_url" :src="marketData.base_token.logo_url" alt=""
                                class="w-5 h-5 rounded" />
                            <span class="font-mono" v-if="tokenValue">
                                {{ formatToken(Number(tokenValue), market.base_token.decimals) + ' ' +
                                    marketData.base_token.name }}
                            </span>
                        </div>

                        <!-- Zeit -->
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            ⏳ {{ $t(timeLeft(marketData)) }}
                        </span>
                    </div>
                </div>

                <!-- Outcomes List -->
                <div v-for="o in marketData.outcomes" :key="o.id"
                    class="bg-white dark:bg-gray-800 p-4 outcome-hover rounded-lg shadow flex flex-col gap-2 transition-all duration-200"
                    :class="{
                        'bg-green-100 dark:bg-green-700 animate-flash': successFlash[o.id],
                        'bg-red-100 dark:bg-red-700 animate-flash-error': errorFlash[o.id]
                    }">

                    <div class="flex items-stretch gap-2 w-full">
                        <!-- Logo links, volle Höhe -->
                        <img v-if="o.logo_url" :src="o.logo_url" alt="logo"
                            class="w-16 rounded object-cover flex-shrink-0" />

                        <!-- Rest Container (Name + Input) -->
                        <div class="flex-1 flex flex-col justify-between min-w-0">
                            <!-- Name + Share -->
                            <div class="flex justify-between items-center mb-2 min-w-0">
                                <component :is="o.link ? 'a' : 'span'" :href="o.link || null" target="_blank"
                                    rel="noopener noreferrer" class="flex-1 min-w-0 block">
                                    <span
                                        class="block w-full px-2 py-1 rounded text-xs truncate transition-all duration-500"
                                        :style="outcomeNameBarStyleByShare(market, o)">
                                        {{ o.name }}
                                    </span>
                                </component>
                                <span class="font-mono text-xs text-gray-500 dark:text-gray-400 ml-2">
                                    {{ (outcomeShare(marketData, o) * 100).toFixed(2) }}%
                                </span>
                            </div>

                            <!-- Buy Input + Button -->
                            <div class="flex items-center gap-2 w-full min-w-0">
                                <input type="number" v-model.number="o.buyAmount" min="1" step="1"
                                    :disabled="marketData.status !== 'OPEN' || !authUser"
                                    @input="debouncedUpdatePrice(marketData, o.id, o.buyAmount)"
                                    @keypress="onlyNumber($event)"
                                    class="px-2 py-1 border rounded text-sm dark:bg-gray-700 dark:text-gray-200 flex-1 min-w-0" />

                                <span v-if="loadingOutcomeId === o.id" class="w-5 text-center animate-spin">⏳</span>
                                <span v-else class="w-5 text-center"></span>

                                <button
                                    class="px-2 py-1 text-sm rounded text-white transition bg-blue-600 hover:bg-blue-700 cursor-pointer disabled:bg-gray-400 disabled:cursor-not-allowed disabled:hover:bg-gray-400"
                                    :disabled="marketData.status !== 'OPEN' || o.buyAmount <= 0 || !canBuy(o) || !authUser"
                                    @click="buyOutcome(marketData, o)">
                                    {{ $t('vote') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="o.chanceIncrease !== undefined && Number(o.buyAmount) > 0"
                        class="px-2 text-xs text-gray-600 dark:text-gray-400 mt-1 flex justify-between items-center">
                        <!-- Linke Spalte -->
                        <span>{{ $t('change') }}:</span>

                        <!-- Rechte Spalte -->
                        <span class="font-mono flex items-center gap-1">
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
                                {{ formatToLess(o.total_value, market.base_token.decimals) }} {{
                                    market.base_token.name }}
                                <span class="text-gray-400">→</span>
                                {{ formatToLess(o.chance, market.base_token.decimals) }} {{
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

                    <div v-if="market.allow_limit_orders"
                        class="border-t border-gray-200 dark:border-gray-700 mt-2 pt-2 text-xs">

                        <div class="px-2 flex items-center justify-between gap-2">
                            <!-- Linker Text -->
                            <span class="text-gray-500 dark:text-gray-400 font-semibold truncate">
                                {{ $t('limit_order') }}:
                            </span>

                            <div class="px-2 flex items-center gap-2">
                                <label class="flex items-center gap-1 cursor-pointer">
                                    <input type="radio" :name="`expiry-${o.id}`" value="GTC"
                                        v-model="getLimit(o).expiry" class="accent-gray-600" />
                                    <span class="text-xs text-gray-500 dark:text-gray-400 font-semibold">{{ $t('GTC')
                                    }}</span>
                                </label>
                                <label class="flex items-center gap-1 cursor-pointer">
                                    <input type="radio" :name="`expiry-${o.id}`" value="GTD"
                                        v-model="getLimit(o).expiry" class="accent-gray-600" />
                                    <span class="text-xs text-gray-500 dark:text-gray-400 font-semibold">{{ $t('GTD')
                                    }}</span>
                                </label>
                            </div>

                            <!-- Rechter Container für Radio Buttons -->
                            <div class="flex items-center gap-2">
                                <label class="flex items-center gap-1 cursor-pointer">
                                    <input type="radio" :name="`side-${o.id}`" value="buy" v-model="getLimit(o).side"
                                        @change="activeOutcomeId = o.id" class="accent-blue-600" />
                                    <span class="text-xs text-blue-600 font-semibold">{{ $t('buy') }}</span>
                                </label>

                                <label class="flex items-center gap-1 cursor-pointer">
                                    <input type="radio" :name="`side-${o.id}`" value="sell" v-model="getLimit(o).side"
                                        @change="activeOutcomeId = o.id" class="accent-red-600" />
                                    <span class="text-xs text-red-600 font-semibold">{{ $t('sell') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class=" flex items-center justify-between mt-2 gap-2 w-full min-w-0">
                            <!-- Inputs -->
                            <div class="flex gap-2 flex-1 min-w-0 max-w-[50%]">
                                <input type="number" :placeholder="$t('amount')" v-model.number="getLimit(o).amount"
                                    min="1" step="1" @focus="activeOutcomeId = o.id" @keypress="onlyNumber($event)"
                                    class="flex-1 px-2 py-1 border rounded text-xs dark:bg-gray-700 dark:text-gray-200 min-w-0" />


                                <input type="text" :placeholder="$t('token')"
                                    :value="getLimit(o).price !== null ? getLimit(o).price?.toString() : ''"
                                    @focus="activeOutcomeId = o.id" @input="(event: Event) => {
                                        const input = event.target as HTMLInputElement;
                                        let val = input.value;

                                        val = val.replace(/[^0-9,\.]/g, '');

                                        const separator = val.includes(',') ? ',' : val.includes('.') ? '.' : null;

                                        if (separator) {
                                            const parts = val.split(separator);
                                            parts[1] = parts[1].slice(0, props.market.base_token.decimals);
                                            val = parts.join(separator);
                                        }

                                        getLimit(o).price = parseFloat(val.replace(',', '.')) || null;

                                        input.value = val;
                                    }"
                                    class="flex-1 px-2 py-1 border rounded text-xs dark:bg-gray-700 dark:text-gray-200 min-w-0" />

                            </div>

                            <!-- GTD DatePicker -->
                            <div v-if="getLimit(o).expiry === 'GTD'" class="flex-none">
                                <input type="datetime-local" v-model="getLimit(o).expiry_date"
                                    :min="new Date().toISOString().slice(0, 16)"
                                    class="px-2 py-1 border rounded text-xs dark:bg-gray-700 dark:text-gray-200 w-30" />
                            </div>

                            <!-- Button -->
                            <button
                                class="px-2 py-1 text-xs rounded text-white transition disabled:bg-gray-400 disabled:cursor-not-allowed flex-shrink-0"
                                :class="getLimit(o).side === 'sell' ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'"
                                :disabled="!canPlaceLimit(o)" @click="placeLimitOrder(marketData, o)">
                                {{ getLimit(o).side === 'sell' ? $t('sell') : $t('buy') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div
                :class="market.allow_limit_orders ? 'grid grid-cols-1 lg:grid-cols-[1fr_240px] gap-4' : 'bg-white dark:bg-gray-800 p-4 rounded-lg shadow flex flex-col gap-2 transition-all duration-200'">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg"
                    :class="market.allow_limit_orders ? 'rounded-lg shadow flex flex-col gap-2 transition-all duration-200' : ''">

                    <div class="flex items-center justify-between mb-2">
                        <button @click="resetZoom" :disabled="!isZoomed" class="px-2 py-1 rounded transition" :class="isZoomed
                            ? 'bg-blue-600 text-white hover:bg-blue-700'
                            : 'bg-gray-200 dark:bg-gray-700 opacity-50 cursor-not-allowed'">

                            <ZoomOut class="w-4 h-4" />
                        </button>
                        <div class="flex gap-1">
                            <button v-for="r in ['1H', '6H', '1D', '1W', '1M', 'ALL']" :key="r"
                                @click="chartRange = r as any" class="px-2 py-1 rounded text-xs" :class="chartRange === r
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-200 dark:bg-gray-700'">
                                {{ r }}
                            </button>
                        </div>
                    </div>
                    <div
                        class="w-full flex items-center justify-center text-gray-400 dark:text-gray-500 border rounded h-48 sm:h-128 overflow-hidden">
                        <canvas ref="chartPrice"
                            class="w-full h-full opacity-0 transition-opacity duration-500"></canvas>
                    </div>
                </div>

                <div v-if="market.allow_limit_orders"
                    class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow transition-all duration-200">
                    <div v-for="o in marketData.outcomes" :key="o.id">

                        <div class="w-full flex justify-between items-center">
                            <h3 class="text-gray-600 dark:text-gray-400 font-semibold mb-2">
                                {{ $t('orderbook') }}
                            </h3>
                            <span class="text-gray-500 dark:text-gray-400 font-medium mb-2">
                                {{ o.name }}
                            </span>
                        </div>

                        <div v-if="reactiveOrderTable[o.id]"
                            class="mt-1 mb-2 border-gray-200 dark:border-gray-700 border rounded bg-gray-50 dark:bg-gray-900 text-[11px] font-mono">

                            <div class="pb-1 grid grid-cols-[1fr_auto_1fr]">

                                <template v-for="(row, level) in reactiveOrderTable[o.id]" :key="level">

                                    <!-- BUY (links) -->
                                    <div class="text-right pr-2">
                                        <span v-if="row.buy" class="text-green-600 cursor-pointer leading-none"
                                            @click="getLimit(o).side = 'buy'; getLimit(o).price = roundByDecimals(row.limit_price / Math.pow(10, marketData.base_token.decimals), 2); activeOutcomeId = o.id;">
                                            {{ row.buy }}
                                        </span>
                                    </div>

                                    <!-- Prozent / Mid (mitte) -->
                                    <div class="px-1 text-center" :class="row.p === 'Mid'
                                        ? 'bg-blue-500 dark:bg-blue-600 font-bold dark:text-white'
                                        : 'text-gray-500'">
                                        <span v-if="row.p === 'Mid'" class="leading-none">
                                            {{ roundByDecimals(row.limit_price / Math.pow(10,
                                                marketData.base_token.decimals), 2) }}
                                        </span>
                                        <span v-else class="leading-none">
                                            {{ row.p }}
                                        </span>
                                    </div>

                                    <!-- SELL (rechts) -->
                                    <div class="text-left pl-2">
                                        <span v-if="row.sell" class="text-red-600 cursor-pointer leading-none" @click="getLimit(o).side = 'sell'; getLimit(o).price = roundByDecimals(row.limit_price / Math.pow(10, marketData.base_token.decimals), 2);
                                        activeOutcomeId = o.id;">
                                            {{ row.sell }}
                                        </span>
                                    </div>

                                </template>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
