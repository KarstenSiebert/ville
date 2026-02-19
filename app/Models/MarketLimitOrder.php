<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Http\Services\LimitOrderMatchingService;

class MarketLimitOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'market_id', 
        'outcome_id',
        'type',
        'limit_price',
        'share_amount',        
        'filled',
        'spent_amount',
        'valid_until',
        'status'
    ];

    protected $casts = [
        'valid_until' => 'datetime',
    ];

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

    protected static function booted()
    {    
        static::created(function (MarketLimitOrder $order) {

            DB::afterCommit(function () use ($order) {
                LimitOrderMatchingService::match($order);
            });
        });
    }    

}
