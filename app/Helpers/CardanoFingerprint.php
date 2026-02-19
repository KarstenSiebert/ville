<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class CardanoFingerprint
{
    // Bech32 charset
    private const CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

    /**
     * Generate a Cardano asset fingerprint (asset1...).
     *
     * @param string $policyIdHex 56-char policy ID (hex)
     * @param string $assetName UTF-8 asset name (empty string if none)
     * @return string asset fingerprint in bech32
     */
    public static function fromPolicyAndName(string $policyIdHex, string $assetName = '', bool $cpi68 = false, bool $nft = false): string
    {
        if (!ctype_xdigit($policyIdHex) || strlen($policyIdHex) !== 56) {
            file_put_contents('/tmp/policy-fehler', $policyIdHex.'-'.$assetName);
            throw new \InvalidArgumentException("Invalid policy ID (must be 56 hex chars).");
        }

        // asset name -> hex (UTF-8) -> for CIP68 the asset NameHex is prepended by 0014df10 or 000643b0
        $assetNameHex = bin2hex($assetName);

        if ($cpi68 === true) {
            $assetNameHex = '0014df10'.$assetNameHex;

            if ($nft === true) {
                $assetNameHex = '000643b0'.substr($assetNameHex, 8);
            }
        }

        // unit = policyIdHex + assetNameHex
        $unitHex = $policyIdHex . $assetNameHex;
        $unitBytes = hex2bin($unitHex);

        // blake2b-160 (20 bytes) → requires ext-sodium
        if (!function_exists('sodium_crypto_generichash')) {
            throw new \RuntimeException("ext-sodium required.");
        }
        $hash = sodium_crypto_generichash($unitBytes, '', 20);

        // convert to 5-bit values
        $fiveBit = self::convertBits(array_values(unpack('C*', $hash)), 8, 5, true);

        // Bech32 encode with hrp "asset" and 6-char checksum
        return self::bech32Encode('asset', $fiveBit);
    }

    private static function convertBits(array $data, int $fromBits, int $toBits, bool $pad = true): array
    {
        $acc = 0;
        $bits = 0;
        $ret = [];
        $maxv = (1 << $toBits) - 1;

        foreach ($data as $value) {
            if ($value < 0 || ($value >> $fromBits)) {
                throw new \InvalidArgumentException("Invalid value while converting bits.");
            }
            $acc = ($acc << $fromBits) | $value;
            $bits += $fromBits;
            while ($bits >= $toBits) {
                $bits -= $toBits;
                $ret[] = ($acc >> $bits) & $maxv;
            }
        }

        if ($pad && $bits > 0) {
            $ret[] = ($acc << ($toBits - $bits)) & $maxv;
        } elseif ($bits >= $fromBits || (($acc << ($toBits - $bits)) & $maxv)) {
            throw new \InvalidArgumentException("Invalid padding.");
        }

        return $ret;
    }

    // ---------- Bech32 (with 6-char checksum) ----------
    private static function bech32Encode(string $hrp, array $data): string
    {
        $chk = self::createChecksum($hrp, $data);
        $combined = array_merge($data, $chk);

        $ret = $hrp . '1';
        foreach ($combined as $d) {
            $ret .= self::CHARSET[$d];
        }
        return $ret;
    }

    private static function hrpExpand(string $hrp): array
    {
        $ret = [];
        $len = strlen($hrp);
        for ($i = 0; $i < $len; $i++) {
            $ret[] = ord($hrp[$i]) >> 5;
        }
        $ret[] = 0;
        for ($i = 0; $i < $len; $i++) {
            $ret[] = ord($hrp[$i]) & 31;
        }
        return $ret;
    }

    private static function polymod(array $values): int
    {
        $GEN = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];
        $chk = 1;
        foreach ($values as $v) {
            $top = $chk >> 25;
            $chk = (($chk & 0x1ffffff) << 5) ^ $v;
            for ($i = 0; $i < 5; $i++) {
                if ((($top >> $i) & 1) !== 0) {
                    $chk ^= $GEN[$i];
                }
            }
        }
        return $chk;
    }

    private static function createChecksum(string $hrp, array $data): array
    {
        $values = array_merge(self::hrpExpand($hrp), $data, array_fill(0, 6, 0));
        $polymod = self::polymod($values) ^ 1;
        $ret = [];
        for ($i = 0; $i < 6; $i++) {
            $ret[] = ($polymod >> (5 * (5 - $i))) & 31;
        }
        return $ret;
    }

    public static function getTokenJson(string $asset_name, string $policy_id, ?string $metadata = null): ?array
    {
        $result = [];
                
        if (!empty($metadata)) {              
            $meta = json_decode($metadata, true);
            
            if ((array_key_exists($policy_id, $meta)) && !empty($meta[$policy_id])) {
                $pname = $meta[$policy_id];                

                if (array_key_exists($asset_name, $pname) && !empty($pname[$asset_name])) {
                    $fname = $pname[$asset_name];

                    if ((array_key_exists('image', $fname)) && !empty($fname['image'])) {                
                        $imageArray = $fname['image'];
                        
                        $result['image'] = implode($imageArray);
                    }

                    if ((array_key_exists('name', $fname)) && !empty($fname['name'])) {
                        $result['name'] = $fname['name'];
                    }
            
                    if ((array_key_exists('ticker', $fname)) && !empty($fname['ticker'])) {
                        $result['ticker'] = $fname['ticker'];
                    }

                    if ((array_key_exists('category', $fname)) && !empty($fname['category'])) {
                        $result['category'] = $fname['category'];
                    }

                    if ((array_key_exists('type', $fname)) && !empty($fname['type'])) {
                        $result['type'] = $fname['type'];
                    }

                    if ((array_key_exists('decimals', $fname)) && !empty($fname['decimals'])) {
                        $result['decimals'] = $fname['decimals'];                            
                    }

                    if ((array_key_exists('description', $fname)) && !empty($fname['description'])) {
                        $descriptionArray = $fname['description'];
                        
                        $result['description'] = implode((array)$descriptionArray);
                    }
                
                } else if (array_key_exists(hex2bin($asset_name), $pname) && !empty($pname[hex2bin($asset_name)])) {                    
                    $fname = $pname[hex2bin($asset_name)];

                    if ((array_key_exists('image', $fname)) && !empty($fname['image'])) {
                        $imageArray = $fname['image'];

                        $result['image'] = implode($imageArray);
                    }

                    if ((array_key_exists('name', $fname)) && !empty($fname['name'])) {
                        $result['name'] = $fname['name'];
                    }
            
                    if ((array_key_exists('ticker', $fname)) && !empty($fname['ticker'])) {
                        $result['ticker'] = $fname['ticker'];
                    }

                    if ((array_key_exists('category', $fname)) && !empty($fname['category'])) {
                        $result['category'] = $fname['category'];
                    }

                    if ((array_key_exists('type', $fname)) && !empty($fname['type'])) {
                        $result['type'] = $fname['type'];
                    }

                    if ((array_key_exists('decimals', $fname)) && !empty($fname['decimals'])) {
                        $result['decimals'] = $fname['decimals'];                            
                    }

                    if ((array_key_exists('description', $fname)) && !empty($fname['description'])) {
                        $descriptionArray = $fname['description'];
                        
                        $result['description'] = implode((array)$descriptionArray);
                    }
                }
            }                        
        }
        else {

            // Launching from a static function, cannot take this
            
            $tokenData = CardanoFingerprint::getTokendata($policy_id, $asset_name);
            
            if (!empty($tokenData)) {
                $meta = json_decode($tokenData[0]->json, true);

                if ((array_key_exists($policy_id, $meta)) && !empty($meta[$policy_id])) {
                    $pname = $meta[$policy_id];
             
                    if (array_key_exists($asset_name, $pname) && !empty($pname[$asset_name])) {
                        $fname = $pname[$asset_name];

                        if ((array_key_exists('image', $fname)) && !empty($fname['image'])) {                
                            $imageArray = $fname['image'];
                        
                            $result['image'] = implode($imageArray);
                        }

                        if ((array_key_exists('name', $fname)) && !empty($fname['name'])) {
                            $result['name'] = $fname['name'];
                        }
            
                        if ((array_key_exists('ticker', $fname)) && !empty($fname['ticker'])) {
                            $result['ticker'] = $fname['ticker'];
                        }

                        if ((array_key_exists('category', $fname)) && !empty($fname['category'])) {
                            $result['category'] = $fname['category'];
                        }

                        if ((array_key_exists('type', $fname)) && !empty($fname['type'])) {
                            $result['type'] = $fname['type'];
                        }

                        if ((array_key_exists('location', $fname)) && !empty($fname['location'])) {
                            $result['location'] = $fname['location'];
                        }

                        if ((array_key_exists('address', $fname)) && !empty($fname['address'])) {
                            $result['address'] = $fname['address'];
                        }

                        if ((array_key_exists('ip', $fname)) && !empty($fname['ip'])) {
                            $result['ip'] = $fname['ip'];
                        }

                        if ((array_key_exists('decimals', $fname)) && !empty($fname['decimals'])) {
                            $result['decimals'] = $fname['decimals'];                            
                        }   

                        if ((array_key_exists('description', $fname)) && !empty($fname['description'])) {
                            $descriptionArray = $fname['description'];

                            $result['description'] = implode((array)$descriptionArray);
                        }
                                    
                    } else if (array_key_exists(hex2bin($asset_name), $pname) && !empty($pname[hex2bin($asset_name)])) {
                        $fname = $pname[hex2bin($asset_name)];

                        if ((array_key_exists('image', $fname)) && !empty($fname['image'])) {
                            $imageArray = $fname['image'];

                            $result['image'] = implode($imageArray);
                        }

                        if ((array_key_exists('name', $fname)) && !empty($fname['name'])) {
                            $result['name'] = $fname['name'];
                        }
            
                        if ((array_key_exists('ticker', $fname)) && !empty($fname['ticker'])) {
                            $result['ticker'] = $fname['ticker'];
                        }

                        if ((array_key_exists('category', $fname)) && !empty($fname['category'])) {
                            $result['category'] = $fname['category'];
                        }

                        if ((array_key_exists('type', $fname)) && !empty($fname['type'])) {
                            $result['type'] = $fname['type'];
                        }

                        if ((array_key_exists('location', $fname)) && !empty($fname['location'])) {
                            $result['location'] = $fname['location'];
                        }

                        if ((array_key_exists('address', $fname)) && !empty($fname['address'])) {
                            $result['address'] = $fname['address'];
                        }

                        if ((array_key_exists('ip', $fname)) && !empty($fname['ip'])) {
                            $result['ip'] = $fname['ip'];
                        }

                        if ((array_key_exists('decimals', $fname)) && !empty($fname['decimals'])) {
                            $result['decimals'] = $fname['decimals'];                            
                        }

                        if ((array_key_exists('description', $fname)) && !empty($fname['description'])) {
                            $descriptionArray = $fname['description'];

                            $result['description'] = implode((array)$descriptionArray);
                        }
                    }
                }
            }
        }
            
        return $result;
    }
    
    public static function getProductJson(string $asset_name, string $policy_id, ?string $metadata = null): ?array
    {
        $result = [];
        
        if (!empty($metadata)) {              
            $meta = json_decode($metadata, true);

            $meta = reset($meta);
            
            if ((array_key_exists($policy_id, $meta)) && !empty($meta[$policy_id])) {
                $pname = $meta[$policy_id];                

                if (array_key_exists($asset_name, $pname) && !empty($pname[$asset_name])) {
                    $fname = $pname[$asset_name];

                    if ((array_key_exists('image', $fname)) && !empty($fname['image'])) {                
                        $imageArray = $fname['image'];
                        
                        $result['image'] = implode($imageArray);
                    }

                    if ((array_key_exists('name', $fname)) && !empty($fname['name'])) {
                        $result['name'] = $fname['name'];
                    }
            
                    if ((array_key_exists('ticker', $fname)) && !empty($fname['ticker'])) {
                        $result['ticker'] = $fname['ticker'];
                    }

                    if ((array_key_exists('category', $fname)) && !empty($fname['category'])) {
                        $result['category'] = $fname['category'];
                    }

                    if ((array_key_exists('type', $fname)) && !empty($fname['type'])) {
                        $result['type'] = $fname['type'];
                    }

                    if ((array_key_exists('decimals', $fname)) && !empty($fname['decimals'])) {
                        $result['decimals'] = $fname['decimals'];                            
                    }

                    if ((array_key_exists('description', $fname)) && !empty($fname['description'])) {
                        $descriptionArray = $fname['description'];
                        
                        $result['description'] = implode((array)$descriptionArray);
                    }
                
                } else if (array_key_exists(hex2bin($asset_name), $pname) && !empty($pname[hex2bin($asset_name)])) {                    
                    $fname = $pname[hex2bin($asset_name)];

                    if ((array_key_exists('image', $fname)) && !empty($fname['image'])) {
                        $imageArray = $fname['image'];

                        $result['image'] = implode($imageArray);
                    }

                    if ((array_key_exists('name', $fname)) && !empty($fname['name'])) {
                        $result['name'] = $fname['name'];
                    }
            
                    if ((array_key_exists('ticker', $fname)) && !empty($fname['ticker'])) {
                        $result['ticker'] = $fname['ticker'];
                    }

                    if ((array_key_exists('category', $fname)) && !empty($fname['category'])) {
                        $result['category'] = $fname['category'];
                    }

                    if ((array_key_exists('type', $fname)) && !empty($fname['type'])) {
                        $result['type'] = $fname['type'];
                    }

                    if ((array_key_exists('decimals', $fname)) && !empty($fname['decimals'])) {
                        $result['decimals'] = $fname['decimals'];                            
                    }

                    if ((array_key_exists('description', $fname)) && !empty($fname['description'])) {
                        $descriptionArray = $fname['description'];
                        
                        $result['description'] = implode((array)$descriptionArray);
                    }
                }
            }
        }
            
        return $result;
    }

    public static function getTokendata(string $policy_id, string $asset_name)
    {
        try {
            $response = Http::timeout(5)->get(config('chimera.cexplorer').'/cardano/tokendata', [
                'policy_id'  => $policy_id,
                'asset_name' => $asset_name,
            ]);

            if ($response->successful()) {                
                return array_map(fn($item) => (object) $item, $response->json());

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
