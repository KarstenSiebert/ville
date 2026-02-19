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
    
}