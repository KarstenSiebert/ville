<?php

namespace App\Http\Controllers\Api;

use DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Token;
use App\Models\Market;
use App\Models\Wallet;
use App\Models\Outcome;
use App\Models\Transfer;
use App\Models\MarketTrade;
use App\Models\TokenWallet;
use App\Models\OutcomeToken;
use Illuminate\Http\Request;
use App\Models\MarketLimitOrder;
use App\Http\Controllers\Controller;
use App\Http\Services\OutcomeService;
use App\Http\Services\LimitOrderService;
use App\Http\Services\MarketTradesService;
use App\Http\Services\MarketSettlementService;

class ApiMarketController extends Controller
{
    /**
     * Display all makrkets of a publisher
     */
    public function index(Request $request)
    {
        $market_id = $request->input('market_id') ?? null;
                
        $publisher = $request->publisher;

        $markets = Market::with(['outcomes', 'baseToken', 'wallet.tokenWallets'])
            ->where('publisher_id', $publisher->id)
            ->when($market_id, function ($query) use ($market_id) {
                $query->where('id', $market_id);
            })
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($market) {        
                  
                $marketWallet = $market->wallet;
                $baseTokenWallet = $marketWallet->tokenWallets->firstWhere('token_id', $market->baseToken->id);
        
                $market->currentLiquidity = $baseTokenWallet ? $baseTokenWallet->quantity : 0;
          
                return [
                    'id' => $market->id,
                    'title' => $market->title,
                    'description' => $market->description,
                    'logo_url' => url($market->logo_url),
                    'status' => $market->status,
                    'category' => $market->category,
                    'close_time' => $market->close_time,
                    'currentLiquidity' => $market->currentLiquidity,
                    'b' => $market->b,
                    'base_token' => [
                        'name' => $market->baseToken->name,
                        'decimals' => $market->baseToken->decimals,
                        'logo_url' => url($market->baseToken->logo_url),
                        'minPrice' => $market->baseToken->minPrice,
                    ],
                    'outcomes' => $market->outcomes->map(function ($o) {
                        return [
                            'id' => $o->id,
                            'name' => $o->name,
                            'logo_url' => url($o->logo_url),
                        ];
                    }),
                ];                
            });
                    
        return response()->json($markets);
    }

    /**
     * Store a new market of a publisher
     */
    public function store(Request $request)
    {
        // dd($request->all());
                
        $publisher = $request->publisher;
        
        $currentMarketsCount = Market::where('publisher_id', $publisher->id)->count();

        if ($publisher->markets()->count() >= $publisher->max_markets) {
            return response()->json(['error' => 'Reached maximum of markets.']);
        }

        $user = $publisher->user;
        
        $validated = $request->validate([
            'start_date' => ['nullable', 'string'],
            'end_date' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'liquidity_b' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'currency' => ['required', 'string', 'max:10'],
            'description' => ['nullable', 'string', 'max:384'],
            'allow_limit_orders' => ['nullable', 'string', 'in:yes,no'],
            'logo_url' => ['nullable', 'url', 'max:255'],
            'outcomes' => ['required', 'array', 'min:1'],
            'outcomes.*.name' => ['required', 'string', 'max:255'],
            'outcomes.*.link' => ['nullable', 'url:https', 'max:255'],
            'outcomes.*.logo_url' => ['nullable', 'url', 'max:255'],
        ]);   
        
        $outcomes = collect($validated['outcomes'])
                ->pluck('name')
                ->map(fn($n) => trim($n))
                ->filter()
                ->unique()
                ->values();                

        if (($outcomes->count() < 2) || ($outcomes->count() > 3)) {
            return response()->json(['error' => 'At least two max three unique outcomes required.']);
        }
    
        $currencyToken = Token::where('name', $validated['currency'])->first();

        if (!$currencyToken) {
            return response()->json(['error' => __('token_is_not_available')]);
        }
                
        $tokenLiquidity = bcmul($validated['liquidity_b'], bcpow("10", (string) $currencyToken->decimals));
                
        $availableTotal = DB::table('token_wallet')
            ->join('wallets', 'wallets.id', '=', 'token_wallet.wallet_id')
            ->where('wallets.user_id', $user->id)
            ->where('token_wallet.token_id', $currencyToken->id)
            ->sum(DB::raw('token_wallet.quantity - token_wallet.reserved_quantity'));

        if (bccomp($availableTotal, $tokenLiquidity) < 0) {        
            return response()->json(['error' => __('you_do_not_have_enough_tokens_to_provide_this_liquidity')]);
        }

        $marketWalletAmount = $tokenLiquidity;
        
        $b = $tokenLiquidity;
        
        $product = [];
        
        $title = preg_replace('/\s+/', '', $validated['title']);

        $product['name'] = !empty($title) ? substr($title, 0, 32) : null;
        $product['number'] = !empty($validated['liquidity_b']) ? $validated['liquidity_b'] : 0;
        $product['decimals'] = !empty($validated['decimals']) ? $validated['decimals'] : 0;
              
        $product['currency'] = $validated['currency'];
        
        if ($product['currency'] == 'USDX') {
            $product['fingerprint'] = 'asset1exr3kn78n2j9qnw4g3s5l7fhnf3vnpsxq28d6d';
            $product['decimals'] = 6;

        } else if ($product['currency'] == 'USDM') {
            $product['fingerprint'] = 'asset12ffdj8kk2w485sr7a5ekmjjdyecz8ps2cm5zed';
            $product['decimals'] = 6;

        }  else if ($product['currency'] == 'USDA') {
            $product['fingerprint'] = 'asset16fq594uun90f2jajmecjcdt4jnsnq7r3jdqsw5';
            $product['decimals'] = 6;

        } else if ($product['currency'] == 'HOSKY') {
            $product['fingerprint'] = 'asset17q7r59zlc3dgw0venc80pdv566q6yguw03f0d9';
            $product['decimals'] = 0;

        } else if ($product['currency'] == 'NIGHT') {
            $product['fingerprint'] = 'asset1wd3llgkhsw6etxf2yca6cgk9ssrpva3wf0pq9a';
            $product['decimals'] = 6;

        } else if ($product['currency'] == 'SNEK') {
            $product['fingerprint'] = 'asset108xu02ckwrfc8qs9d97mgyh4kn8gdu9w8f5sxk';
            $product['decimals'] = 0;

        } else if ($product['currency'] == 'CHKS') {
            $product['fingerprint'] = 'asset1945pt2n8zutnygk8qyjh83fmu55a9jnwzfdphr';
            $product['decimals'] = 0;

        } else if ($product['currency'] == 'WNT') {
            $product['fingerprint'] = 'asset1xhl76ah9cgj4cw8ystsuk5he3ltdr4f85vs3zu';
            $product['decimals'] = 6;
        
        }  else if ($product['currency'] == 'ADA') {
            $product['fingerprint'] = '';
            $product['decimals'] = 6;

        } else {
            $product['fingerprint'] = '';
            $product['decimals'] = 0;
        }
               
        if (empty($validated['logo_url'])) {
            $validated['logo_url'] = '/storage/logos/wechselstuben-logo.png';
        }

        // return response()->json($user);

        $market = DB::transaction(function() use ($product, $user, $b, $marketWalletAmount, $currencyToken, $validated) {
        
            $mUser = User::create([
                'name' => $product['name'],
                'type' => 'MARKET',
                'parent_user_id' => $user->id,
                'publisher_id' => $user->publisher_id,
                'profile_photo_path' => $validated['logo_url'],
                'is_system' => true,
            ]);            

            $mUser->assignRole('market');

            $toWallet = new Wallet;

            $toWallet->user_id = $mUser->id;

            $toWallet->address = 'avaaddr1'.bin2hex(openssl_random_pseudo_bytes(27));
                    
            $toWallet->type = 'available';
                                        
            $toWallet->user_type = 'MARKET';

            if ($toWallet->save()) {
                    
                $fingerprint = !empty($product['fingerprint']) ? $product['fingerprint'] : '';
            
                $startTime = $validated['start_date'] ? Carbon::parse($validated['start_date']) : now();
                $closeTime = Carbon::parse($validated['end_date']);

                $isActive = now()->between($startTime, $closeTime);
                
                $element = Market::create([
                    'user_id' => $mUser->id,
                    'wallet_id' => $toWallet->id,
                    'publisher_id' => $user->publisher_id,
                    'title' => $validated['title'],
                    'category' => $validated['category'],
                    'logo_url' => $validated['logo_url'],
                    'description' => $validated['description'],
                    'status' => 'OPEN',
                    'b' => $b,                    
                    'start_time' => $startTime,
                    'close_time' => $closeTime,
                    'liquidity_b' => $marketWalletAmount,
                    'base_token_fingerprint' => $fingerprint,
                    'allow_limit_orders' => $validated['allow_limit_orders'] ? true : false,
                ]);
                
                foreach ($validated['outcomes'] as $outcomeDetails) {
                    
                    $logoPath = null;

                    if (isset($outcomeDetails['logo_url'])) {
                        $logoPath = $outcomeDetails['logo_url'];
                    
                    } else {
                        $logoPath = '/storage/logos/wechselstuben-logo.png';
                    }

                    $outcome = Outcome::create([
                        'user_id' => $mUser->id,
                        'wallet_id' => $toWallet->id,
                        'market_id' => $element->id,
                        'name' => $outcomeDetails['name'],
                        'link' => $outcomeDetails['link'] ?? null,
                        'logo_url' => $logoPath,
                    ]);

                    $tokenName =  substr(preg_replace('/\s+/', '', strtoupper($outcomeDetails['name'].$validated['title'])), 0, 8);

                    $fingerprint = 'asset1'.bin2hex(openssl_random_pseudo_bytes(19));

                    $token = Token::create([
                        'user_id' => $mUser->id,
                        'name' => $tokenName,
                        'token_type' => 'SHARE',
                        'decimals' => 0,
                        'fingerprint' => $fingerprint,
                        'logo_url' => $logoPath,
                    ]);
                    
                    TokenWallet::create([
                        'wallet_id' => $toWallet->id,
                        'token_id' => $token->id,
                        'balance' => 0,
                    ]);

                    OutcomeToken::create([
                        'outcome_id' => $outcome->id,
                        'token_id' => $token->id,
                    ]);
                }
                
                // fromWallet is the wallet of the user belonging to publisher

                $fromWallet = $user->avaWallet;

                if (!empty($fromWallet) && $mUser->can('credit', $toWallet) && $mUser->can('debit', $fromWallet)) {
                    Transfer::execute($fromWallet, $toWallet, $currencyToken, $marketWalletAmount, 'internal', 0, 'APIPLTM', false);
                }                                 
                    
                return $element;
            }         
        });
            
        if (!empty($market)) {
            return $this->market($request, $market->id);
        }
        
        return response()->json(['error' => __('market_not_created')]);
    }

    /**
     * Remove a market
     */
    public function destroy(Request $request)
    {

    }

    /**
     * Show a specific market
     */
    public function market(Request $request, $id)
    {        
        $market = Market::with(['baseToken:id,name,decimals,token_type,fingerprint,logo_url', 'outcomes:id,market_id,name,logo_url'])
                ->where('publisher_id', $request->publisher->id)
                ->where('markets.id', $id)
                ->first();          
      
        if (empty($market)) {
            return response()->json([]);
        }
        
        $market->makeHidden(['liquidity_b']);
        
        $market->logo_url = url($market->logo_url);
            
        if ($market->baseToken) {
            $market->baseToken->logo_url = url($market->baseToken->logo_url);
        }
            
        if ($market->outcomes) {

            foreach ($market->outcomes as $outcome) {
                $outcome->logo_url = url($outcome->logo_url);                  
            }
        }

        $orders = MarketLimitOrder::query()
            ->where('market_id', $market->id)
            ->where('status', 'OPEN')
            ->get(['outcome_id', 'type', 'limit_price', 'share_amount']);
        
        $orderBook = $orders->groupBy('outcome_id')->map(function ($outcomeOrders) {
            return [
                'buy' => $outcomeOrders->where('type', 'BUY')->sortByDesc('limit_price')->values(),
                'sell' => $outcomeOrders->where('type', 'SELL')->sortBy('limit_price')->values(),
            ];
        });
        
        $spreads = [];

        foreach ($market->outcomes as $outcome) {
            $buyOrders  = collect($orderBook[$outcome->id]['buy'] ?? [])->filter(fn($o) => ($o->share_amount - $o->filled) > 0)->sortByDesc('limit_price');            
            $sellOrders = collect($orderBook[$outcome->id]['sell'] ?? [])->filter(fn($o) => ($o->share_amount - $o->filled) > 0)->sortBy('limit_price');

            $highestBid = $buyOrders->first()->limit_price ?? null;
            $lowestAsk  = $sellOrders->first()->limit_price ?? null;

            $spreads[$outcome->id] = (!is_null($highestBid) && !is_null($lowestAsk)) ? $lowestAsk - $highestBid : null;
        }

        $decimals = $market->baseToken->decimals;

        $recentTrades = MarketTrade::where('market_id', $market->id)->latest()->take(20)
                    ->get(['outcome_id', 'price_paid', 'share_amount', 'created_at', 'tx_type'])        
                    ->map(function ($trade) use ($decimals) {                
                        $trade->price_paid = bcdiv($trade->price_paid, bcpow("10", (string) $decimals), $decimals);
        
                        return $trade;
                    });

        $lastPriceInt = MarketTrade::where('market_id', $market->id)->latest('created_at')->value('price_paid') ?? 0;

        $lastPrice = bcdiv($lastPriceInt,  bcpow("10", (string) $decimals), $decimals);

        $stats = [
            'last_price'   => $lastPrice,
            'volume_24h'   => MarketTrade::where('market_id', $market->id)->where('created_at', '>=', now()->subDay())->sum('share_amount'),
            'spreads'      => $spreads,
            'recentTrades' => $recentTrades,
        ];

        $inputAmounts = $request->query('inputAmounts', []);

        $outcomeSums = app(OutcomeService::class)->get($market, $inputAmounts);

        $prices = $outcomeSums['prices'] ?? [];

        if ($prices) {
            $total = array_sum(array_column($prices, 'price'));

            foreach ($prices as $outcomeId => &$data) {
                $data['probability'] = $total > 0 ? $data['price'] / $total : 0;
            }
        }

        unset($outcomeSums['prices']);

        return response()->json([
            'market' => $market, 
            'orderBook' => $orderBook,
            'outcomeSums' => $outcomeSums,
            'prices' => $prices,    
            'stats' => $stats
            ]);
    }

    private function close(Market $market)
    {        
        if ($market->status !== 'OPEN') {
            return false;
        }

        DB::transaction(function () use ($market) {

            foreach ($market->limitOrders()->whereIn('status', ['OPEN','PARTIAL'])->get() as $order) {

                $wallet = Wallet::where('user_id', $order->user_id)
                    ->where('type', 'available')
                    ->whereNull('deleted_at')
                    ->first();

                $tokenId = strtoupper($order->type) === 'BUY' ? $market->baseToken->id : OutcomeToken::where('outcome_id', $order->outcome_id)->value('token_id');

                $tokenWallet = TokenWallet::where('wallet_id', $wallet->id)
                    ->where('token_id', $tokenId)
                    ->where('status', 'active')
                    ->first();

                app(\App\Domain\Orders\OrderReserveService::class)->releaseRemainingReserve($order, $tokenWallet);

                $order->status = 'CANCELED';

                $order->save();
            }

            $market->update(['status' => 'CLOSED']);

            return true;
        });
           
        return false;
    }

    public function resolve(Request $request, $id)
    {        
        $winning_outcome_id = $request->input('winning_outcome_id');
        
        $market = Market::with('outcomes')->where('id', $id)->where('publisher_id', $request->publisher->id)->first();

        if (empty($market)) {
            return response()->json(['error' => __('market_not_found')]);
        }

        $valid = $market->outcomes->contains('id', $winning_outcome_id);

        if (!$valid) {
            return response()->json(['error' => __('winning_outcome_invalid')], 422);
        }

        // Cancel or limit orders and close market

        if ($this->close($market) && $market->status !== 'CLOSED') {
            return response()->json(['error' => __('market_not_closed')]);
        }

        try {
            app(MarketSettlementService::class)->resolveMarket($market, $winning_outcome_id);
                
            return response()->json(['success' => __('market_resolved')]);
                    
        } catch (\DomainException $e) {                                
            return response()->json(['error' => __('market_not_resolved')]);
        }
        
        return response()->json(['error' => __('market_not_resolved')]);        
    }

    public function cancel(Request $request, $id)
    {                
        $market = Market::where('id', $id)->where('publisher_id', $request->publisher->id)->first();

        if (empty($market)) {
            return response()->json(['error' => __('market_not_found')]);
        }
                
        if (!in_array($market->status, ['OPEN', 'CLOSED'])) {
            return response()->json(['error' => __('market_not_cancelable')]);
        }
        
        try {
            app(MarketSettlementService::class)->cancelMarket($market, true);            
                
            return response()->json(['success' => __('market_canceled')]);
                    
        } catch (\DomainException $e) {                                
            return response()->json(['error' => __('market_not_canceled')]);
        }
        
        return response()->json(['error' => __('market_not_canceled')]);        
    }

    public function full(Request $request, $id)
    {
        $startDate = $request->input('start_date', null);
        $range     = $request->input('range', '1H'); 

        $amounts = $request->input('amounts', []);

        // amounts, sind buyAmounts für die outcomes oder null :

        // amounts[outcomeId] = buyAmount ?? 0;
        
        $market = Market::find($id);

        $trades = [];
        $orderBook = [];
        $outcomeSums = [];

        if (!empty($market)) {
            $trades = app(MarketTradesService::class)->get($market, $startDate, $range);

            $orderBook = app(LimitOrderService::class)->get($market);

            $outcomeSums = app(OutcomeService::class)->get($market, $amounts);
        }

        return response()->json([            
            'trades' => $trades,            
            'orderTable' => $orderBook['orderTable'],
            'outcomes' => $outcomeSums,
        ]);
    }        
   
}
