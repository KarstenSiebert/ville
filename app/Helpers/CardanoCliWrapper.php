<?php

namespace App\Helpers;

use App\Http\Services\VaultService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class CardanoCliWrapper
{
    private string $cliPath;

    public int $sourceLovelace;

    public string $txPrefix;
    
    public int $transactionFee;

    public function __construct(string $txPrefix, string $cliPath = '/usr/local/bin/cardano-cli')
    {
        $this->cliPath = $cliPath;
        $this->txPrefix = $txPrefix;
        $this->transactionFee = 0;
        $this->sourceLovelace = 0;
    }

    private function run(array $args): string
    {
        $cmd = $this->cliPath . ' ' . implode(' ', $args);

        if (in_array('tip', $args, true)) {
            $cmd .= ' | jq .slot? ';
        }
        
        if (in_array('build', $args, true)) {

            // dd($cmd);
            
            // Security check, the source has to get all ADA back, if babel fees are used.

            if (($this->sourceLovelace > 0) && !str_contains($cmd, $this->sourceLovelace)) {
                // throw new RuntimeException("Cardano-CLI failed:\nCommand: $cmd\nOutput:\n" . $this->sourceLovelace);
            }

            // dd($this->sourceLovelace, $cmd);            
        }
        
        $cmd .= ' 2>&1';

        $output = [];
        $ret    = 0;

        exec($cmd, $output, $ret);

        if ($ret !== 0) {
            // throw new RuntimeException("Cardano-CLI failed:\nCommand: $cmd\nOutput:\n" . implode("\n", $output));
            Log::error('Cardano-CLI failed: Command: '.$cmd.' Output: '.implode("\n", $output));
        }

        return implode("\n", $output);
    }

    public function queryProtocolParams(string $outFile)
    {        
        try {
            $response = Http::timeout(5)->get(config('chimera.cexplorer').'/cardano/query_protocol_params');

            if ($response->successful()) {

                $fileString = base64_decode($response->json()['params']) ?? null;

                if ($fileString) {
                    file_put_contents($outFile, $fileString);
                }

            } else {
                Log::error('Slot query protocol params API failed: '.$response->body());
                return null;
            }

        } catch (\Exception $e) {
            Log::error('Slot query protocol params API exception: '.$e->getMessage());
            return null;
        }
    }

    public function calculateMinRequiredUtxo(string $tout, string $protocolParamsFile): ?int
    {
        $str = $this->run([
            'conway', 'transaction', 
            'calculate-min-required-utxo',
            '--protocol-params-file', $protocolParamsFile,
            '--tx-out', $tout            
        ]);

        if (preg_match('/\d+/', $str, $matches)) {
           return (int)$matches[0];
        }
        
        return null;
    }

    public function getSlotNumber(int $slotDistance = 1000): ?int
    {
        try {
            $response = Http::timeout(5)->get(config('chimera.cexplorer').'/cardano/get_slot_number', [
                'slotDistance' => $slotDistance,
            ]);

            if ($response->successful()) {
                return $response->json()['slot'] ?? 0;

            } else {
                Log::error('Slot distance API failed: '.$response->body());
                return null;
            }

        } catch (\Exception $e) {
            Log::error('Slot distance API exception: '.$e->getMessage());
            return null;
        }
    }
    
    public function witnessTransaction($user_id, $signer, $rawTransaction = null): void
    {
        if (!empty($rawTransaction)) {
            file_put_contents($this->txPrefix.'matx.raw', $rawTransaction);
        }

        if (file_exists($this->txPrefix.'matx.raw') && $this->generateSigningKey($user_id)) {
            $signingKeyFile = $this->txPrefix.'payment.skey';
                
            $txBodyFile = $this->txPrefix.'matx.raw';

            $outFile = $this->txPrefix.'witness.'.$signer;
        
            $str = $this->run([
                'conway', 'transaction', 
                'witness', '--mainnet',
                '--signing-key-file', $signingKeyFile,
                '--tx-body-file', $txBodyFile,
                '--out-file', $outFile            
            ]);
        
            if (file_exists($signingKeyFile)) {
                unlink($signingKeyFile);
            }

            if (file_exists($this->txPrefix.'key.xsk')) {
                unlink($this->txPrefix.'key.xsk');
            }
        }
    }

    public function signTransaction($user_id, $rawTransaction = null): void
    {
        if (!empty($rawTransaction)) {
            file_put_contents($this->txPrefix.'matx.raw', $rawTransaction);
        }
        
        if (file_exists($this->txPrefix.'matx.raw') && $this->generateSigningKey($user_id)) {
            $signingKeyFile = $this->txPrefix.'payment.skey';

            $txBodyFile = $this->txPrefix.'matx.raw';

            $outFile = $this->txPrefix.'matx.signed';
        
             $str = $this->run([
                'conway', 'transaction', 
                'sign', '--mainnet',
                '--signing-key-file', $signingKeyFile,
                '--tx-body-file', $txBodyFile,
                '--out-file', $outFile            
            ]);
        
            if (file_exists($signingKeyFile)) {
                unlink($signingKeyFile);
            }

            if (file_exists($this->txPrefix.'key.xsk')) {
                unlink($this->txPrefix.'key.xsk');
            }
        }
    }

    public function buildTransaction(array $txins, array $txout, string $changeAddress, string $outFile, int $sourceLovelace = 0, string $metaData = '', string $mintingScript = ''): ?string
    {
        $commandArgs = [];

        $txinsString = implode(' ', $txins);
        $txoutString = implode(' ', $txout);

        $commandArgs['txinsString'] = $txinsString;
        $commandArgs['txoutString'] = $txoutString;

        $commandArgs['policy'] = !empty($mintingScript) ? $mintingScript : null;

        $this->sourceLovelace = $sourceLovelace;

        if (!empty($mintingScript)) {
            file_put_contents($this->txPrefix.'policy.script', $mintingScript);
        }

        $commandArgs['metadata'] = !empty($metaData) ? $metaData : null;

        if (!empty($metaData)) {
            file_put_contents($this->txPrefix.'metadata.json', $metaData);
        }

        $commandArgs['changeAddress'] = !empty($changeAddress) ? $changeAddress : null;

        $commandArgs['slotDistance'] = 1000;

        if (strlen($txinsString) && strlen($changeAddress)) {
            
            $commandArgs = base64_encode(json_encode($commandArgs));
        
            try {
                $response = Http::timeout(10)->get(config('chimera.cexplorer').'/cardano/build_transaction', [
                   'command_args' => $commandArgs
                ]);

                if ($response->successful()) {
                    $matx = $response->json()['matx'] ?? null;

                    if (!empty($matx)) {
                        $matx = base64_decode($matx);

                        if (file_put_contents($this->txPrefix.'matx.raw', $matx)) {                    
                            return $response->json()['fee'] ?? null;
                        }
                    }                    
                }
                
                Log::error('Build transaction API failed: '.$response->body());
                return null;                

            } catch (\Exception $e) {
                Log::error('Build transaction API exception: '.$e->getMessage());
                return null;
            }
        }
    }
    
    public function submitTransaction(): ?string
    {
        $matxSigned = base64_encode(file_get_contents($this->txPrefix.'matx.signed'));

        try {
            $response = Http::timeout(5)->get(config('chimera.cexplorer').'/cardano/submit_transaction', [
                'matx_signed' => $matxSigned,
            ]);

            if ($response->successful()) {
                return $response->json()['txhash'] ?? null;

            } else {
                Log::error('Submit transaction API failed: '.$response->body());
                return null;
            }

        } catch (\Exception $e) {
            Log::error('Submit transaction API exception: '.$e->getMessage());
            return null;
        }
    }
    
    public function assembleSignature(): void
    {   
        $directory = $this->txPrefix;

        $wt1 = $directory.'witness.provider';

        $wt2 = $directory.'witness.user';
            
        $inp = $directory.'matx.raw';

        $out = $directory.'matx.signed';
        
        $str = $this->run([
            'conway', 'transaction',
            'assemble',
            '--tx-body-file', $inp,
            '--witness-file', $wt1,
            '--witness-file', $wt2,
            '--out-file', $out
        ]);        
    }

    public function assembleRemoteSignature($witness, $strip = 0): void
    {   
        $directory = $this->txPrefix;

        $wt3 = $directory.'witness.user';
    
        file_put_contents($wt3, '{' . PHP_EOL);

        file_put_contents($wt3, '    "type": "TxWitness ConwayEra",' . PHP_EOL, FILE_APPEND);

        file_put_contents($wt3, '    "description": "Key Witness ShelleyEra",' . PHP_EOL, FILE_APPEND);
        
        file_put_contents($wt3, '    "cborHex": "' .substr($witness, $strip). '"' . PHP_EOL, FILE_APPEND);

        file_put_contents($wt3, '}' . PHP_EOL, FILE_APPEND);
      
        $wt1 = $directory.'witness.provider';

        $wt2 = $directory.'witness.user';
        
        $inp = $directory.'matx.raw';

        $out = $directory.'matx.signed';
        
        $str = $this->run([
            'conway', 'transaction', 
            'assemble',
            '--tx-body-file', $inp,
            '--witness-file', $wt1,
            '--witness-file', $wt2,
            '--out-file', $out
        ]);        
    }

    private function generateSigningKey($index): bool
    {        
        file_put_contents($this->txPrefix.'root.xsk', VaultService::getRootKey());
        
        $cmd = '/usr/local/bin/genskey.sh '.$this->txPrefix.'root.xsk '.$index.' '.$this->txPrefix;

        $ret = 0;

        $output = [];

        if (exec($cmd, $output, $ret) !== false) {
            
            if (file_exists($this->txPrefix.'payment.skey')) {
                return true;
            }
        }

        return false;
    }

    public static function getAdminAssets(string $wallet): array
    {        
        try {
            $response = Http::timeout(5)->get(config('chimera.cexplorer').'/cardano/admin-assets', [
                'address' => $wallet,
            ]);

            if ($response->successful()) {
                return array_map(fn($item) => (object) $item, $response->json());

            } else {
                Log::error('Admin assets API failed: '.$response->body());
                return [];
            }

        } catch (\Exception $e) {
            Log::error('Admin assets API exception: '.$e->getMessage());
            return [];
        }
    }

    public static function make_dir($path): bool
    {
        return is_dir($path) || mkdir($path);
    }

    public static function remove_dir($dir): bool
    {
        $done = false;

        try {
            if (is_dir($dir)) {
                $objects = scandir($dir);

                foreach ($objects as $object) {
                    if ($object != '.' && $object != '..') {
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                    }
                }

                rmdir($dir);

                $done = true;
            }
        } catch (Exception $e) {
            Log::error('remove_dir exception: '.$e->getMessage());
        }

        return $done;
    }        
   
}
