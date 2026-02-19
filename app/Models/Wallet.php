<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wallet extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'parent_wallet_id', 
        'address', 
        'last_checked_at',      
        'type',
        'user_type',
    ];
    
    protected $hidden = [
        'address', 
        'parent_wallet_id',
        'last_checked_at', 
        'type',
        'user_type',
    ];

    protected $with = ['user'];

    protected $casts = [
        'last_checked_at' => 'datetime',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parentUserWallets()
    {
        return $this->hasManyThrough(Wallet::class, User::class, 'id', 'user_id', 'user_id', 'parent_user_id');
    }

    public function parentAvailableWallet()
    {
        return $this->parentUserWallets()->where('wallets.type', 'available')->first();
    }
    
    public function markets()
    {
        return $this->hasMany(Market::class);
    }

    public function subWallets()
    {
        return $this->hasMany(Wallet::class, 'parent_wallet_id');
    }

    public function parentWallet()
    {
        return $this->belongsTo(Wallet::class, 'parent_wallet_id');
    }

    public function tokenWallets()
    {
        return $this->hasMany(TokenWallet::class, 'wallet_id');
    }

    public function tokens()
    {
        return $this->belongsToMany(Token::class, 'token_wallet', 'wallet_id', 'token_id')
            ->withPivot('quantity', 'reserved_quantity', 'quantity_version')
            ->withTimestamps();
    }

    public function transfersFrom()
    {
        return $this->hasMany(Transfer::class, 'from_wallet_id');
    }

    public function transfersTo()
    {
        return $this->hasMany(Transfer::class, 'to_wallet_id');
    }
        
    public function trades()
    {
        return $this->hasManyThrough(
            MarketTrade::class,
            Market::class,
            'wallet_id',
            'market_id',
            'id',
            'id'
        );
    }

}
