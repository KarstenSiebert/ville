<?php

namespace App\Helpers;

use App\Models\Wallet;

class ContainerMetadata
{
    public function __construct()
    {
    }

    public function generateContainerMetaData(array $data, string $address, string $service, string $direction): ?string
    {        
        $addr = substr(trim(config('chimera.head_id')), 0, 64);
                
        $adminId = '20823';

        $reservedWallets = [];

        if ($direction == 'outbound') {

            foreach ($data as $d) {
                $walletId = $d['wallet_id'];

                if (!isset($reservedWallets[$walletId])) {
                    $reservedWallets[$walletId] = [];
                }

                $reservedWallets[$walletId][] = [
                    't' => strval($d['transfer_id']) ?? ''                
                ];
            }
                    
        } else {

            $wallet_id = Wallet::where('address', 'ava'.$address)->where('type', 'available')->value('id');

            foreach ($data as $d) {
                $walletId = !empty($wallet_id) ? $wallet_id : 0;

                if (!isset($reservedWallets[$walletId])) {
                    $reservedWallets[$walletId] = [];
                }

                $reservedWallets[$walletId][] = [
                    'n' => strval($d['asset_name']) ?? '',
                    'a' => strval($d['token_number']) ?? '',
                ];
            }
        }

        $metaDataArray = [
            $adminId => [
                'head' => $addr,
                'body' => $reservedWallets,
            ]
        ];
                
        $metaData = json_encode($metaDataArray, JSON_PRETTY_PRINT);

        return !empty($addr) ? $metaData : '';
    }

}