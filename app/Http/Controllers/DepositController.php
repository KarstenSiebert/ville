<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use Carbon\Carbon;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Token;
use App\Models\Market;
use App\Models\Wallet;
use App\Models\Deposit;
use App\Models\Transfer;
use App\Models\ChimeraLog;
use App\Models\TokenWallet;
use Illuminate\Http\Request;
use App\Models\OutcomeToken;
use App\Helpers\ImageStorage;
use Illuminate\Support\Facades\Log;
use App\Helpers\CardanoFingerprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class DepositController extends Controller
{
    use AuthorizesRequests;
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, User $publisher = null)
    {        
        $user = auth()->user();

        if ($publisher) {
            $user = $publisher;
        }

        $search = $request->input('search', null);
        
        $payment_token = $request->input('payment_token', null);
    
        $page = (int) $request->input('page', 1);
    
        $perPage = 10;

        if ($user !== null) {    
            
            if ($usrWallet = Wallet::where('user_id', $user->id)->where('type', 'available')->first()) {
                                      
                // How many tokens are available ?                
                $tokens = TokenWallet::with(['token.outcomes.market'])->forWallet($usrWallet->id)
                    ->withQuantity()
                    ->withActiveToken()
                    ->loadTokenData()
                    ->limit(50)                 
                    ->get()
                    ->map(function ($tw) use ($user) {

                        if (!str_starts_with($tw->token->logo_url, 'https') && !str_starts_with($tw->token->logo_url, '/storage')) {                            
                            $tw->token->logo_url = ImageStorage::saveBase64Image($tw->token->logo_url, trim($tw->token->name));
                        }

                        $isUserToken = false;

                        if ($tw->token->user_id == auth()->id()) {
                            $isUserToken = true;
                        }

                        $market = '';
                        
                        if ($tw->token->token_type == 'SHARE') {
                            foreach ($tw->token->outcomes as $outcome) {

                                if ($outcome->market) {
                                    $market = $outcome->market->title;
                                }
                            }
                        }
                        
                        return [
                            'asset_name'           => $tw->token->name,
                            'asset_hex'            => $tw->token->name_hex,
                            'policy_id'            => $tw->token->policy_id,
                            'quantity'             => $tw->quantity,
                            'reserved_quantity'    => $tw->reserved_quantity,
                            'token_number'         => $tw->quantity,
                            'decimals'             => $tw->token->decimals,
                            'fingerprint'          => $tw->token->fingerprint,                            
                            'logo_url'             => $tw->token->logo_url,
                            'user_name'            => $user->name,
                            'user_email'           => $user->email,
                            'is_user_token'        => $isUserToken,                            
                            'token_type'           => $tw->token->token_type,
                            'token_id'             => $tw->token_id,
                            'market'               => $market
                        ];                        
                    });
                    
                $searchTerms = preg_split('/\+/', strtolower(trim($search)));
                    
                $assetList = $tokens->filter(function($asset) use ($searchTerms) {
                        
                    if (!$searchTerms) return true;
                        
                    $assetName = 'ADA';

                    if ($asset['asset_name'] !== 'ADA') {

                        if (str_contains($asset['asset_hex'], '0014df10') || str_contains($asset['asset_hex'], '000643b0')) {
                            $assetName = hex2bin(substr($asset['asset_hex'], 8));

                        } else {
                            $assetName = hex2bin($asset['asset_hex']);
                        }
                    }

                    $assetName   = strtolower($assetName);
                    $fingerprint = strtolower($asset['fingerprint'] ?? '');
    
                    return collect($searchTerms)->contains(fn($term) =>
                        str_contains($assetName, $term) || str_contains($fingerprint, $term)
                    );
                });
                                
                // How many tokens are available ?

                $tokenTotals = DB::table('tokens as t')
                    ->join('token_wallet as tw', 'tw.token_id', '=', 't.id')
                    ->join('wallets as w', 'w.id', '=', 'tw.wallet_id')
                    ->where('w.user_id', $user->id)
                    ->where(function ($q) {
                        $q->where('w.type', 'available')->orWhere('w.type', 'reserved');
                    })                    
                    ->select('t.fingerprint', DB::raw('SUM(tw.quantity) as total_quantity'))
                    ->groupBy('t.fingerprint')
                    ->havingRaw('SUM(tw.quantity) > 0')
                    ->pluck('total_quantity', 'fingerprint');           

                // How many tokens are reserved ?
                
                $tokenReserved = DB::table('tokens as t')
                    ->join('token_wallet as tw', 'tw.token_id', '=', 't.id')
                    ->join('wallets as w', 'w.id', '=', 'tw.wallet_id')
                    ->where('w.type', 'reserved')
                    ->where('w.user_id', $user->id)
                    ->select('t.fingerprint', DB::raw('SUM(tw.quantity) as total_quantity'))
                    ->groupBy('t.fingerprint')
                    ->havingRaw('SUM(tw.quantity) > 0')
                    ->pluck('total_quantity', 'fingerprint');
                
                $assetList = collect($assetList)->map(function ($asset) use ($tokenTotals, $tokenReserved) {
                    $fingerprint = $asset['fingerprint'];
                    $assetQty    = (int) $asset['quantity'];
                    $dbQty       = (int) ($tokenTotals[$fingerprint] ?? 0);
                    $dbRes       = (int) ($tokenReserved[$fingerprint] ?? 0);
                    
                    $asset['status'] = $assetQty === ($dbQty - $dbRes) ? '✅' : '⚠️';
                
                    return $asset;
                });
                
                $paginated = new LengthAwarePaginator(
                    $assetList->forPage($page, $perPage)->values(),
                    $assetList->count(),
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'query' => $request->query()]
                );                    
            }            
        }

        // dd($paginated->items());
                
        if (empty($paginated)) {
            $paginated = new LengthAwarePaginator([], 0, $perPage, $page);
        }        
                
        return Inertia::render('deposits/Deposits', [
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
                'user_context' => [
                    'type' => $publisher ? 'operator' : 'self',
                    'name' => $user->name,
                    'id'   => $publisher ? $publisher->id : null
                ],
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request, $publisherId = null)
    {
        // dd($publisherId);

        $user = auth()->user();

        $publisher = User::where('id', $publisherId)->first();
        
        if ($publisher) {            
            $user = $publisher;
        }

        // dd($publisher);
        
        $validated = $request->validate([
            'selected_assets' => 'required|array|min:1',
            'selected_assets.*.policy_id' => 'nullable|string',
            'selected_assets.*.asset_name' => 'required|string',
            'selected_assets.*.address' => 'nullable|string',
            'selected_assets.*.asset_hex' => 'nullable|string',
            'selected_assets.*.fingerprint' => 'nullable|string',
            'selected_assets.*.quantity' => 'required|numeric|min:1',
            'selected_assets.*.reserved_quantity' => 'nullable|numeric',
            'selected_assets.*.decimals' => 'required|integer|min:0',
            'selected_assets.*.logo_url' => 'nullable|string',
            'selected_assets.*.token_type' => 'nullable|string|in:BASE,SHARE',
            'selected_assets.*.token_id' => 'required|integer|exists:tokens,id'
        ]);

        $assets = $validated['selected_assets'];
        
        $preparedAssets = [];

        foreach($assets as $asset) {
            $token = 0;
         
            if (!empty($asset['address'])) {
                $toknm = bcdiv(max($asset['quantity'] - $asset['reserved_quantity'], 0), bcpow("10", (string) $asset['decimals']), $asset['decimals']);
            
                $token = $toknm ? $toknm : 0;
            
            } else {

                if (max($asset['quantity'] - $asset['reserved_quantity'], 0) == 1) {
                    $token = 0;
                }
            }            
           
            if ($asset['quantity'] > 0) {    

                $preparedAssets[] = ['asset_name'        => $asset['asset_name'],
                                     'asset_hex'         => $asset['asset_hex'],
                                     'policy_id'         => $asset['policy_id'],
                                     'fingerprint'       => $asset['fingerprint'],
                                     'quantity'          => $asset['quantity'],
                                     'reserved_quantity' => $asset['reserved_quantity'],
                                     'token_number'      => $token,
                                     'decimals'          => $asset['decimals'],
                                     'logo_url'          => $asset['logo_url'],
                                     'token_type'        => $asset['token_type'],
                                     'token_id'          => $asset['token_id']                                     
                ];
            }
        };

        return Inertia::render('deposits/Create', [
            'assets' => $preparedAssets,
            'payout' => $user->payout,
            'user_context' => [
                'type' => $publisher ? 'operator' : 'self',
                'name' => $user->name,
                'id'   => $publisher ? $user->id : null
            ],             
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $publisherId = null)
    {
        // dd($request->all());

        $err['response'] = 'error';
        $err['message']  = __('transfer_not_completed');

        $local = auth()->user();

        $publisher = User::where('id', $publisherId)->first();
        
        $data = $request->input('selected_assets');
                 
        if ($this->validateTokenTotals($data) == false) {
            $err['message'] = __('amount_exceeds_available');
                        
            return redirect('deposits')->with($err['response'], $err['message']);
        }

        if ($publisher) {
            $local = $publisher;
        }

        // dd($publisher);

        $ada = array_filter($data, function ($item) {
            return $item['asset_name'] === 'ADA' || is_null($item['asset_name']);
        });

        $ada = array_values($ada);

        $paymentDone = false;

        $count = 0;

        $unique = [];
     
        foreach($data as $d) {
                           
            if (($d['destination'] !== null && str_starts_with($d['destination'], 'addr1')) || ($d['destination'] !== null && ($d['destination'] == $local->email))) {
                $unique[$d['destination']] = true;
            }    
        }

        $count += count($unique);
        
        foreach($data as $d) {

            $receiver_id = (array_key_exists('receiver_id', $d) && !empty($d['receiver_id'])) ? $d['receiver_id'] : $local->id;
        
            $isSender    =  $receiver_id === $local->id;

            $isPublisher = $local->type === 'OPERATOR';

            $isAdminUser = $d['destination'] === config('chimera.admin_user');
            
            if ($isAdminUser) {
                $receiver_id = User::where('email', config('chimera.admin_user'))->value('id');
            }
            
            $hasPayoutTransaction = $isSender && ! $isAdminUser && ! $isPublisher;
            
            if ($hasPayoutTransaction == true) {

                if ($fromWallet = Wallet::where('type', 'available')->where('user_id', $local->id)->first()) {

                    // Do not forget to set the Admin wallet address in config.chimera

                    $avaAdminAddress = 'ava'.trim(config('chimera.admin_wallet'));

                    if ($toWallet = Wallet::where('address', $avaAdminAddress)->where('type', 'available')->first()) {

                        if (($paymentDone == false) && ($token = Token::where('name', 'ADA')->where('fingerprint', "")->first()))  {

                            // Fee 2.000000 ADA per remote address, might change in the future, admin always has to stay in profit

                            $cost = ($count > 0) ? $count * intval(config('chimera.head_fee')) : intval(config('chimera.head_fee'));
                            
                            $fromPivot = $fromWallet->tokens()->where('token_id', $token->id)->first();
                                    
                            $fromBalance = $fromPivot ? max($fromPivot->pivot->quantity - $fromPivot->pivot->reserved_quantity, 0) : 0;

                            $adaVal = 0;

                            /**
                             * If the transfer includes ADA, we have to check, if the User
                             * account contains ADA (for the transfer) AND ADA for the fee
                             */

                            if (!empty($ada)) {
                                $adaVal = $ada[0]['token_number'];
                            }
                            
                            if ($fromBalance < $adaVal + $cost) {
                                $err['response'] = 'error';
                                $err['message']  = __('not_enough_ada_for_outgoing_transaction');
                                
                                return redirect('deposits')->with($err['response'], $err['message']);
                            }

                            // We charge the USER account and the cost (fee) will be sent to the Admin account

                            // $cost = 0;

                            if ($cost > 0) {
                            
                                if (Transfer::execute($fromWallet, $toWallet, $token, $cost, 'internal', 0, 'FFBT')) {
                                    $paymentDone = true;
                                    
                                    $err['response'] = 'success';
                                    $err['message']  = __('transfer_fee_paid');
                                }
                            }
                        }
                    }
                }
            }
        }
         
        // dd($data);

        foreach($data as $d) {
                        
            $receiver_id = (array_key_exists('receiver_id', $d) && !empty($d['receiver_id'])) ? $d['receiver_id'] : $local->id;

            $isAdminUser = trim($d['destination']) === config('chimera.admin_user');

            if ($isAdminUser) {
                $receiver_id = User::where('email', config('chimera.admin_user'))->value('id');
            }
     
            $senderId = $local->id;

            // dd($senderId, $receiver_id);

            if ($receiver_id && $senderId) {

                $d['fingerprint'] = !empty($d['fingerprint']) ? $d['fingerprint'] : "";

                if ($token = Token::where('name', $d['asset_name'])->where('fingerprint', $d['fingerprint'])->first()) {
            
                    if ($fromWallet = Wallet::where('type', 'available')->where('user_id', $local->id)->first()) {

                        // Only BASE tokens can be sent accross to the blockchain, SHARE tokens exist only locally
                    
                        // Here, we check the destination address and user type, no MARKET or PUBLISHER allowed to send
                                                
                        if (($token->token_type == 'BASE') && ($receiver_id == $senderId) && ($local->type == 'USER')) {
                                                   
                            // Payments will leave the available account to the reserved account, if those are oputgoing (towards the blockchain) payments

                            if ($toWallet = Wallet::where('type', 'reserved')->where('user_id', $receiver_id)->first()) {
                                
                                // Hardened policy to check, only adults can send on chain
                                
                                if ($local->can('credit', $toWallet) && $local->can('debit', $fromWallet) ) {

                                    $receiver_address = str_starts_with($d['destination'], 'addr1') ? $d['destination'] : null;
                                    
                                    if (Transfer::execute($fromWallet, $toWallet, $token, $d['token_number'], 'internal', 0, 'PFBT', false, $receiver_address)) {
                                        $err['response'] = 'success';
                                        $err['message']  = __('transfer_initiated');                                        
                                        
                                        Transfer::execute($toWallet, null, $token, $d['token_number'], 'onchain_out', 0, 'S2BT', true, $receiver_address);
                                    }                              
                                }
                            }

                        } else {

                            // Internal payments are sent to the recipients account directly
                            
                            if ($toWallet = Wallet::where('type', 'available')->where('user_id', $receiver_id)->first()) {

                                if ($fromWallet->id !== $toWallet->id) {                                    

                                    if ($local->can('credit', $toWallet) && $local->can('debit', $fromWallet) ) {

                                       if (Transfer::execute($fromWallet, $toWallet, $token, $d['token_number'], 'internal', 0, 'W2WT', false)) {
                                            $err['response'] = 'success';
                                            $err['message']  = __('transfer_completed');
                                        }
                                    
                                    } else {
                                        $err['message']  = __('no_permission');
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if ($publisher) {
            return redirect('deposits/publisher/'.$publisher->id)->with($err['response'], $err['message']);
        }                    

        return redirect('deposits')->with($err['response'], $err['message']);
    }

    private function validateTokenTotals(array $data): bool
    {
        $totals = [];

        foreach ($data as $item) {
            $name = $item['asset_name'];
        
            if (!isset($totals[$name])) {
                $totals[$name] = [
                    'sum' => 0,
                    'quantity' => $item['quantity'],
                    'reserved_quantity' => $item['reserved_quantity']
                ];
            }
        
            $totals[$name]['sum'] += $item['token_number'];
        }

        foreach ($totals as $name => $info) {

            $available_quantity = max($info['quantity'] - $info['reserved_quantity'], 0);

            if ($info['sum'] > $available_quantity) {
                return false;
            }
        }

        return true;
    }

}

