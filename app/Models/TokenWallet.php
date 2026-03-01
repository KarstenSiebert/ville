<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TokenWallet extends Model
{
    use SoftDeletes;

    protected $table = 'token_wallet';

    protected $fillable = [
        'wallet_id',
        'token_id',
        'quantity',
        'reserved_quantity',
        'status',        
        'metadata',
        'quantity_version'
    ];

    protected $casts = [
        'metadata' => 'json',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function token()
    {
        return $this->belongsTo(Token::class);
    }
    
    public function getAvailableAttribute()
    {           
        return max($this->quantity - ($this->reserved_quantity ?? 0), 0);
    }
     
    public function scopeForWallet($query, int $walletId)
    {
        return $query->where('token_wallet.wallet_id', $walletId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->whereHas('wallet', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }

    public function scopeWithQuantity($query)
    {
        return $query->where('token_wallet.quantity', '>', 0);
    }

    public function scopeWithActiveToken($query)
    {
        return $query->whereHas('token', function ($q) {
            $q->where('tokens.status', 'active');
        });
    }

    public function scopeLoadTokenData($query)
    {
        return $query->with([
            'token' => function ($q) {
                $q->select(
                    'id',
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
                    'status',                    
                );
            }
        ]);
    }
    
}
