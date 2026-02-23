<?php

namespace App\Http\Controllers;

use DB;
use Exception;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Token;
use App\Models\Market;
use App\Models\Wallet;
use BaconQrCode\Writer;
use App\Models\Outcome;
use App\Models\Transfer;
use App\Models\MarketTrade;
use App\Models\TokenWallet;
use App\Models\InputOutput;
use App\Models\OutcomeToken;
use Illuminate\Http\Request;
use App\Models\MarketLimitOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use BaconQrCode\Renderer\Color\Rgb;
use App\Http\Services\OutcomeService;
use BaconQrCode\Renderer\ImageRenderer;
use App\Http\Services\LimitOrderService;
use Illuminate\Support\Facades\Validator;
use App\Http\Services\MarketTradesService;
use BaconQrCode\Renderer\RendererStyle\Fill;
use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Services\LimitOrderMatchingService;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MarketController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {           
        $search = $request->input('search', null);
                
        $page = (int) $request->input('page', 1);
            
        $perPage = 10;
        
        $user = auth()->user();

        $isAdmin = ($user->email == config('chimera.admin_user')) ? true : false;
                              
        if ($isAdmin) {
            $markets = Market::with(['baseToken', 'outcomes'])                
                ->withCount(['outcomes', 'subscribers'])
                // ->running()
                ->limit(500)
                ->get();
        } else {
            $markets = Market::with(['baseToken', 'outcomes'])
                ->withCount(['outcomes', 'subscribers'])
                ->whereHas('user', function ($q) use ($user) {
                    $q->where('parent_user_id', $user->id);
                })                
                ->running()
                ->limit(100)
                ->get();
        }
             
        // dd($markets);

        if (empty($markets)) {
            $paginated = new LengthAwarePaginator([], 0, $perPage, $page);
        
        } else {
            $paginated = new LengthAwarePaginator(
                $markets->forPage($page, $perPage)->values(),
                $markets->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => $request->query()]
            );
        }   
                
        return Inertia::render('markets/Markets', [
            'assets' => [
                'data' => $paginated->items(),
                'links' => $paginated->linkCollection()->map(function($link){
                    if ($link['label'] === '&laquo; Previous') $link['label'] = 'Prev';
                    if ($link['label'] === 'Next &raquo;') $link['label'] = 'Next';
                    return $link;
                }),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
                'user' => [
                    'can_create'  => $user->can('create_markets'),
                    'can_resolve' => $user->hasRole('admin'),
                    'can_close'   => $user->can('create_markets'),
                    'can_cancel'  => $user->hasRole('admin'),
                    'can_delete'  => $user->hasRole('admin')
                ]
            ]            
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('markets/Create', [
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // dd($request->all());

        $user = auth()->user();        

        $validated = $request->validate([
            'start_date' => ['nullable', 'string'],
            'end_date' => ['required', 'string'],
            'publisher_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'liquidity_b' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'max_subscribers' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'currency' => ['required', 'string'],        
            'description' => ['nullable', 'string', 'max:384'],
            'latitude' => ['nullable', 'numeric', 'min:-90.0', 'max:90.0'],
            'longitude' => ['nullable', 'numeric', 'min:-180.0', 'max:180.0'],
            'logo_url' => ['nullable', 'image', 'max:2048'],
            'outcomes.*.name' => ['required', 'string', 'max:255'],
            'outcomes.*.link' => ['nullable', 'url:https', 'max:255'],
            'outcomes.*.logo_url' => ['nullable', 'image', 'max:2048'],
        ]);        

        $outcomes = collect($validated['outcomes'])
                ->pluck('name')
                ->map(fn($n) => trim($n))
                ->filter()
                ->unique()
                ->values();                

        if ($outcomes->count() < 2) {
            throw new \Exception('At least two unique outcomes required.');
        }
        
        $currencyToken = Token::where('name', $validated['currency'])->first();

        if (!$currencyToken) {
            return back()->with(['error' => __('token_is_not_available')]);
        }
        
        // Token with decimals are multiple of their real value
        
        $tokenLiquidity = bcmul($validated['liquidity_b'], bcpow("10", (string) $currencyToken->decimals));
                
        $availableTotal = DB::table('token_wallet')
            ->join('wallets', 'wallets.id', '=', 'token_wallet.wallet_id')
            ->where('wallets.user_id', $user->id)
            ->where('token_wallet.token_id', $currencyToken->id)
            ->sum(DB::raw('token_wallet.quantity - token_wallet.reserved_quantity'));

        if (bccomp($availableTotal, $tokenLiquidity) < 0) {        
            return back()->with(['error' => __('you_do_not_have_enough_tokens_to_provide_this_liquidity')]);
        }
      
        $marketWalletAmount = $tokenLiquidity;

        // Reduce liquidity to normal value

        $tokenLiquidity = $validated['liquidity_b'];

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

        } else if ($product['currency'] == 'USDCx') {
            $product['fingerprint'] = 'asset1e7eewpjw8ua3f2gpfx7y34ww9vjl63hayn80kl';
            $product['decimals'] = 6;

        } else if ($product['currency'] == 'USDA') {
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

        if ($request->hasFile('logo_url')) {
            $validated['logo_url'] = '/storage/'.$request->file('logo_url')->store('market-logos', 'public');
        }
               
        if (empty($validated['logo_url'])) {
            $validated['logo_url'] = '/storage/logos/wechselstuben-logo.png';
        }
                                    
        $market = DB::transaction(function() use ($product, $user, $b, $marketWalletAmount, $currencyToken, $validated) {
        
            $mUser = User::create([
                'name' => $product['name'],
                'type' => 'MARKET',
                'parent_user_id' => $user->id,                
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
                
                // max, min trade amount setzen!!!
                
                $element = Market::create([
                    'user_id' => $mUser->id,
                    'wallet_id' => $toWallet->id,
                    'publisher_id' => $validated['publisher_id'] > 0 ? $validated['publisher_id'] : null,
                    'title' => $validated['title'],
                    'category' => $validated['category'],
                    'logo_url' => $validated['logo_url'],
                    'description' => $validated['description'],
                    'status' => 'OPEN',
                    'b' => $b,                    
                    'max_subscribers' => $validated['max_subscribers'] ? $validated['max_subscribers'] : 100000,
                    'start_time' => $validated['start_date'] ? $validated['start_date'] : now(),
                    'close_time' => $validated['end_date'],
                    'latitude' => $validated['latitude'] ? round($validated['latitude'], 4) : null,
                    'longitude' => $validated['longitude'] ? round($validated['longitude'], 4) : null,
                    'liquidity_b' => $marketWalletAmount,
                    'base_token_fingerprint' => $fingerprint,
                ]);
                
                foreach ($validated['outcomes'] as $outcomeDetails) {
                    
                    $logoPath = null;

                    if (isset($outcomeDetails['logo_url']) && $outcomeDetails['logo_url'] instanceof UploadedFile) {
                        $logoPath = '/storage/'.$outcomeDetails['logo_url']->store('outcome-logos', 'public');
                    
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

                // Finally transfer the amount to the new wallet

                $fromWallet = Wallet::where('user_id', $user->id)->where('type', 'available')->first();
                
                if (!empty($fromWallet) && $mUser->can('credit', $toWallet) && $mUser->can('debit', $fromWallet)) {
                    Transfer::execute($fromWallet, $toWallet, $currencyToken, $marketWalletAmount, 'internal', 0, 'PLTM', false);
                }                                 
                    
                return $element;
            }         
        });
            
        if (!empty($market)) {
            return redirect('markets')->with('success', __('market_created_successfully'));
        }
        
        return redirect()->back()->with('error', __('market_not_created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Market $market)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Market $market)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Market $market)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $market = Market::find($id);

        if (empty($market)) {
            return back()->with('error', __('market_does_not_exist'));
        }

        if (!in_array($market->status, ['CANCELED', 'SETTLED'])) {
            return back()->with('error', __('market_not_removable'));
        }
        
        if ($this->authorize('delete', $market)) {
                                    
            $user = User::find($market->user_id);

            if (!$user) {
                return redirect()->back()->with('error', __('market_not_removed'));
            }

            DB::transaction(function () use ($market, $user) {
                
                $restToken = Token::where('fingerprint', $market->base_token_fingerprint)->first();

                if (!$restToken) {
                    return redirect()->back()->with('error', __('market_not_removed'));
                }
                      
                $restTotal = DB::table('token_wallet')
                    ->join('wallets', 'wallets.id', '=', 'token_wallet.wallet_id')
                    ->where('wallets.user_id', $market->user_id)
                    ->where('token_wallet.token_id', $restToken->id)
                    ->sum(DB::raw('token_wallet.quantity - token_wallet.reserved_quantity'));
      
                if ($restTotal > 0) {

                    $fromWallet = Wallet::where('user_id', $user->id)->where('type', 'available')->first();
                    $toWallet = Wallet::where('user_id', $user->parent_user_id)->where('type', 'available')->first();

                    // Canceled, 10% goes to platform
                                        
                    if ($market->status == 'CANCELED') {

                        $rest90Prz = intval(round($restTotal * 0.90), 2);
                        $cancelFee = $restTotal - $rest90Prz;

                        $adminWallet = Wallet::where('user_id', 1)->where('type', 'available')->first();
                        
                        if (($cancelFee > 0) && ($adminWallet) && Transfer::execute($fromWallet, $adminWallet, $restToken, $cancelFee, 'internal', 0, 'CANCEL FEE', false)) {

                            MarketTrade::create([
                                'market_id'    => $market->id,
                                'user_id'      => $adminWallet->user_id,
                                'outcome_id'   => null,
                                'share_amount' => 0,
                                'price_paid'   => $cancelFee,                                
                                'tx_type'      => 'ADJUST'
                            ]);

                            $restTotal = $rest90Prz;
                        }
                    }
                        
                    if (!empty($fromWallet) && $user->can('credit', $toWallet) && $user->can('debit', $fromWallet)) {

                        if (Transfer::execute($fromWallet, $toWallet, $restToken, $restTotal, 'internal', 0, 'DMPT', false)) {

                            MarketTrade::create([                            
                                'market_id'    => $market->id,
                                'user_id'      => $user->parent_user_id,
                                'outcome_id'   => null,
                                'share_amount' => 0,
                                'price_paid'   => $restTotal,                                
                                'tx_type'      => 'ADJUST'
                            ]);
                        }
                    }  
                }
           
                $outcomes = $market->outcomes()->get();
                    
                // Delete the reference to the SHARE tokens

                foreach ($outcomes as $outcome) {                    
                    OutcomeToken::where('token_id', $outcome->outcomeToken->token_id)->delete();
                }
            
                // Delete the wllet of the market, no longer needed

                TokenWallet::where('wallet_id', $market->wallet_id)->delete();
                
                // Delete the SHARE tokens, those were just for this market

                Token::where('user_id', $user->id)->where('token_type', 'SHARE')->where('status', 'active')->delete();

                // Delete the outcomes for the market
                 
                Outcome::where('market_id', $market->id)->delete();

                // The market wallet is no longer needed

                Wallet::where('user_id', $market->user_id)->where('id', $market->wallet_id)->delete();                

                // Delete the market itself and the market user, who was just for the market
                
                $market->subscribers()->detach();  

                $market->delete();

                $user->delete();
            });
            
            return redirect()->back()->with('success', __('market_removed_successfully'));
        }

        return redirect()->back()->with('error', __('market_not_removed'));
    }

    public function buy(Request $request, Market $market)
    {
        // dd($request->all());

        $request->validate([
            'outcome_id' => ['required', 'integer'],
            'buy_amount' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0.000001'],
        ]);

        $user = auth()->user();        
    
        $buyAmount = $request->buy_amount;

        $baseToken = $market->baseToken;
        $decimals  = $baseToken->decimals;
               
        $currentPriceFloat = round($this->getCurrentOutcomePrice($market, $request->outcome_id, $buyAmount), $decimals);

        $currentPriceInt = (int) ceil($currentPriceFloat * pow(10, $decimals));

        $priceFloat = $request->price;
        
        $priceInt = (int) ceil($priceFloat * pow(10, $decimals));
        
        // Price changed, we currently fo not have slippage

        $marketWallet = Wallet::find($market->wallet_id);   

        $outcomeId = $request->outcome_id;

        if (($buyAmount < $market->min_trade_amount) || ($buyAmount > $market->max_trade_amount)) {
            
            Log::debug('buy '.$buyAmount . ' - ' . $market->min_trade_amount . ' - ' . $market->max_trade_amount);

            $q = $this->getOutcomeQuantities($market); 

            $liquidity = app(OutcomeService::class)->getLiquidity($market);

            $calc = app(OutcomeService::class)->calculateLmsrPrice($market, $outcomeId, $buyAmount);         
            
            return response()->json(['liquidity' => $liquidity, 'price' => $calc, 'outcomes' => $q]);
        }    

        if ($priceInt < $currentPriceInt) {     
            
            Log::debug('buy '.$buyAmount . ' - ' . $priceInt . ' - ' . $currentPriceInt);

            $q = $this->getOutcomeQuantities($market); 

            $liquidity = app(OutcomeService::class)->getLiquidity($market);

            $calc = app(OutcomeService::class)->calculateLmsrPrice($market, $outcomeId, $buyAmount);         
            
            return response()->json(['liquidity' => $liquidity, 'price' => $calc, 'outcomes' => $q]);            

        } else {
            // $priceInt = $currentPriceInt;            
        }   
        
        $userWallet  = Wallet::where('user_id', $user->id)->where('type', 'available')->first();

        $adminWallet = Wallet::where('user_id', 1)->where('type', 'available')->first(); 

        if (empty($marketWallet) || empty($userWallet) || empty($adminWallet)) {

            Log::debug('buy wallet empty');

            $liquidity = app(OutcomeService::class)->getLiquidity($market);
            
            $calc = app(OutcomeService::class)->calculateLmsrPrice($market, $outcomeId, $buyAmount);
            
            return response()->json(['liquidity' => $liquidity, 'price' => $calc, 'outcomes' => $q]); 
        }

        $userTokenWallet = TokenWallet::firstOrCreate(
            ['wallet_id' => $userWallet->id, 'token_id' => $baseToken->id],
            ['quantity' => 0, 'reserved_quantity' => 0]
        );

        $available = $userTokenWallet->quantity - $userTokenWallet->reserved_quantity;
                              
        if ($available < $priceInt) {
            
            Log::debug('buy '.$available . ' - ' . $priceInt);

            $q = $this->getOutcomeQuantities($market); 

            $liquidity = app(OutcomeService::class)->getLiquidity($market);
            
            $calc = app(OutcomeService::class)->calculateLmsrPrice($market, $outcomeId, $buyAmount);
            
            return response()->json(['liquidity' => $liquidity, 'price' => $calc, 'outcomes' => $q]);
        }

        $outcome = $market->outcomes()->with('outcomeToken.token')->find($request->outcome_id);

        if (empty($outcome)) {
            $q = $this->getOutcomeQuantities($market); 

            $liquidity = app(OutcomeService::class)->getLiquidity($market);
            
            $calc = app(OutcomeService::class)->calculateLmsrPrice($market, $outcomeId, $buyAmount);
            
            return response()->json(['liquidity' => $liquidity, 'price' => $calc, 'outcomes' => $q]);
        }

        $token = $outcome?->outcomeToken?->token;
        
        $marketId = $market->id;

        $outcomeId = $request->outcome_id;
                
        DB::transaction(function () use ($userWallet, $marketWallet, $adminWallet, $baseToken, $priceInt, $token, $buyAmount, $marketId, $outcomeId) {
                                
            Transfer::execute($userWallet, $marketWallet, $baseToken, $priceInt, 'internal', 0, 'PBUY', false);

            Transfer::execute(null, $marketWallet, $token, $buyAmount, 'internal', 0, 'PBUY', false);

            Transfer::execute($marketWallet, $userWallet, $token, $buyAmount, 'internal', 0, 'PBUY', false);
                        
            $denominator = 1000000;        
            $numerator   = (int) (($denominator * $priceInt) / $buyAmount);

            $tradeData = [
                'market_id'         => $marketId,
                'user_id'           => $userWallet->user_id,
                'outcome_id'        => $outcomeId,
                'share_amount'      => $buyAmount,
                'price_paid'        => 0,
                'price_numerator'   => $numerator,
                'price_denominator' => $denominator,
                'tx_type'           => 'BUY'
            ];

            MarketTrade::create($tradeData);
        });

        $market->subscribers()->syncWithoutDetaching([$user->id]);
        
        $q = $this->getOutcomeQuantities($market); 
                        
        $liquidity = app(OutcomeService::class)->getLiquidity($market);

        app(LimitOrderMatchingService::class)->match(marketOutcomeId: $outcomeId);

        // Return all updated data

        $calc = app(OutcomeService::class)->calculateLmsrPrice($market, $outcomeId, $buyAmount);

        $outcomes = app(OutcomeService::class)->get($market);        
            
        return response()->json(['liquidity' => $liquidity, 'price' => $calc, 'outcomes' => $outcomes]);
    }

    private function getCurrentOutcomePrice($market, $outcomeId, $buyAmount)
    {
        $calc = app(OutcomeService::class)->calculateLmsrPrice($market, $outcomeId, $buyAmount);        

        return $calc['price'];
    }

    public function price(Request $request, Market $market)
    {        
        $request->validate([
            'outcome_id' => ['required', 'integer'],
            'buy_amount' => ['required', 'integer', 'min:0'],
        ]);

        $calc = app(OutcomeService::class)->calculateLmsrPrice($market, $request->outcome_id, $request->buy_amount);

        $outcomes = app(OutcomeService::class)->get($market);
            
        return response()->json(['price' => $calc, 'outcomes' => $outcomes]);        
    }

    public function getOutcomeQuantities(Market $market): array
    {
        $outcomes = $market->outcomes()
            ->with(['outcomeToken.tokenWallet' => function ($q) use ($market) {
                $q->where('wallet_id', $market->wallet_id);
            }])
            ->get();
                
        $q = [];

        foreach ($outcomes as $outcome) {
            $tokenId = $outcome->outcomeToken->token_id;
        
            $q[$outcome->id] = OutcomeToken::query()
                ->join('token_wallet', 'outcome_tokens.token_id', '=', 'token_wallet.token_id')
                ->where('outcome_tokens.token_id', $tokenId)
                ->where('token_wallet.status', 'active')
                ->sum('token_wallet.quantity');
        }
        
        return $q;
    }

    public function prices(Request $request)
    {        
        if (empty($request->input('market_ids'))) {
            return response()->json(['markets' => []]);
        }

        $request->validate([
            'market_ids' => ['required', 'array'],
            'market_ids.*' => ['integer', 'exists:markets,id'],
        ]);

        $prices = app(OutcomeService::class)->prices($request->market_ids);
        
        return response()->json(['markets' => $prices]);
    }

    public function order(Request $request, Market $market)
    { 
        // dd($request->all());

        $validated = $request->validate([
            'outcome_id'  => ['required', 'integer', 'exists:outcomes,id'],
            'side'        => ['required', 'string', 'in:buy,sell,BUY,SELL'],
            'price'       => ['required', 'numeric', 'gt:0'],
            'amount'      => ['required', 'numeric', 'min:1'],
            'expire'      => ['required', 'string', 'in:GTC,GTD'],                        
            'expire_date' => ['nullable', 'required_if:expire,GTD', 'date_format:Y-m-d\TH:i', 'after_or_equal:now']
        ]);        

        // dd($validated);
        
        $userId = auth()->user()->id;

        $buyAmount = 1;

        $baseToken = $market->baseToken;

        if ($baseToken->fingerprint == 'asset1xhl76ah9cgj4cw8ystsuk5he3ltdr4f85vs3zu') {
            $buyAmount = 1;
            
        } else if ($baseToken->fingerprint == 'asset108xu02ckwrfc8qs9d97mgyh4kn8gdu9w8f5sxk') {
            $buyAmount = 1000;
            
        } else if ($baseToken->fingerprint == 'asset17q7r59zlc3dgw0venc80pdv566q6yguw03f0d9') {
            $buyAmount = 10000;
            
        } else {                
            $buyAmount = 1;
        } 

        if ($validated['amount'] < $buyAmount) {
            return response()->json(['error' => 'Amount is too low.'], 422);
        }
                
        $price = Intval(bcmul($validated['price'] * $buyAmount, bcpow("10", (string) $baseToken->decimals)));

        $priceProShareInt = (int) ceil($price / $buyAmount);
        
        if ($market->status !== 'OPEN') {
            return response()->json(['error' => 'Market is not open for trading.'], 403);
        }
        
        $marketId    = $market->id;        
        $baseTokenId = $baseToken->id;

        $isBuy = strtoupper($validated['side']) === 'BUY';
      
        $tokenId = $isBuy ? $baseTokenId : OutcomeToken::where('outcome_id', $validated['outcome_id'])->value('token_id');
      
        DB::transaction(function () use ($userId, $tokenId, $baseTokenId, $marketId, $validated, $buyAmount, $priceProShareInt, $isBuy) {
            
            $usrWallet = Wallet::where('user_id', $userId)->where('type', 'available')->whereNull('deleted_at')->firstOrFail();
         
            $tokenWallet = TokenWallet::where('wallet_id', $usrWallet->id)->where('token_id', $tokenId)->where('status', 'active')->first();

            if (empty($tokenWallet)) {
                throw new \Exception('No wallet with that token');
            }

            $available = max($tokenWallet->quantity - $tokenWallet->reserved_quantity, 0);

            $reserveAmount = $isBuy ? $priceProShareInt * $validated['amount'] : $validated['amount'];

            if ($available < $reserveAmount) {
                throw new \Exception('Insufficient available balance');
            }
        
            $tokenWallet->reserved_quantity += $reserveAmount;
            $tokenWallet->quantity_version  += 1;
        
            $tokenWallet->save();
                    
            $limitOrder = new MarketLimitOrder();

            $limitOrder->user_id       = $userId;
            $limitOrder->market_id     = $marketId;
            $limitOrder->outcome_id    = $validated['outcome_id'];
            $limitOrder->base_token_id = $baseTokenId;
            $limitOrder->type          = strtoupper($validated['side']);
            $limitOrder->limit_price   = $priceProShareInt ? $priceProShareInt : $validated['price'];
            $limitOrder->share_amount  = $validated['amount'];            
            $limitOrder->status        = 'OPEN';
            $limitOrder->valid_until   = $validated['expire_date'];
                                    
            $limitOrder->save();
        });

        // Limit order match runs after created, nach commit, siehe Model boot->created

        return response()->json(['success' => 'Limit order created.']);
    }
    
    public function fullData(Request $request, Market $market)
    {
        $startDate = $request->query('start_date', null);
        $range     = $request->query('range', '1H'); 

        $inputAmounts = $request->query('inputAmounts', []);
        
        $trades = app(MarketTradesService::class)->get($market, $startDate, $range);

        $orderBook = app(LimitOrderService::class)->get($market);

        $outcomeSums = app(OutcomeService::class)->get($market, $inputAmounts);

        return response()->json([            
            'trades' => $trades,            
            'orderTable' => $orderBook['orderTable'],
            'outcomes' => $outcomeSums,
        ]);
    }

    public function orders(Request $request, int $marketId)
    {                
        $data = $request->validate([            
            'allow_limit_orders' => 'required|boolean',
            'max_trade_amount' => 'required|int|min:1|max:1000',
        ]);
        
        $market = Market::findOrFail($marketId);
        
        if ($this->authorize('update', $market)) {

            Market::where('id', $market->id)->update([
                'allow_limit_orders' => $data['allow_limit_orders'], 
                'max_trade_amount'   => $data['max_trade_amount']
            ]);
        }

        return redirect()->back();
    }

    public function qrcode(Request $request, $id)
    {
        $market = Market::findOrFail($id);
            
        $foregroundColor = new Rgb(148, 164, 163);

        $backgroundColor = new Rgb(255, 255, 255);

        $fill = Fill::uniformColor($foregroundColor, $backgroundColor);

        $marketData = [];

        $marketData['market']    = $market->id;
        $marketData['latitude']  = $market->latitude;
        $marketData['longitude'] = $market->longitude;
        
        $data = json_encode($marketData);
                            
        $renderer = new ImageRenderer(new RendererStyle(400, 1, null, null, $fill), new ImagickImageBackEnd());
        
        $qrcode = 'data:image/png;base64,'.base64_encode((new Writer($renderer))->writeString($data));
                                    
        return response()->json($qrcode, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);        
    }
    
}
