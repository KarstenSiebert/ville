<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class VaultService
{    
    public function __construct()
    {     
    }

    /**
     * Fetch the rootkey from Vault
     *
     * @return string|null
     */
    public static function getRootKey(): ?string
    {        
        return base64_decode(Storage::disk('transactions')->get('root.key'));
    }

    public static function verifyString(User $user, string $message, string $signature = null): bool
    {        
        if (!$user?->public_key || !$signature || !$message) return false;
        
        return openssl_verify($message, base64_decode($signature), $user->public_key, OPENSSL_ALGO_SHA256);
    }
    
}