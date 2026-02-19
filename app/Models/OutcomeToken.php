<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutcomeToken extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'outcome_id', 
        'token_id'        
    ];

    public function token()
    {
        return $this->belongsTo(Token::class);
    }

    public function tokenWallet()
    {
        return $this->hasOne(TokenWallet::class, 'token_id', 'token_id')->where('status', 'active');
    }
    
}
