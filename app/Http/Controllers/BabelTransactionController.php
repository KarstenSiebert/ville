<?php

namespace App\Http\Controllers;

use Exception;
use RuntimeException;
use App\Models\Wallet;
use App\Models\InputOutput;
use App\Helpers\CardanoCliWrapper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class BabelTransactionController extends Controller
{
    private CardanoCliWrapper $cli;

    private string $protocolFile;

    public function __construct()
    {
        $this->protocolFile = Storage::path('transactions').'/protocol.json';        
    }

    private function groupTotals(array $data): array
    {
        $result = [];
        
        foreach ($data as $row) {
            $key = $row['destination'] . '-' . $row['policy_id'] . '-' . $row['asset_name'];

            if (!isset($result[$key])) {
                $result[$key] = [
                    'destination'  => $row['destination'],
                    'policy_id'    => $row['policy_id'],
                    'asset_name'   => $row['asset_name'],
                    'asset_hex'    => $row['asset_hex'],
                    'token_number' => 0
                ];
            }

            $result[$key]['token_number'] += $row['token_number'];
        }
    
        return array_values($result);
    }
    
    public function generate_transaction(array $txOutList, string $source, string $service, string $txPrefix, string $nftMetaData, float &$fee, float &$net, array &$txoutCopy): ?array
    {           
        $err = [];

        $err['response'] = 'error';
        $err['message']  = '';
        $err['id']       = '';
        
        $txout = [];
        $txins = [];

        $tokenlists = [];

        $pairs = [];
        
        $sourceLovelace = 0;

        $this->cli = new CardanoCliWrapper($txPrefix, '/usr/local/bin/cardano-cli');

        if (!file_exists($this->protocolFile)) {
            try {
                $this->cli->queryProtocolParams($this->protocolFile);

            } catch (RuntimeException $e) {
                // dd($e->getMessage());
            }
        }

        $txOutList = $this->groupTotals($txOutList);

        $chain = [];
        $adach = [];

        foreach ($txOutList as $txo) {
            if (strtoupper($txo['asset_name']) !== 'ADA') {
                $chain[] = explode(',', trim($txo['destination']).', 0 ,'.$txo['token_number'].','.$txo['policy_id'].'.'.$txo['asset_hex']);
            } else {                
                $chain[] = explode(',', trim($txo['destination']).','.$txo['token_number'].', 0 ,'.'ADA');
            }
        }

        $combinedTxout = [];

        /**
         * For each outgoing wallet we build the --tx-out
         */
        foreach ($chain as [$addr, $lovelace, $value, $string]) {
                
            if (!isset($combinedTxout[$addr])) {
                if (!strcmp($string, 'ADA')) {
                    $combinedTxout[$addr] = $lovelace.'';
                } else {
                    $combinedTxout[$addr] = '0+"'.$value.' '.$string.'"';
                }
            } else {
                if (!strcmp($string, 'ADA')) {
                    $combinedTxout[$addr] .= '+"'.$value.'"';
                } else {
                    $combinedTxout[$addr] .= '+"'.$value.' '.$string.'"';
                }
            }
        }

        $sum = 0;        

        /**
         * And calculate the minimum ADA required to fullfill the transaction
         */
        foreach ($combinedTxout as $addr => $combined) {
            $strm = $addr.'+'.$combined;

            $parts = explode('+', $combined);

            $lovelace = (int)$parts[0];

            $trxtail = substr($combined, strlen($parts[0]));

            $stro = $addr.'+0'.$trxtail;

            $mval = 0;
            $mvan = 0;

            try {
                $mval = $this->cli->calculateMinRequiredUtxo($strm, $this->protocolFile);

                $mvan = $this->cli->calculateMinRequiredUtxo($stro, $this->protocolFile);

                if ($lovelace > $mval) {
                    $mval = $lovelace;                    
                }
              
                $sum += intval($mval);
                
                $txout[] = '--tx-out '.$addr.'+'.$mval.$trxtail;
                    
            } catch (RuntimeException $e) {
                // dd($e->getMessage());
            }
        }

        /**
         * Now build all --tx-in for the transaction.
         * Start  with  the  source  address  itself.
         */
        $tokenList = $this->getTokenInList($source);
               
        foreach ($tokenList as $list) {
            $txins[] = "--tx-in ".substr($list->tx_hash, 2).'#'.$list->index;
            $pairs[] = "('{$list->tx_hash}', '{$list->index}')";
        }

        // We have built ALL --tx-in, we need only --tx-in with tokens from $data

        $pairList = implode(", ", $pairs);
        
        if (!empty($pairs) && !empty($pairList)) {                
            $tokenList = $this->getTokenList($source);

        } else {
            $tokenList = [];
        }
 
        // $tokenList consists of all input values of the source wallet, ADA and tokens.
        
        if (!empty($tokenList)) {
            $sourceLovelace = collect($tokenList)->unique(fn($tx) => $tx->tx_hash . '-' . $tx->index)->sum(fn($tx) => (int) $tx->value);
        
        } else {
            $sourceLovelace = 0;

            $err['message'] = 'sourceLovelace is 0';

            return $err;
        }

        // We need sufficient ADA to process the transaction.

        // $sourceLovelace is the entire Lovelace of the inputs, $tokenList are all inputs, $txOutList are all outputs

        $aggregated = [];

        foreach ($tokenList as $list) {    
            $policy = bin2hex($list->policy_id);
            $asset  = bin2hex($list->asset_name);
            $key    = $policy . '_' . $asset;

            if (!isset($aggregated[$key])) {
        
                $aggregated[$key] = [
                    'policy_id'  => $list->policy_id,
                    'asset_name' => $list->asset_name,
                    'quantity'   => 0,
                ];
            }

            $aggregated[$key]['quantity'] += (int) $list->quantity;
        }
    
        $aggregated = array_values($aggregated);

        $tempList = [];

        /**
         * $aggregated combines  all inputs, where  different
         * tokens from $data are found on the same input UTxO
         */        
        foreach($aggregated as $token) {

            foreach($txOutList as $list) {
                if ($list['asset_hex'] == substr($token['asset_name'], 2)) {
                    $token['quantity'] -= $list['token_number'];
                }
            }            

            $tempList[] = $token;
        }
        
        $tout = '';
        
        foreach($tempList as $token) {
                        
            if ($token['quantity'] > 0) {
                $tout .= '+"'.$token['quantity'].' '.substr($token['policy_id'], 2).'.'.substr($token['asset_name'], 2).'"'; 
            }           
        }

        // Sourcelovelace no contains the value of loevelace without the entire mval values

        $sourceLovelace -= ($sum + intval(1000000 * $fee));
        
        // If there is Lovelace, the source shall get this back as a seperate output (will be merged, if output == change wallet)

        if ($sourceLovelace > 0) {
            $txou = $source.'+0'.$tout;
        
            // This is the entire tx-out before calculation to return to the source

            try {
                $mval = $this->cli->calculateMinRequiredUtxo($txou, $this->protocolFile);

                if ($mval <= $sourceLovelace) {
                    $mval  = $sourceLovelace;
            
                } else {                
                    $err['message'] = 'Not enough ADA, missing: '. abs($sourceLovelace);
                    return $err;
                } 

                // Achtung, wenn zu klein muss Fehler kommen, sonst bezahlt service !!!

                $txout[] = '--tx-out '.$source.'+'.$mval.''.$tout;
                    
            } catch (RuntimeException $e) {
                dd("CLI Error: " . $e->getMessage());
            }
        }

        $tokenList = $this->getTokenList($service);
               
        foreach($tokenList as $token) {                
            $txins[] = '--tx-in '.substr($token->tx_hash, 2).'#'.$token->index;
        }
        
        $txins = array_unique($txins);            
        
        // Filter all txout for service, as those go to change anyway

        $txoutCopy = unserialize(serialize($txout));
                
        $txout = array_values(array_filter($txout, fn($v) => !str_contains($v, $service)));

        // dd($service, $txins, $txoutCopy, $txout, "STOP");
                
        try {
            $transactionFee = $this->cli->buildTransaction($txins, $txout, $service, 'matx.raw', $sourceLovelace, $nftMetaData);
            
            // dd($transactionFee, $sum, $service, $this->cli->txPrefix.'matx.raw');
            
            // Limit transaction fees to 400000 Lovelace, currently hard coded. Ths moves to the new screen

            if (file_exists($this->cli->txPrefix.'matx.raw') && !empty($transactionFee) && (intval($transactionFee) < 400000)) {
                
                /**
                 * New process sends raw transaction and both users to the signing authority
                 * to received the signed transaction  for further  submission to the chain.
                 */
                $provider = Wallet::where('address', $service)->where('type', 'deposit')->first();                
                $userWall = Wallet::where('address',  $source)->where('type', 'deposit')->first();
                       
                $rawTransaction = file_get_contents($this->cli->txPrefix.'matx.raw');

                if (!empty($rawTransaction) && ($signedTransaction = $this->getSignedTransaction($rawTransaction, $provider->user_id, $userWall->user_id))) {

                    if (file_exists($this->cli->txPrefix.'matx.signed')) {

                        InputOutput::Create([
                            'user_id'   => $userWall->user_id,
                            'wallet_id' => $provider->id,
                            'inputs'    => implode(' ', $txins),
                            'outputs'   => implode(' ', $txoutCopy),
                            'change'    => $service
                        ]);
                    
                        $net = $transactionFee / 1000000;

                        $fee = $sum / 1000000;
                           
                        $err['response'] = 'success';
                    }                
                }
            }

        } catch (RuntimeException $e) {

            Log::error('buildTransaction exception: '.$e->getMessage());
            
            if (preg_match('/Error:\s*(.+?\.)/s', $e->getMessage(), $matches)) {
                $err['message'] = $matches[1];

            } else {
                $err['message'] = 'Error in assembling.';
            }            
        }

        return $err;
    }

    /**
     * This function will be out-sourced to a separate key server.
     */
    private function getSignedTransaction(string $rawTransaction, int $providerId, int $userId): ?string
    {
        $this->cli->witnessTransaction($providerId, 'provider');
                                        
        if (file_exists($this->cli->txPrefix.'witness.provider')) {

            // User can be User (incoming payments) or Admin (outgoing payments) 
                                                
            $this->cli->witnessTransaction($userId, 'user');
                        
            if (file_exists($this->cli->txPrefix.'witness.user')) {                            
                $this->cli->assembleSignature();

                if (file_exists($this->cli->txPrefix.'matx.signed')) {
                    return file_get_contents($this->cli->txPrefix.'matx.signed');
                }
            }
        }
                   
        return null;
    }

    private function getTokenInList(string $wallet)
    {
        try {
            $response = Http::timeout(5)->get(config('chimera.cexplorer').'/cardano/token-in-list', [
                'address' => $wallet,
            ]);

            if ($response->successful()) {
                return array_map(fn($item) => (object) $item, $response->json());

            } else {
                Log::error('Admin assets API failed: '.$response->body());
                return 0;
            }

        } catch (\Exception $e) {
            Log::error('Admin assets API exception: '.$e->getMessage());
            return 0;
        }
    }

    private function getTokenList(string $wallet)
    {
        try {
            $response = Http::timeout(5)->get(config('chimera.cexplorer').'/cardano/token-list', [
                'address' => $wallet,
            ]);

            if ($response->successful()) {
                return array_map(fn($item) => (object) $item, $response->json());

            } else {
                Log::error('Token list API failed: '.$response->body());
                return 0;
            }

        } catch (\Exception $e) {
            Log::error('Token list API exception: '.$e->getMessage());
            return 0;
        }
    }

}
