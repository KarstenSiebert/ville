<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, reactive, onMounted, getCurrentInstance, watchEffect, watch, onUnmounted, toRaw } from 'vue';
import { ZoomOut } from 'lucide-vue-next';
import { type BreadcrumbItem } from '@/types';
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

const chartRange = ref<'1H' | '6H' | '1D' | '1W' | '1M' | 'ALL'>('1H');

const isDarkMode = ref(false);

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

const vm = getCurrentInstance();
const $m = vm?.appContext.config.globalProperties.$t;

type BackendTradesResponse = Record<string, BackendTrade[]>;

interface Outcome {
    id: number;
    name: string;
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

const props = defineProps<{
    market: Market;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: props.market.title, href: `/markets/${props.market.id}` },
];

const page = usePage()

const chartRef = ref<HTMLCanvasElement | null>(null);

let chart: ChartJS | null = null;

const chartPrice = ref<HTMLCanvasElement | null>(null);

let chartPriceInstance: ChartJS | null = null;

const chartActiveUsers = ref<HTMLCanvasElement | null>(null);

let chartActiveUsersInstance: ChartJS | null = null;

const activeUsersData = ref<{ time: string; active_users: number }[]>([]);

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
const isPriceZoomed = ref(false)
const isActiveUsersZoomed = ref(false)

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

function totalUserBaseTokens(market: Market) {
    if (
        market.currentLiquidity == null ||
        market.b == null ||
        !market.base_token
    ) return '';

    const liquidity = Number(market.currentLiquidity);
    const b = props.market.b;
    const decimals = market.base_token.decimals;

    // const userTokens = Math.max(liquidity - b, 0) / Math.pow(10, decimals);

    const userTokens = Math.max(liquidity - b, 0);

    return formatToken(userTokens, decimals);
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

function updateChart(): ChartData<'line' | 'bar'> {
    if (!chartRef.value) return { labels: [], datasets: [] };

    const labels = [...new Set(
        trades.value.map(t => t.created_at)
    )].sort((a, b) => new Date(a).getTime() - new Date(b).getTime());

    const datasets: ChartDataset<'line' | 'bar', number[]>[] =
        marketData.outcomes.flatMap(outcome => {
            const outcomeTrades = trades.value.filter(t => t.outcome_id === outcome.id);

            const volumeData = labels.map(label => {
                const tradesAtLabel = outcomeTrades.filter(t => t.created_at === label);

                return tradesAtLabel.length ? tradesAtLabel.reduce((sum, t) => sum + t.quantity, 0) : 0;
            });

            return [
                {
                    type: 'bar',
                    label: `${outcome.name}`,
                    data: volumeData,
                    borderColor: hexToRgba(outcome.color, 0.8),
                    backgroundColor: hexToRgba(outcome.color, 0.4),
                    yAxisID: 'y-left'
                }
            ];
        });

    const formattedLabels = labels.length ? labels.map(formatLabel) : [''];

    return {
        labels: formattedLabels,
        datasets: datasets
    }
}

function updatePriceChart(lastLabels: string[]) {
    if (!chartPriceInstance) {
        renderPriceChart(lastLabels);
        return;
    }

    const priceData = updatePriceChartWithLabels(lastLabels);

    chartPriceInstance.data.labels = priceData.labels;

    chartPriceInstance.data.datasets.forEach((dataset, index) => {
        dataset.data = priceData.datasets[index].data as number[];
    });

    chartPriceInstance.update('none');
}

function updateActiveUsersChart(): ChartData<'line'> {
    if (!chartActiveUsers.value) return { labels: [], datasets: [] };

    const labels = activeUsersData.value.length ? activeUsersData.value.map(u => formatLabel(u.time)) : [''];

    const dataPoints = activeUsersData.value.map(u => u.active_users);

    if (chartActiveUsersInstance) {
        chartActiveUsersInstance.data.labels = labels;
        chartActiveUsersInstance.data.datasets[0].data = dataPoints;
        chartActiveUsersInstance.update('none');
    }

    return {
        labels,
        datasets: [
            {
                label: $m ? $m('active_users') : 'Active Users',
                data: dataPoints,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59,130,246,0.4)',
                fill: true,
                pointRadius: 0,
                borderWidth: 2,
                tension: 0.25,
                yAxisID: 'y-left',
                spanGaps: true,
            },
        ],
    };
}

function renderActiveUsersChart() {
    if (!chartActiveUsers.value) return;

    const data = updateActiveUsersChart();

    chartActiveUsersInstance = new ChartJS(chartActiveUsers.value, {
        type: 'line',
        data,
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            animation: {
                duration: 300,
            },

            plugins: {
                legend: { position: 'top' },

                zoom: {
                    pan: {
                        enabled: true,
                        mode: 'x',
                        onPanComplete() {
                            isActiveUsersZoomed.value = true
                        },
                        modifierKey: 'shift',
                    },
                    zoom: {
                        wheel: {
                            enabled: true,
                        },
                        onZoomComplete() {
                            isActiveUsersZoomed.value = true
                        },
                        pinch: { enabled: true },
                        drag: {
                            enabled: true,
                            backgroundColor: 'rgba(16,185,129,0.15)',
                        },
                        mode: 'x',
                    },
                },
            },

            scales: {
                'y-left': {
                    title: { display: true, text: $m ? $m('users') : 'Users', },

                    ticks: {
                        precision: 0,
                        callback: (value) => String(Math.round(Number(value))),
                    },
                    min: 0,
                },
                x: {
                    stacked: false,
                },
            },
        },
    });

    chartActiveUsers.value.style.opacity = '1';
}

watch(chartRange, async () => {
    trades.value = [];
    activeUsersData.value = [];

    await Promise.all([
        fetchTrades(true),
        fetchActiveUsers(true)
    ]);
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

function getStartDateByRange(): string | null {
    const now = new Date();

    switch (chartRange.value) {
        case '1H':
            now.setHours(now.getHours() - 1);
            break;
        case '6H':
            now.setHours(now.getHours() - 6);
            break;
        case '1D':
            now.setDate(now.getDate() - 1);
            break;
        case '1W':
            now.setDate(now.getDate() - 7);
            break;
        case '1M':
            now.setMonth(now.getMonth() - 1);
            break;
        case 'ALL':
            return null;
    }

    return now.toISOString();
}

async function fetchActiveUsers(reset = false) {
    if (reset) {
        chartActiveUsersInstance?.resetZoom();
        isActiveUsersZoomed.value = false;
    }

    const startDate = getStartDateByRange();

    try {
        const response = await axios.post(`/analytics/${marketData.id}/active`, null, {
            params: {
                start_date: startDate,
                range: chartRange.value,
            }
        });

        activeUsersData.value = response.data.users ?? [];

        if (!chartActiveUsersInstance) {
            renderActiveUsersChart();

        } else {
            updateActiveUsersChart();
        }

    } catch (e) {
        console.error('Error fetching active users', e);
    }
}

async function fetchTrades(reset = false) {
    if (reset) {
        chart?.resetZoom();
        chartPriceInstance?.resetZoom();
        isZoomed.value = false;
        isPriceZoomed.value = false;
        trades.value = [];
    }

    const lastTrade = trades.value[trades.value.length - 1];
    const startDate = lastTrade?.created_at;

    try {
        const response = await axios.post(`/markets/${marketData.id}/trades`, null, {
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

        if (!chart) {
            renderChart();
            return;
        }

        const labels = [...new Set(trades.value.map(t => t.created_at))].sort(
            (a, b) => new Date(a).getTime() - new Date(b).getTime()
        );

        const lastLabels = labels.slice(-getMaxPoints());

        chart.data.datasets.forEach((dataset, index) => {
            const outcome = marketData.outcomes[index];
            const outcomeTrades = trades.value.filter(t => t.outcome_id === outcome.id);

            const newData = lastLabels.map(label => {
                const trade = outcomeTrades.find(t => t.created_at === label);
                return trade?.quantity ?? 0;
            });

            dataset.data = newData;
        });

        const formattedLabels = lastLabels.length ? lastLabels.map(formatLabel) : [''];

        chart.data.labels = formattedLabels;

        chart.update('active');

        updatePriceChart(lastLabels);

    } catch (e) {
        console.error('Error fetching trades', e);
    }
}

function renderPriceChart(lastLabels: string[]) {
    if (!chartPrice.value) return;

    const data = updatePriceChartWithLabels(lastLabels);

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
                            isPriceZoomed.value = true
                        },
                        modifierKey: 'shift',
                    },
                    zoom: {
                        wheel: {
                            enabled: true,
                        },
                        onZoomComplete() {
                            isPriceZoomed.value = true
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
                        text: $m ? $m('market_opinion') + ' [%]' : 'Market Opinion [%]',
                    },
                },
            },
        },
    });

    chartPrice.value.style.opacity = '1';
}

function renderChart() {
    if (!chartRef.value) return;

    const data = updateChart();

    chart = new ChartJS(chartRef.value, {
        type: 'bar',
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
                        pinch: { enabled: true },
                        drag: {
                            enabled: true,
                            backgroundColor: 'rgba(16,185,129,0.15)',
                        },
                        mode: 'x',
                    },
                },
            },

            scales: {
                'y-left': {
                    title: { display: true, text: $m ? $m('volume') : 'Volume' },

                    ticks: {
                        precision: 0,
                        callback: (value) => String(Math.round(Number(value))),
                    },
                    min: 0,
                },
                x: {
                    stacked: false,
                },
            },
        },
    });

    chartRef.value.style.opacity = '1';
}

function resetZoom() {
    chart?.resetZoom();
    chartPriceInstance?.resetZoom();
    chartActiveUsersInstance?.resetZoom();
    isZoomed.value = false;
    isPriceZoomed.value = false;
    isActiveUsersZoomed.value = false;
}

const intervalId = ref<number | null>(null);

onMounted(async () => {
    try {
        await fetchTrades();

        await updateAllPrices();

        await fetchActiveUsers();

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

    if (chart) {
        chart.destroy();
        chart = null;
    }

    if (chartPriceInstance) {
        chartPriceInstance.destroy();
        chartPriceInstance = null;
    }

    if (chartActiveUsersInstance) {
        chartActiveUsersInstance.destroy();
        chartActiveUsersInstance = null;
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

        if (response.data.markets) {
            Object.entries(response.data.markets).forEach(([marketId, marketData]: any) => {
                const market = marketsData.find(m => m.id === Number(marketId));
                if (!market) return;

                if (marketData.prices) {
                    Object.entries(marketData.prices).forEach(([id, data]) => {
                        prices[Number(id)] = data as PriceData;
                    });
                }

                if (marketData.liquidity) {
                    market.currentLiquidity = isFinite(parseBigNumber(marketData.liquidity))
                        ? parseBigNumber(marketData.liquidity)
                        : 0;

                    market.b = isFinite(parseBigNumber(marketData.b))
                        ? parseBigNumber(marketData.b)
                        : 0;
                }

                if (marketData.outcomes) {
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
                }
            });
        }

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

        const outcomes = response.data.outcomes?.outcomes;

        if (outcomes) {
            Object.entries(outcomes).forEach(([id, sum]) => {
                outcomeTokenSums[Number(id)] = Number(sum);
            });

            if (outcomes.liquidity) {
                marketData.currentLiquidity = response.data.outcomes.liquidity;
            }

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
        }

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

            if (!chart) {
                renderChart();

            } else {

                const labels = [...new Set(trades.value.map(t => t.created_at))].sort(
                    (a, b) => new Date(a).getTime() - new Date(b).getTime()
                );

                const lastLabels = labels.slice(-getMaxPoints());

                chart.data.datasets.forEach((dataset, index) => {
                    const outcome = marketData.outcomes[index];
                    const outcomeTrades = trades.value.filter(t => t.outcome_id === outcome.id);

                    const newData = lastLabels.map(label => {
                        const trade = outcomeTrades.find(t => t.created_at === label);
                        return trade?.quantity ?? 0;
                    });

                    dataset.data = newData;
                });

                const formattedLabels = lastLabels.map(formatLabel);

                chart.data.labels = formattedLabels;

                chart.update('active');

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
                    class="flex-1 bg-white dark:bg-gray-800 p-4 rounded-lg shadow flex flex-col gap-2 transition-all duration-200">

                    <!-- Title & Status -->
                    <div
                        class="flex justify-between items-center mb-2 group transition-shadow duration-200 cursor-default">
                        <a :href="`/marketdetails/${marketData.id}`" class="flex-shrink-0">
                            <img v-if="marketData.logo_url" :src="marketData.logo_url" :alt="marketData.title"
                                class="w-12 h-12 rounded-lg transition-transform duration-200 hover:scale-125" />
                        </a>
                        <h2
                            class="px-4 text-lg font-semibold hover:text-blue-600 truncate max-w-xs overflow-hidden text-ellipsis ">
                            <a :href="`/marketdetails/${marketData.id}`"
                                class="flex-shrink-0 transition-colors duration-200 truncate max-w-xs overflow-hidden text-ellipsis hover:text-blue-600">
                                {{ marketData.title }}
                            </a>
                        </h2>

                        <span :class="{
                            'text-green-600 text-lg font-semibold': marketData.status === 'OPEN',
                            'text-gray-500 text-lg font-semibold': marketData.status === 'CLOSED' || marketData.status === 'SETTLED',
                            'text-blue-500 text-lg font-semibold': marketData.status === 'RESOLVED',
                            'text-red-500 text-lg font-semibold': marketData.status === 'CANCELED'
                        }">
                            {{ $t(marketData.status || '') }}
                        </span>
                    </div>

                    <!-- Description -->
                    <p
                        class="text-sm text-gray-700 dark:text-gray-300 mb-2 truncate overflow-hidden text-ellipsis cursor-default">
                        {{ marketData.description || '\u00A0' }}</p>

                    <!-- Outcomes -->
                    <div class="flex flex-col gap-2 mb-2">
                        <div v-for="o in marketData.outcomes" :key="o.id"
                            class="flex flex-col gap-1 pt-2 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-2">
                                <span class="flex-1 px-2 py-1 rounded text-xs truncate transition-all duration-500"
                                    :style="outcomeNameBarStyleByShare(marketData, o)">
                                    {{ o.name }}
                                </span>

                                <span class="w-12 text-xs text-gray-600 text-right dark:text-gray-400">
                                    <tt>{{ (outcomeShare(marketData, o) * 100).toFixed(2) + '%' }}</tt>
                                </span>

                            </div>

                        </div>
                    </div>

                    <!-- Base Token / Liquidity -->
                    <div
                        class="flex justify-between items-center mt-auto pt-2 border-t border-gray-200 dark:border-gray-700 text-sm">
                        <div class="flex items-start gap-2 cursor-default flex-col text-xs">
                            <div class="flex items-center gap-2 cursor-default">
                                <img v-if="marketData.base_token.logo_url" :src="marketData.base_token.logo_url" alt=""
                                    class="w-5 h-5 rounded" />
                                <span class="font-mono">
                                    {{
                                        isNaN(marketData.currentLiquidity)
                                            ? ''
                                            : totalUserBaseTokens(marketData) + ' ' + marketData.base_token.name
                                    }}
                                </span>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                ⏳ {{ $t(timeLeft(marketData)) }}
                            </span>
                        </div>
                        <div class="flex flex-col text-xs">
                            <div class="flex flex-wrap gap-2">
                                <div v-for="(o, index) in marketData.outcomes" :key="o.id" :class="[
                                    'flex flex-col items-center px-2 py-1 rounded text-center min-w-[60px]',
                                    outcomeColor(index)
                                ]">
                                    <span class="text-[10px] font-medium truncate max-w-[70px]">
                                        {{ o.name }}
                                    </span>
                                    <span class="inline-block transition-transform duration-200">
                                        {{ outcomeTokenSums[o.id] ?? 0 }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="flex-1 bg-white dark:bg-gray-800 p-4 rounded-lg shadow flex flex-col gap-2 transition-all duration-200">

                    <div class="flex items-center justify-between mb-2">
                        <button @click="resetZoom" :disabled="!isActiveUsersZoomed" class="px-2 py-1 rounded transition"
                            :class="isActiveUsersZoomed
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
                        class="w-full flex items-center justify-center text-gray-400 dark:text-gray-500 border rounded h-64 overflow-hidden">
                        <canvas ref="chartActiveUsers"
                            class="w-full h-full opacity-0 transition-opacity duration-500"></canvas>
                    </div>
                </div>
            </div>

            <div class="flex flex-col lg:flex-row gap-4">

                <div
                    class="flex-1 bg-white dark:bg-gray-800 p-4 rounded-lg shadow flex flex-col gap-2 transition-all duration-200">

                    <div class="flex items-center justify-between mb-2">
                        <button @click="resetZoom" :disabled="!isPriceZoomed" class="px-2 py-1 rounded transition"
                            :class="isPriceZoomed
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
                        class="w-full flex items-center justify-center text-gray-400 dark:text-gray-500 border rounded h-64 overflow-hidden">
                        <canvas ref="chartPrice"
                            class="w-full h-full opacity-0 transition-opacity duration-500"></canvas>
                    </div>
                </div>

                <div
                    class="flex-1 bg-white dark:bg-gray-800 p-4 rounded-lg shadow flex flex-col gap-2 transition-all duration-200">

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
                        class="w-full flex items-center justify-center text-gray-400 dark:text-gray-500 border rounded h-64 overflow-hidden">
                        <canvas ref="chartRef" class="w-full h-full opacity-0 transition-opacity duration-500"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
