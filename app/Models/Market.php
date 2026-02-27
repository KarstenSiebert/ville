<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class Market extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'publisher_id',
        'title', 
        'description',
        'logo_url',
        'download',
        'images',
        'status',
        'category',
        'start_time',
        'close_time', 
        'liquidity_b',
        'b',
        'max_subscribers',
        'base_token_fingerprint',
        'token_ratio',
        'resolved_at',
        'settled_at',
        'canceled_at',
        'cancel_reason',
        'winning_outcome_id',
        'resolved_outcome_id',
        'min_trade_amount',
        'max_trade_amount',
        'latitude',
        'longitude',
        'is_active',
        'allow_limit_orders'
    ];

    protected $hidden = [
        'user_id',
        'wallet_id',
        'winning_outcome_id',
        'resolved_outcome_id',
        'resolved_at',
        'settled_at',
        'canceled_at',
        'cancel_reason',
        'min_trade_amount',        
        'base_token_fingerprint',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected $dates = ['deleted_at', 'canceled_at', 'start_time', 'close_time'];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'is_active' => 'boolean',
            'allow_limit_orders' => 'boolean',
        ];
    }
    
    protected $appends = ['is_active'];
    
    public function getIsActiveAttribute(): bool
    {
        $now = now();

        if (!$this->start_time) {
            return false;
        }

        if (!$this->close_time) {
            return $now->greaterThanOrEqualTo($this->start_time);
        }

        return $now->between($this->start_time, $this->close_time);
    }
   
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotNull('markets.start_time')
                ->where('markets.start_time', '<=', now())
                ->where(function ($q) {
                    $q->whereNull('markets.close_time')
                        ->orWhere('markets.close_time', '>=', now());
            });
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_time', '>', now());
    }

    public function scopeRunning($query)
    {
        return $query->where('start_time', '<=', now())
                ->where(function ($q) {
                    $q->whereNull('close_time')
                        ->orWhere('close_time', '>=', now());
            });
    }

    public function scopeClosed($query)
    {
        return $query->whereNotNull('close_time')->where('close_time', '<', now());
    }

    public function baseToken()
    {
        return $this->hasOne(Token::class, 'fingerprint', 'base_token_fingerprint');
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function user()
    {
        return $this->hasOne(User::class, 'parent_user_id');
    }

    public function outcomes()
    {
        return $this->hasMany(Outcome::class, 'market_id');
    }

    public function resolvedOutcome()
    {
        return $this->belongsTo(Outcome::class, 'resolved_outcome_id');
    }

    public function winningOutcome()
    {
        return $this->belongsTo(Outcome::class, 'winning_outcome_id');
    }

    protected static function booted()
    {
        static::deleting(function ($market) {
            if ($market->isForceDeleting()) return;

            $market->outcomes()->delete();
        });
    }

    public function trades() {
        return $this->hasMany(MarketTrade::class);
    }

    public function token() {
        return $this->belongsTo(Token::class, 'base_token_fingerprint', 'fingerprint');
    }
    
    public function limitOrders()
    {
        return $this->hasMany(MarketLimitOrder::class, 'market_id');
    }

    public function limitOrdersForOutcome($outcomeId)
    {
        return $this->hasMany(MarketLimitOrder::class, 'market_id')->where('outcome_id', $outcomeId);
    }

    public function subscribers()
    {
        return $this->belongsToMany(User::class, 'market_subscribers', 'market_id', 'user_id')->whereNull('users.deleted_at');
    }

}
