<?php

namespace App\Http\Controllers;

use DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Token;
use App\Models\Wallet;
use App\Models\Outbound;
use App\Models\Transfer;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Helpers\ImageStorage;
use App\Helpers\ContainerMetadata;
use App\Helpers\CardanoCliWrapper;
use App\Helpers\CardanoFingerprint;
use Illuminate\Support\Facades\Log;
use App\Models\WalletReconciliation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class TransferController extends Controller
{
    /**
     *  Periodically issued via cron to read all incoming tokens and ADA of user wallets
     */
    public function readDepositWallets(): void
    {
        /**
         * Admin wallet currently hard-coded, excluded from transfers into
         * the system. deposit addresses are real Cardano wallet addresses
         * Assets to admin wallet are directly added to the token quantity
         */
        
        $service = trim(config('chimera.admin_wallet'));

        $wallets = Wallet::with('user')
                        ->where('type', 'deposit')                        
                        ->where(function ($q) {
                            $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subMinutes(2));
                        })
                        ->limit(10)
                        ->get();

        foreach($wallets as $wallet) {
            $address = trim($wallet->address);

            Log::debug('readdepositwallets processing: '.$address);
                        
            // API call to external resource
            $assets = CardanoCliWrapper::getAdminAssets($address);
            
            $netFee = 0;

            $spentFee = 0;

            $txoutCopy = [];

            $assets = array_filter($assets, function ($item) {
                return !($item->asset_name === null && $item->asset_hex === null && $item->quantity === null);
            });
                        
            // Values from deposit are moved to available, if the blockchain wallet is empty.

            if (empty($assets) && ($address !== $service)) {
                
                if ($this->transferAllDepositToAvailable($address)) {
                    Log::debug('readdepositwallets empty transferAllDeposit: '.$address);
                }
            }

            /** 
             * The real blockchain transfer activity for incomings from the Users' deposit addresses to the Admin
             * address. Sending to the same address is forbidden, this would change the overall accounting system.
             */

            if (!empty($assets) && ($address !== $service)) {
                $netFee = $this->transferAllAssets($assets, $address, $service, 'inbound', $spentFee, $txoutCopy);
            }    
            
            // If the transfer was successful (netFee > 0), the virtual account of a user is filled

            if ((!empty($assets) && ($netFee > 0)) || (!empty($assets) && ($address == $service))) {
            
                foreach($assets as $asset) {
                    $img = null;

                    $asset->decimals = 0;

                    $asset->description = '';

                    if ($asset->quantity) {

                        if ((strtoupper($asset->asset_name) !== 'ADA') && !empty($asset->policy_id) && !empty($asset->asset_hex)) {
                            $is_CIP68 = false;
                            $is_NFTTK = false;

                            if (str_starts_with($asset->asset_hex, '\x')) {
                                $asset->asset_hex = substr($asset->asset_hex, 2);
                            }

                            if (str_starts_with($asset->policy_id, '\x')) {
                                $asset->policy_id = substr($asset->policy_id, 2);
                            }
                                                    
                            if (str_starts_with($asset->asset_hex, '0014df10')) {
                                $asset->asset_name = hex2bin(substr($asset->asset_hex, 8));
                                $is_CIP68 = true;

                            } else if (str_starts_with($asset->asset_hex, '000643b0')) {                                    
                                $asset->asset_name = hex2bin(substr($asset->asset_hex, 8));                        
                                $is_CIP68 = true;
                                $is_NFTTK = true;

                            } else {                                    
                                $asset->asset_name = hex2bin($asset->asset_hex);
                            }
                                                            
                            if ((strlen($asset->policy_id) == 56) && !empty($asset->asset_name)) {                    
                                $asset->fingerprint = CardanoFingerprint::fromPolicyAndName($asset->policy_id, $asset->asset_name, $is_CIP68, $is_NFTTK);
                                
                            } else {
                                $asset->fingerprint = null;
                            }

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
                        
                        if (!str_starts_with($asset->logo_url, 'https') && !str_starts_with($asset->logo_url, '/storage')) {
                            $asset->logo_url = ImageStorage::saveBase64Image($asset->logo_url, trim($asset->asset_name));
                        }                        

                        $fingerprint = $asset->fingerprint ? $asset->fingerprint : "";
                        
                        $step_size = 1000;

                        if (($fingerprint == '') && ($asset->decimals == 6)) {
                            $step_size = 10000; // ADA
                        
                        } else if ($fingerprint == 'asset1exr3kn78n2j9qnw4g3s5l7fhnf3vnpsxq28d6d') {
                            $step_size = 10000; // USDX
                                                 
                        } else if ($fingerprint == 'asset1e7eewpjw8ua3f2gpfx7y34ww9vjl63hayn80kl') {
                            $step_size = 10000; // USDCx                        
                        }

                        else if ($fingerprint == 'asset12ffdj8kk2w485sr7a5ekmjjdyecz8ps2cm5zed') {
                            $step_size = 10000; // USDM
                        
                        } else if ($fingerprint == 'asset16fq594uun90f2jajmecjcdt4jnsnq7r3jdqsw5') {
                            $step_size = 10000;  // USDA
                        
                        } else if ($fingerprint == 'asset17q7r59zlc3dgw0venc80pdv566q6yguw03f0d9') {
                            $step_size = 10000;  // HOSKY
                        
                        } else if ($fingerprint == 'asset1wd3llgkhsw6etxf2yca6cgk9ssrpva3wf0pq9a') {
                            $step_size = 10000;  // NIGHT

                        } else if ($fingerprint == 'asset108xu02ckwrfc8qs9d97mgyh4kn8gdu9w8f5sxk') {
                            $step_size = 1000;   // SNEK
                        
                        } else if ($fingerprint == 'asset1945pt2n8zutnygk8qyjh83fmu55a9jnwzfdphr') {
                            $step_size = 1000;   // CHKS
                        }
                        
                        $description = $asset->description ? $asset->description : "";
                        
                        $token = Token::firstOrCreate(
                            [
                                'name'        => $asset->asset_name,
                                'fingerprint' => $fingerprint,
                            ],
                            [
                                'name_hex'    => $asset->asset_hex,
                                'policy_id'   => $asset->policy_id,
                                'decimals'    => $asset->decimals,
                                'step_size'   => $step_size,
                                'logo_url'    => $asset->logo_url,
                                'token_type'  => 'BASE',
                                'metadata'    => null,
                                'description' => substr($description, 0, 2048)
                            ]
                        );

                        /**
                         * Internally users  work only  with the available account, all  internal transactions
                         * are carried here and tokens are immediately sent to the recipient available account
                         */ 

                        if ($address !== $service) {

                            /**                         
                             * The Cardano network fee of the transfer of all assets
                             * from the deposit  wallet to the system wallet is paid
                             * by the deposit wallet holder.
                             */

                            if ($asset->asset_name == 'ADA') {
                                $asset->quantity -= $netFee;
                            }
                       
                            if (!empty($token) && ($deWallet = Wallet::where('id', $wallet->id)->where('type', 'deposit')->first())) {
                                Transfer::execute(null, $deWallet, $token, $asset->quantity, 'onchain_in', 0, 'IBT');
                            }

                        } else {
                          
                            if (!empty($token) && ($adminWallet = Wallet::where('parent_wallet_id', $wallet->id)->where('type', 'available')->first())) {
                                $current = 0;
                                $before  = 0;                                
            
                                $pivot = $adminWallet->tokens()->where('token_id', $token->id)->first();

                                if ($pivot) {
                                    $current = $pivot->pivot->quantity;
                                    $before  = $pivot->pivot->quantity;                                    
                                
                                    $tokens = DB::table('tokens as t')
                                        ->leftJoin('token_wallet as tw', 't.id', '=', 'tw.token_id')
                                        ->select(
                                            't.id as token_id',
                                            't.name',
                                            DB::raw('COALESCE(SUM(tw.quantity), 0) as total_quantity'),
                                            DB::raw('COALESCE(SUM(tw.quantity), 0) - COALESCE(SUM(CASE WHEN tw.wallet_id = '.$adminWallet->id.' THEN tw.quantity END), 0) as diff_quantity')
                                        )
                                        ->where('t.id', $token->id)
                                        ->groupBy('t.id', 't.name')
                                        ->first();
                
                                    if ($tokens) {                                    
                                        $current = max($asset->quantity - $tokens->diff_quantity, 0);
                                    
                                    } else {
                                        logger("💸 Tokens empty in pivot exist");
                                    }

                                    if ($current != $before) {
                                        $adminWallet->tokens()->updateExistingPivot($token->id, [
                                            'quantity' => $current
                                        ]);
                                                                            
                                        logger("💸 Updated in pivot exist ({$asset->asset_name}): ({$asset->quantity}), ({$tokens->total_quantity}), ({$tokens->diff_quantity}), ({$current}), ({$before})");
                                    }
            
                                } else {
                                    $current = $asset->quantity;
                                    $before  = 0;

                                    $tokens = DB::table('tokens as t')
                                        ->leftJoin('token_wallet as tw', 't.id', '=', 'tw.token_id')
                                        ->select(
                                            't.id as token_id',
                                            't.name',
                                            DB::raw('COALESCE(SUM(tw.quantity), 0) as total_quantity'),
                                            DB::raw('COALESCE(SUM(tw.quantity), 0) - COALESCE(SUM(CASE WHEN tw.wallet_id = '.$adminWallet->id.' THEN tw.quantity END), 0) as diff_quantity')
                                        )
                                        ->where('t.id', $token->id)
                                        ->groupBy('t.id', 't.name')
                                        ->first();

                                    if ($tokens) {
                                        $current = max($asset->quantity - $tokens->diff_quantity, 0);
                                    
                                    } else {
                                        logger("💸 Tokens empty in pivot does not exist");
                                    }                                    

                                    if ($current != $before) {                                 
                                        $adminWallet->tokens()->attach($token->id, [
                                            'quantity' => $current
                                        ]);   
                                                                        
                                        logger("💸 Attached in pivot does not exist ({$asset->asset_name}): ({$asset->quantity}), ({$tokens->total_quantity}), ({$tokens->diff_quantity}), ({$current}), ({$before})");
                                    }
                                }
                               
                                if ($current != $before) {
                                    
                                    logger("💸 Create transaction ({$asset->asset_name}): ({$asset->quantity}), ({$tokens->total_quantity}), ({$tokens->diff_quantity}), ({$current}), ({$before})");

                                    $txn = '0';

                                    DB::transaction(function () use ($adminWallet, $token, $current, $txn, $before, $service) {

                                        $tran = Transfer::create([
                                            'type'           => 'onchain_in',
                                            'from_wallet_id' => $adminWallet->parent_wallet_id,
                                            'to_wallet_id'   => $adminWallet->id,
                                            'token_id'       => $token->id,
                                            'quantity'       => $current,
                                            'fee'            => 0,
                                            'tx_hash'        => $txn,
                                            'status'         => 'completed',
                                            'note'           => 'AWRT',
                                            'created_at'     => now(),
                                            'updated_at'     => now(),
                                        ]);

                                        if (!empty($tran)) {

                                            WalletReconciliation::create([
                                                'transfer_id'     => $tran->id,
                                                'wallet_id'       => $adminWallet->id,
                                                'token_id'        => $token->id,
                                                'quantity_before' => $before,
                                                'quantity_after'  => $current,
                                                'change'          => $current - $before,
                                                'tx_hash'         => $txn,
                                                'note'            => 'AAWU',
                                            ]);
                                        }                                     

                                        logger("💸 Admin wallet corrected by ({$current}) from {$service}");
                                    });
                                }
                            }                            
                        } 
                    }
                }
            }

            $wallet->last_checked_at = now();

            $wallet->save();
        }

        // Make sure, we do not call the cron job twice

        if (file_exists('/tmp/readwallets-ville.lock')) {
            unlink('/tmp/readwallets-ville.lock');
        }
    }

    private function transferAllAssets(array $assets, string $address, string $service, string $direction, float &$spentFee, array &$txoutCopy) : int
    {
        $data = [];

        foreach($assets as $asset) {

            if ($asset->quantity) {

                if ((strtoupper($asset->asset_name) !== 'ADA') && !empty($asset->policy_id) && !empty($asset->asset_hex)) {

                    if (str_starts_with($asset->asset_hex, '\x')) {
                        $asset->asset_hex = substr($asset->asset_hex, 2);
                    }

                    if (str_starts_with($asset->policy_id, '\x')) {
                        $asset->policy_id = substr($asset->policy_id, 2);
                    }
                                                    
                    if (str_starts_with($asset->asset_hex, '0014df10')) {                                           
                        $asset->asset_name = hex2bin(substr($asset->asset_hex, 8));                        

                    } else if (str_starts_with($asset->asset_hex, '000643b0')) {
                        $asset->asset_name = hex2bin(substr($asset->asset_hex, 8));                        

                    } else {                                    
                        $asset->asset_name = hex2bin($asset->asset_hex);
                    }                    
                }

                $destination = ($address == $service) ? $asset->destination : $service;
                
                $tokenId = ($asset->token_id != null) ? $asset->token_id : 0;

                $walletId = ($asset->wallet_id != null) ? $asset->wallet_id : 0;

                $transferId = ($asset->transfer_id != null) ? $asset->transfer_id : 0;

                $fingerprint = ($asset->fingerprint != null) ? $asset->fingerprint : '';

                $data[] = [
                    'transfer_id'  => $transferId,
                    'token_id'     => $tokenId,
                    'wallet_id'    => $walletId,
                    'asset_name'   => $asset->asset_name,
                    'asset_hex'    => $asset->asset_hex,
                    'policy_id'    => $asset->policy_id,
                    'fingerprint'  => $fingerprint,
                    'token_number' => intval($asset->quantity),
                    'destination'  => $destination
                ];
            }
        }

        /**
         * $data    => User's assets (CNT and ADA, also ADA with the CNTs, if only CNTs are sent), but separated
         * $address => User's deposit wallet address
         * $service => Admin's deposit wallet address
         * 
         * This transaction leaves the User's deposit wallet empty
         */
                
        return $this->sendAllAssets($data, $address, $service, $direction, $spentFee, $txoutCopy);
    }

    private function sendAllAssets(array $data, string $address, string $service, string $direction, float &$spentFee, array &$txoutCopy): int
    {                           
        $metaData = new ContainerMetadata();

        $nftMetaData = $metaData->generateContainerMetaData($data, $address, $service, $direction);
        
        $txPrefix = Storage::path('transactions').DIRECTORY_SEPARATOR.bin2hex(openssl_random_pseudo_bytes(4)).DIRECTORY_SEPARATOR;
              
        $err = [];
        
        $err['response'] = 'error';
        $err['message'] = '';
        
        $spentFee = 0;
        $netwkFee = 0;

        /**
         * Build the transaction for all on chain transfers, this function is used for incoming
         * AND outgoing transactions. In the latter case $address === $service (change address)
         */   
                
        if (CardanoCliWrapper::make_dir($txPrefix) && ($address !== $service || $address == trim(config('chimera.admin_wallet')))) {

            $err = app(BabelTransactionController::class)->generate_transaction($data, $address, $service, $txPrefix, $nftMetaData, $spentFee, $netwkFee, $txoutCopy);

            if (!empty($err['response']) && ($err['response'] == 'success')) {

                $cli = new CardanoCliWrapper($txPrefix, '/usr/local/bin/cardano-cli');

                if (file_exists($cli->txPrefix.'matx.signed')) {      
                    $retVal = $cli->submitTransaction();                    
                  
                    if (strlen($retVal) == 64) {

                        $userId = Wallet::where('address', $address)->where('type', 'deposit')->value('user_id');
                                      
                        Transaction::Create([
                            'user_id' => $userId,
                            'transaction_id' => $retVal,
                            'transaction_fee' => floatval($netwkFee),
                            'direction' => $direction
                        ]);

                        if ($direction == 'outbound') {

                            Outbound::Create([
                                'user_id' => $userId,
                                'transaction_id' => $retVal,
                                'transaction_fee' => floatval($netwkFee),
                                'direction' => $direction
                            ]);
                        }
                            
                        $err['response'] = 'success';

                    } else {
                        $err['response'] = 'error';
                        $err['message']  = 'Submit failed';
                    }
                }
            }

            CardanoCliWrapper::remove_dir($txPrefix);
        }

        /**
         * We use only one NODE, and  it might be  busy to put the request into the buffer.
         * To avoid immediate failure, in case of several in-payments, we delay the process
         * between individual in-payments. (This is a cron job).
         */

        if ($err['response'] == 'success') {
            sleep(15);
        }

        return ($err['response'] == 'success') ? intval(1000000 * $netwkFee) : 0;
    }
    
    /**
     * The outgoing cron job.
     */

    public function readReservedWallets(): void
    {
        /**
         * If anything is still in the outbound table, it has not been proccessed yet.
         * We return  immediately. The outbounds table  gets  cleared after successful
         * proccessing the metadata.
         */

        logger("sendassets processing");  

        $outbounds = Outbound::where('direction', 'outbound')->whereNotNull('transaction_id')->limit(1)->get();

        if ($outbounds->isEmpty()) {
            $address = trim(config('chimera.admin_wallet'));
            $service = trim(config('chimera.admin_wallet'));
      
            /**
             * All assets waiting in reserved accounts for outgoing transfers.
             * We might limit the  number later, if too  many per transaction.
             */            
            $assets = DB::table('wallets as w')
                ->join('users as u', 'u.id', '=', 'w.user_id')
                ->join('token_wallet as tw', 'tw.wallet_id', '=', 'w.id')
                ->join('tokens as t', 't.id', '=', 'tw.token_id')
                ->join('transfers as tr', function($join) {
                    $join->on('tr.from_wallet_id', '=', 'w.id')
                        ->on('tr.token_id', '=', 't.id');
                })
                ->select(
                    'w.id as wallet_id',
                    't.id as token_id',
                    't.name as asset_name',
                    't.name_hex as asset_hex',
                    't.policy_id',
                    't.fingerprint as fingerprint',
                    'tr.quantity as quantity',
                    DB::raw('COALESCE(tr.receiver_address, u.payout) as destination'),
                    'tr.id as transfer_id',
                    'tr.quantity as pending_quantity'
                )
                ->where('w.type', 'reserved')
                ->where('tw.quantity', '>', 0)
                ->where('tr.status', 'pending')
                ->where('tr.type', 'onchain_out')
                ->whereNotNull('u.payout')
                ->orderBy('u.id')
                ->orderBy('w.id')
                ->orderBy('t.id')
                ->limit(10)
                ->get()
                ->toArray();
        
            $netFee = 0;

            $spentFee = 0;

            $txoutCopy = [];

            /**
             * The real blockchain transfer activity for outgoing from the Admin address,
             * change  address  Admin address, destinations  (later: tx-outs) in  assets.
             */                

            if (!empty($assets) && ($address == $service)) {
            
                // Todo: Secure and counter check assets and addresses, assets leave the system
            
                $netFee = $this->transferAllAssets($assets, $address, $service, 'outbound', $spentFee, $txoutCopy);                
            }
        }

        if (file_exists('/tmp/sendassets-ville.lock')) {
            unlink('/tmp/sendassets-ville.lock');
        }
    }

    public function checkTransactions(): void
    {
        logger("transactions processing");

        $outbounds = Outbound::where('direction', 'outbound')
            ->whereNotNull('transaction_id')
            ->orderBy('id')
            ->chunk(100, function ($chunk) {
                foreach ($chunk as $outbound) {
                    $txnId = $outbound->transaction_id;
            
                    if (!preg_match('/^[0-9a-fA-F]+$/', $txnId)) {
                        Log::warning('Invalid transaction_id format', ['id' => $outbound->id, 'transaction_id' => $txnId]);
                        continue;
                    }

                    $txn = '\x' . $txnId;

                    try {
                    
                        if ($this->reconcileAggregatedTransfers($txn) === true) {                    
                            $outbound->delete();
                  
                            Log::info('Outbound deleted after reconciliation', ['id' => $outbound->id, 'transaction_id' => $txnId]);
                        }
            
                    } catch (\Throwable $e) {            
                        Log::error('Reconciliation failed', ['id' => $outbound->id, 'error' => $e->getMessage()]);
                    }
                }
        });
    }

    public function reconcileAggregatedTransfers(string $txn): bool
    {      
        try {            
            $metaData = $this->getMetadata($txn);
            
            if (!$metaData || !$metaData->json) {
                logger("❌ No metadata found for txn: {$txn}");
                return false;
            }

            $metaJson = json_decode($metaData->json, true);

            if (!isset($metaJson['body'])) {
                logger("❌ Metadata has no 'body' for txn: {$txn}");
                return false;
            }

            $adaToken = Token::where('name', 'ADA')->first();
        
            if (!$adaToken) {
                logger("❌ ADA token not found in DB");
                return false;
            }

            $results = [];
        
            $headAddr = config('chimera.admin_wallet');
            
            foreach ($metaJson['body'] as $walletId => $entries) {
                $reservedWallet = Wallet::find($walletId);

                if (!$reservedWallet) {
                    logger("⚠️ Reserved wallet {$walletId} not found in DB");
                    continue;
                }
                    
                foreach ($entries as $entry) {
                    $transferId = (int) $entry['t'];
                    
                    $transfer = Transfer::find($transferId);
        
                    if (!$transfer) {
                        logger("⚠️ Transfer {$transferId} not found for wallet {$walletId}");
                        continue;
                    }

                    $token = Token::find($transfer->token_id);
        
                    if (!$token) {
                        logger("⚠️ Token not found for transfer {$transferId}");
                        continue;
                    }

                    $resBefore = 0;
                    $resChange = 0;
                    $resNewQty = 0;
                 
                    if ($transfer->status === 'pending') {            
                        $pivot = $reservedWallet->tokens()->where('token_id', $token->id)->first();
                        
                        $resBefore = $pivot ? $pivot->pivot->quantity : 0;
                        $resNewQty = max($resBefore - $transfer->quantity, 0);
                        $resChange = $resNewQty - $resBefore;
            
                        if ($pivot) {
                            $reservedWallet->tokens()->updateExistingPivot($token->id, ['quantity' => $resNewQty]);
                        
                        } else {
                            $reservedWallet->tokens()->attach($token->id, ['quantity' => $resNewQty]);
                        }
                        
                        $transfer->update([
                            'status'     => 'completed',
                            'updated_at' => now(),
                            'tx_hash'    => $txn,
                        ]);

                        WalletReconciliation::create([
                            'transfer_id'     => $transfer->id,
                            'wallet_id'       => $reservedWallet->id,
                            'token_id'        => $token->id,
                            'quantity_before' => $resBefore,
                            'quantity_after'  => $resNewQty,
                            'change'          => $resChange,
                            'tx_hash'         => $txn,
                            'note'            => "Reconciled via metadata",
                        ]);

                        logger("✅ Reconciled reserved transfer {$transferId} for wallet {$walletId} ({$token->name})");

                        $results[] = compact('walletId', 'transferId', 'resBefore', 'resNewQty');
                    }
                }
            }
            
            $adminTotalDeduction = $this->adjustDepositWithAccountADA();
            
            logger("Total spent by admin wallet for txn: $adminTotalDeduction Lovelace");

            if ($adminTotalDeduction != 0 && $headAddr) {
                $adminWallet = Wallet::where('address', 'ava'.$headAddr)->first();

                if ($adminWallet) {
                    
                    $pivot = $adminWallet->tokens()->where('token_id', $adaToken->id)->first();

                    if ($pivot) {
                        $before = $pivot->pivot->quantity;
                        $newQty = max($before - $adminTotalDeduction, 0);
                        $fromVersion = $pivot?->pivot->quantity_version ?? 0;
                        
                        $adminWallet->tokens()->updateExistingPivot($adaToken->id, [
                            'quantity' => $newQty,
                            'quantity_version' => $fromVersion + 1
                        ]);

                        DB::transaction(function () use ($adminWallet, $adaToken, $adminTotalDeduction, $txn, $before, $newQty, $fromVersion, $headAddr) {

                            $tran = Transfer::create([
                                'type'           => 'onchain_out',
                                'from_wallet_id' => $adminWallet->id,
                                'to_wallet_id'   => null,
                                'token_id'       => $adaToken->id,
                                'quantity'       => $adminTotalDeduction,
                                'fee'            => 0,
                                'tx_hash'        => $txn,
                                'status'         => 'completed',
                                'note'           => 'BNC',                            
                                'created_at'     => now(),
                                'updated_at'     => now(),
                            ]);

                            if (!empty($tran)) {

                                WalletReconciliation::create([
                                    'transfer_id'     => $tran->id,
                                    'wallet_id'       => $adminWallet->id,
                                    'token_id'        => $adaToken->id,
                                    'quantity_before' => $before,
                                    'quantity_after'  => $newQty,
                                    'change'          => $newQty - $before,
                                    'tx_hash'         => $txn,
                                    'note'            => "Admin account wallet update",
                                ]);
                            }

                            logger("💸 Admin wallet corrected by ({$adminTotalDeduction} lovelace) from {$headAddr}");
                        });
                    }

                } else {
                    logger("⚠️ Admin wallet for {$headAddr} not found in DB");
                }
            }

            logger("🎯 Reconciliation complete for txn: {$txn}", $results);

            return true;

        } catch (\Throwable $e) {
            logger()->error('❌ Reconciliation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    private function adjustDepositWithAccountADA(): int
    {
        $adaDiff  = 0;

        $totalADA = DB::table('tokens as t')
            ->join('token_wallet as tw', 'tw.token_id', '=', 't.id')
            ->join('wallets as w', 'w.id', '=', 'tw.wallet_id')
            ->whereIn('w.type', ['available', 'reserved'])
            ->where('t.name', 'ADA')
            ->sum('tw.quantity');
     
        $adminId = User::where('email', config('chimera.admin_user'))->value('id');

        if(!empty($adminId)) {
            $adminWallet = Wallet::where('user_id', $adminId)->where('type', 'deposit')->value('address');
                    
            if (!empty($adminWallet)) {
       
                // API call to external resource
                $adminDepositADA = $this->getAdminDeposit($adminWallet);

                if (!empty($adminDepositADA)) {
                    $totalAdminDepositADA = $adminDepositADA ?? 0;

                    $adaDiff = $totalADA - $totalAdminDepositADA;
                }
            }
        }
        
        return $adaDiff;
    }

    private function transferAllDepositToAvailable(string $address): bool
    {   
        if ($deWallet = Wallet::where('address', $address)->where('type', 'deposit')->first()) {
                    
            $tokens = $deWallet->tokens()->wherePivot('quantity', '>', 0)->withPivot('quantity')->get();

            if ($tokens->isEmpty()) {                
                Log::debug('transferAllDeposits no tokens: '.$address);
                return false;
            }

            if ($avWallet = Wallet::where('address', 'ava'.$address)->where('type', 'available')->first()) {

                foreach ($tokens as $token) {
                    $quantity = $token->pivot->quantity;

                    Transfer::execute($deWallet, $avWallet, $token, $quantity, 'internal', 0, 'D2WT');
                                        
                    Log::debug('readdepositwallets fill account from deposit wallet: '.$quantity);
                }

                return true;
            }
        }

        return false;
    }

    // API call to extrnal resource
    private static function getAdminDeposit(string $wallet)
    {
        try {
            $response = Http::timeout(5)->get(config('chimera.cexplorer').'/cardano/admin-deposit', [
                'address' => $wallet,
            ]);

            if ($response->successful()) {
                return $response->json()['quantity'] ?? 0;

            } else {
                Log::error('Admin deposit API failed: '.$response->body());
                return 0;
            }

        } catch (\Exception $e) {
            Log::error('Admin deposit API exception: '.$e->getMessage());
            return 0;
        }
    }

    // API call to external resource
    private static function getMetadata(string $txn)
    {
        try {
            $response = Http::timeout(5)->get(config('chimera.cexplorer').'/cardano/metadata', [
                'txn' => $txn,
            ]);

            if ($response->successful()) {                
                return ((object) ['json' => array_map(fn($item) => (object) $item, $response->json())['json']->scalar]);

            } else {
                Log::error('Admin assets API failed: '.$response->body());
                return null;
            }

        } catch (\Exception $e) {
            Log::error('Admin assets API exception: '.$e->getMessage());
            return null;
        }
    }

}
