<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'user_id',
        'transaction_id',
        'transaction_fee',
        'is_confirmed',
        'direction'
    ];

    protected $hidden = [
        'user_id',
        'transaction_id',
        'transaction_fee'
    ];

    protected $casts = [
        'transaction_fee' => 'decimal:6',
        'is_confirmed' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
}
