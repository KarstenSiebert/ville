<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WalletReconciliation extends Model
{
    use SoftDeletes;

     protected $fillable = [
        'transfer_id',
        'wallet_id',
        'token_id',
        'quantity_before',
        'quantity_after',
        'change',
        'tx_hash',
        'note'
    ];
}
