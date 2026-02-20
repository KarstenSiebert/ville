<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use Inertia\Inertia;
use App\Models\Token;
use App\Models\Wallet;
use App\Models\OnChain;
use App\Models\Transaction;
use App\Models\InputOutput;
use Illuminate\Http\Request;
use App\Helpers\ImageStorage;
use App\Helpers\CardanoCliWrapper;
use App\Helpers\CreateNftMetadata;
use App\Helpers\CardanoFingerprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;

class OnChainController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin') && !$user->hasRole('superadmin')) {
            return redirect('deposits');
        }
        
        $search = $request->input('search', null);

        $payment_token = $request->input('payment_token', null);
    
        $page = (int) $request->input('page', 1);
    
        $perPage = 10;

        $address = Wallet::where('user_id', Auth::user()->id)->where('type', 'deposit')->value('address');
                
        if ($address !== null) {                           
            $allAssets = collect(CardanoCliWrapper::getAdminAssets($address));

            $searchTerms = preg_split('/\+/', strtolower(trim($search)));

            $filteredAssets = $allAssets->filter(function($asset) use ($searchTerms) {

                if (!$searchTerms) return true;
    
                $assetName = 'ADA';

                if ($asset->asset_name !== 'ADA') {
                    if (str_contains($asset->asset_name, '\x0014df10') || str_contains($asset->asset_name, '\x000643b0')) {
                        $assetName = hex2bin(substr($asset->asset_name, 10));
        
                    } else {
                        $assetName = hex2bin(substr($asset->asset_name, 2));
                    }
                }

                $assetName   = strtolower($assetName);
                $fingerprint = strtolower($asset->fingerprint ?? '');
    
                return collect($searchTerms)->contains(fn($term) =>
                    str_contains($assetName, $term) || str_contains($fingerprint, $term)
                );
            });

            $assetList = $filteredAssets->map(function($asset) use ($payment_token) {

                if (!$asset->quantity) return null;

                $img = null;

                $asset->decimals = 0;
                    
                if ((strtoupper($asset->asset_name) !== 'ADA') && !empty($asset->policy_id) && !empty($asset->asset_hex)) {
                    $is_CIP68 = false;
                    $is_NFTTK = false;
                    
                    if (str_starts_with($asset->asset_hex, '\x0014df10')) {
                        $asset->asset_name = hex2bin(substr($asset->asset_hex, 10));
                        $is_CIP68 = true;  

                    } else if (str_starts_with($asset->asset_hex, '\x000643b0')) {
                        $asset->asset_name = hex2bin(substr($asset->asset_hex, 10));
                        $is_CIP68 = true;
                        $is_NFTTK = true;
                        
                    } else {
                        $asset->asset_name = hex2bin(substr($asset->asset_hex, 2));
                    }
                        
                    $asset->policy_id = substr($asset->policy_id, 2);
                    $asset->asset_hex = substr($asset->asset_hex, 2);

                    $asset->fingerprint = CardanoFingerprint::fromPolicyAndName($asset->policy_id, $asset->asset_name, $is_CIP68, $is_NFTTK);
                                    
                    $predefined = [
                        'USDM'          => ['policy' => 'c48cbb3d5e57ed56e276bc45f99ab39abe94e6cd7ac39fb402da47ad', 'logo' => '/storage/logos/usdm-coin.png', 'decimals' => 6],
                        'USDA'          => ['policy' => 'fe7c786ab321f41c654ef6c1af7b3250a613c24e4213e0425a7ae456', 'logo' => '/storage/logos/usda-coin.png', 'decimals' => 6],
                        'USDCx'         => ['policy' => '1f3aec8bfe7ea4fe14c5f121e2a92e301afe414147860d557cac7e34', 'logo' => '/storage/logos/usdcx-coin.png', 'decimals' => 6],
                        'HOSKY'         => ['policy' => 'a0028f350aaabe0545fdcb56b039bfb08e4bb4d8c4d7c3c7d481c235', 'logo' => '/storage/logos/hosky-coin.png', 'decimals' => 0],
                        'NIGHT'         => ['policy' => '0691b2fecca1ac4f53cb6dfb00b7013e561d1f34403b957cbb5af1fa', 'logo' => '/storage/logos/night-coin.png', 'decimals' => 6],
                        'SNEK'          => ['policy' => '279c909f348e533da5808898f87f9a14bb2c3dfbbacccd631d927a3f', 'logo' => '/storage/logos/snek-coin.png', 'decimals' => 0],
                        'DjedMicroUSD'  => ['policy' => '8db269c3ec630e06ae29f74bc39edd1f87c819f1056206e879a1cd61', 'logo' => '/storage/logos/djed-coin.png', 'decimals' => 6],
                        'Wechselstuben' => ['policy' => '8dfd68762a95e06f3d66c04f2a688241767e8ea934a4144b4915a681', 'logo' => '/storage/logos/wechselstuben-logo.png', 'decimals' => str_starts_with($asset->asset_hex, '0014df10') ? 6 : 0]
                    ];

                    if (isset($predefined[$asset->asset_name]) && $predefined[$asset->asset_name]['policy'] === $asset->policy_id) {
                        $img = $predefined[$asset->asset_name]['logo'];
                        $asset->decimals = $predefined[$asset->asset_name]['decimals'];

                    } else {                    
                        $assetHex = $asset->asset_hex;
                        $policyId = $asset->policy_id;

                        $cacheKey = 'jsonMeta_' . $assetHex.$policyId;
                                                                       
                        $jsonMeta = Cache::remember($cacheKey, 3600, function () use ($assetHex, $policyId) {
                            return CardanoFingerprint::getTokenJson($assetHex, $policyId);
                        });
                                                
                        if (!empty($jsonMeta)) {
                            $asset->ticker = !empty($jsonMeta['ticker']) ? $jsonMeta['ticker'] : '';

                            $asset->decimals = !empty($jsonMeta['decimals']) ? $jsonMeta['decimals'] : 0;

                            $asset->description = !empty($jsonMeta['description']) ? $jsonMeta['description'] : '';

                            $asset->logo_url = !empty($jsonMeta['image']) ? $jsonMeta['image'] : '';

                            if (!empty($asset->logo_url)) {
                                $img = $asset->logo_url;
                            }
                        }                            
                    }
            
                    if (empty($img)) {
                        $img = '/storage/logos/wechselstuben-logo.png';
                    }

                } else {
                    $img = '/storage/logos/cardano-ada-logo.png';

                    $asset->fingerprint = null;
                    $asset->decimals    = 6;
                }

                $asset->logo_url = $img;

                if (!str_starts_with($img, 'https') && !str_starts_with($img, '/storage')) {
                    $asset->logo_url = ImageStorage::saveBase64Image($img, trim($asset->asset_name));
                }
                
                if ($payment_token && $payment_token !== $asset->asset_name) return null;
                            
                return $asset;

            })->filter()->values();
            
            $tokenTotals = DB::table('tokens as t')
                ->join('token_wallet as tw', 'tw.token_id', '=', 't.id')
                ->join('wallets as w', 'w.id', '=', 'tw.wallet_id')
                ->where(function ($q) {
                    $q->where('w.type', 'available')->orWhere('w.type', 'reserved');
                })  
                ->select('t.fingerprint', DB::raw('SUM(tw.quantity) as total_quantity'))
                ->groupBy('t.fingerprint')
                ->havingRaw('SUM(tw.quantity) > 0')
                ->pluck('total_quantity', 'fingerprint');

            $differences = [];

            foreach ($assetList as $asset) {
                $fingerprint = $asset->fingerprint;
                $assetQty = (int) $asset->quantity;
                $dbQty = (int) ($tokenTotals[$fingerprint] ?? 0);

                $asset->status = $assetQty === $dbQty ? '✅' : '⚠️';
            }
            
            $paginated = new LengthAwarePaginator(
                $assetList->forPage($page, $perPage)->values(),
                $assetList->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => $request->query()]
            );

        } else {
            $paginated = new LengthAwarePaginator([], 0, $perPage, $page);
        }        

        return Inertia::render('onchain/Onchain', [
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
                ]
            ]
        ]);    
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {       
        $validated = $request->validate([
            'selected_assets' => 'required|array|min:1',
            'selected_assets.*.policy_id' => 'nullable|string',
            'selected_assets.*.asset_name' => 'required|string',
            'selected_assets.*.address' => 'nullable|string',
            'selected_assets.*.asset_hex' => 'nullable|string',
            'selected_assets.*.fingerprint' => 'nullable|string',
            'selected_assets.*.quantity' => 'required|numeric|min:1',
            'selected_assets.*.decimals' => 'required|integer|min:0',
            'selected_assets.*.logo_url' => 'nullable|string',
        ]);

        $assets = $validated['selected_assets'];

        $preparedAssets = [];
        
        foreach($assets as $asset) {
            $token = 0;
         
            if (!empty($asset['address'])) {
                $toknm = bcdiv($asset['quantity'], bcpow("10", (string) $asset['decimals']), $asset['decimals']);
            
                $token = $toknm ? $toknm : 0;
            
            } else {
                if ($asset['quantity'] == 1) {
                    $token = 1;
                }
            }

            $preparedAssets[] = ['asset_name'   => $asset['asset_name'],                                  
                                 'asset_hex'    => $asset['asset_hex'],
                                 'policy_id'    => $asset['policy_id'],
                                 'fingerprint'  => $asset['fingerprint'],
                                 'quantity'     => $asset['quantity'],
                                 'decimals'     => $asset['decimals'],                                 
                                 'token_number' => $token,
                                 'logo_url'     => $asset['logo_url']
            ];
        };

        return Inertia::render('onchain/Create', [
            'assets' => $preparedAssets,
            'payout' => Auth::user()->payout
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->input('selected_assets');
        
        $data = array_map(function ($item) {
            if ($item['token_number'] > $item['quantity']) {
                $item['token_number'] = $item['quantity'];
            }
            return $item;
            
        }, $data);

        $metaData = new CreateNftMetadata();
        
        $additional_info = !empty($request->input('additional_info')) ? $request->input('additional_info') : '';
        
        $nftMetaData = $metaData->generateTransactionMetadata($additional_info);
        
        $address = Wallet::where('user_id', Auth::user()->id)->where('type', 'deposit')->value('address');

        $txPrefix = Storage::path('transactions').'/'.bin2hex(openssl_random_pseudo_bytes(4)).'/';
              
        $asset = [];

        $asset['asset_name'] = 'ADA';
        $asset['rate']       = null;

        $asset_name = $asset['asset_name'];

        $err = [];
        
        $err['response'] = 'error';
        $err['message'] = '';
        
        $spentFee = 0;
        $netwkFee = 0;

        $txoutCopy = [];

        // dd($data);
        
        $service = config('chimera.admin_wallet');

        if (CardanoCliWrapper::make_dir($txPrefix)) {
            $err = app(BabelTransactionController::class)->generate_transaction($data, $address, $service, $txPrefix, $nftMetaData, $spentFee, $netwkFee, $txoutCopy);
        }
        
        if (empty($err['response'])) {
            $err['response'] = 'error';
        }
        
        if (!empty($err) && !empty($err['response'])) {
            
            if (!empty($err['message'])) {
                $err['message'] = ($err['response'] == 'error') ? $err['message'] : 'Transaction successfully assembled.';

            } else {
                $err['message'] = ($err['response'] == 'error') ? 'Error assembling transaction.' : 'Transaction successfully assembled.';
            }
        }
                
        // dd($err, $spentFee, $txPrefix);

        if (!empty($err['response']) && ($err['response'] == 'success')) {
            $transaction = [];
            $parsedvalue = [];

            $transaction['tx_fee']    = ($spentFee > 0) ? $spentFee : '0';
            $transaction['tx_net']    = ($netwkFee > 0) ? $netwkFee : '0';
            $transaction['tx_prefix'] = $txPrefix;
            
            $transaction['tx_rate']   = $asset['rate'] ? $asset['rate'] : null;

            // dd($transaction);

            if ($inputOutputs = InputOutput::where('user_id', Auth::user()->id)->where('change', $service)->orderby('created_at', 'desc')->first()) {
                $unparsedvalue = explode('--tx-out ', $inputOutputs->outputs);
                
                foreach($unparsedvalue as $val) {
                    if (!empty($val)) {
                        $parsedvalue[] = $val;
                    }
                }

                $parsed = [];

                foreach ($parsedvalue as $entry) {       
                    /*                           
                    [$address, $rest] = explode('+', $entry, 2);

                    preg_match('/^(\d+)/', $rest, $matches);
                    $lovelace = $matches[1] ?? 0;                    
                    */
                    
                    $parts = explode('+', $entry, 2);
                    $address = $parts[0];
                    $rest = $parts[1] ?? '';
    
                    preg_match('/^(\d+)/', $rest, $matches);
                    $lovelace = isset($matches[1]) ? (int)$matches[1] : 0;

                    preg_match_all('/"([^"]+)"/', $rest, $matches);
                    $tokens = [];
        
                    foreach ($matches[1] as $tokenStr) {
                        if (preg_match('/^(\d+)\s+([^.]+)\.(.+)$/', $tokenStr, $parts)) {
                            $qty = (int)$parts[1];
                            $policy = $parts[2];
                            $assetHex = $parts[3];
                            
                            if (str_starts_with($assetHex, '0014df10')) {
                                $assetName = hex2bin(substr($assetHex, 8));
                            } else {
                                $assetName = hex2bin($assetHex);
                            }
                
                            $tokens[] = [
                                'quantity'   => $qty,
                                'policy_id'  => $policy,
                                'asset_hex'  => $assetHex,
                                'asset_name' => $assetName,
                            ];
                        }
                    }

                    if ($address === Auth::user()->wallets[0]->address) {
                        // $address = 'self';
                        $address = $address.' (self)';
                    }

                    $parsed[] = [
                        'address'  => $address,
                        'ada'      => $lovelace / 1_000_000,
                        'tokens'   => $tokens,                        ];                    
                }                
            }
            
            return Inertia::render('onchain/Confirm', [
                'transaction' => $transaction,                
                'utxos'       => $parsed
            ]);
        }
        
        return redirect('onchain')->with($err['response'], $err['message']);
    }

    public function confirm(Request $request)
    {
        // dd($request->all());

        $err = 'error';
   
        $transaction = $request->input('transaction');

        $txPrefix = $transaction['tx_prefix'];
        $txFee    = $transaction['tx_fee'];        

        if (!empty($txPrefix)) {
            $this->cli = new CardanoCliWrapper($txPrefix, '/usr/local/bin/cardano-cli');
      
            if (file_exists($this->cli->txPrefix.'matx.signed')) {
                                
                $retVal = $this->cli->submitTransaction();

                // $retVal = 0;

                if (strlen($retVal) == 64) {
                                      
                    Transaction::Create([
                        'user_id' => Auth::id(),
                        'transaction_id' => $retVal,
                        'transaction_fee' => floatval($txFee)
                    ]);
                            
                    $err = 'success';
                                        
                    Cache::tags(['user:' . Auth::id()])->flush();                    
                }
            }

            CardanoCliWrapper::remove_dir($txPrefix);
        }

        $message = ($err == 'error') ? __('transaction_not_submitted') : __('transaction_successfully_submitted');
        
        return redirect('onchain')->with($err, $message);
    }
        
}

