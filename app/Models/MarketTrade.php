<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketTrade extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'market_id',
        'user_id',
        'outcome_id',
        'share_amount',
        'price_paid',
        'price_numerator',
        'price_denominator',
        'tx_type',
        'tx_hash',
        'cancelled_trade_id'
    ];

    public function cancelledTrade()
    {
        return $this->belongsTo(MarketTrade::class, 'cancelled_trade_id');
    }

    public function originalTrades()
    {
        return $this->hasMany(MarketTrade::class, 'cancelled_trade_id');
    }

    public function market()
    {
        return $this->belongsTo(Market::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function outcome()
    {
        return $this->belongsTo(Outcome::class);
    }

    // Hash will be automatically created and appended upon creation of the market trade
    protected static function booted()
    {
        static::creating(function ($trade) {
            $data = $trade->only([
                'market_id',
                'user_id',
                'outcome_id',
                'share_amount',
                'price_paid',
                'price_numerator',
                'price_denominator',
                'tx_type'            
            ]);

            ksort($data);

            $trade->tx_hash = hash('sha256', json_encode($data));
        });
    }

    public function getPriceRealAttribute()
    {
        $decimals = $this->market?->token?->decimals ?? 0;
        return bcdiv($this->price_paid, bcpow('10', $decimals), $decimals);
    }

    public function getFinalPriceAttribute()
    {
        return $this->price_denominator ? $this->price_numerator / $this->price_denominator : $this->price_paid;
    }

    public function getFinalPriceRealAttribute()
    {
        $decimals = $this->market?->token?->decimals ?? 0;

        $finalPrice = $this->price_denominator ? $this->price_numerator / $this->price_denominator : $this->price_paid;
        
        if ($decimals) {
            return bcdiv((string) $finalPrice, bcpow('10', $decimals), (string) $decimals);
        
        } else {
            return $finalPrice;
        }
    }

}
