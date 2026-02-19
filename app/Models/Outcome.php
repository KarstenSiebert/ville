<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Outcome extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 
        'wallet_id',
        'market_id',
        'name',
        'link',
        'logo_url'
    ];
    
    protected $dates = ['deleted_at'];

    public function market()
    {
        return $this->belongsTo(Market::class);
    }

    public function tokens()
    {
        return $this->belongsToMany(Token::class, 'outcome_tokens', 'outcome_id', 'token_id');
    }

    public function outcomeToken()
    {
        return $this->hasOne(OutcomeToken::class);
    }
    
}
