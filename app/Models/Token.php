<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Token extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'user_id',
        'name',
        'name_hex',
        'policy_id',
        'fingerprint', 
        'decimals', 
        'logo_url',
        'metadata',
        'token_type',
        'supply',
        'description',
        'status'        
    ];

    protected $casts = [
        'metadata' => 'json',
    ];

    protected $appends = ['minPrice'];

    public function getMinPriceAttribute()
    {    
        return 1 / pow(10, $this->decimals);        
    }

    public function wallets()
    {
        return $this->belongsToMany(Wallet::class, 'token_wallet')
                    ->withPivot('quantity', 'quantity_version')
                    ->withTimestamps();
    }

    public function tokenWallets()
    {
        return $this->hasMany(TokenWallet::class);
    }

    public function outcomes()
    {
        return $this->belongsToMany(Outcome::class, 'outcome_tokens');
    }

    public function markets()
    {
        return $this->hasMany(Market::class, 'base_token_fingerprint', 'fingerprint');
    }
    
}
