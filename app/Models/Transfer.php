<?php

namespace App\Models;

use DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transfer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type',
        'from_wallet_id',
        'to_wallet_id',
        'token_id',
        'quantity',
        'status',
        'tx_hash',
        'fee',
        'note',
        'receiver_address'
    ];

    protected $with = ['fromWallet.user', 'toWallet.user'];
   
    public function token()
    {
        return $this->belongsTo(Token::class);
    }

    public function fromWallet()
    {
        return $this->belongsTo(Wallet::class, 'from_wallet_id');
    }

    public function toWallet()
    {
        return $this->belongsTo(Wallet::class, 'to_wallet_id');
    }

    /**
     * Führt einen Transfer aus und passt die Token-Mengen an.
     */
    public static function execute($fromWallet, $toWallet, $token, $quantity, $type = 'internal', $fee = 0, $note = null, $systemInitiated = false, $receiver_address = null)
    {
        return DB::transaction(function () use ($fromWallet, $toWallet, $token, $quantity, $type, $fee, $note, $systemInitiated, $receiver_address) {
            
            // Validate fromWallet for internal transfers
            
            if ($fromWallet) {
                $fromPivot = $fromWallet->tokens()->where('token_id', $token->id)->first();                
                                
                $fromBalance = $fromPivot ? max($fromPivot->pivot->quantity - $fromPivot->pivot->reserved_quantity, 0) : 0;

                $fromVersion = $fromPivot?->pivot->quantity_version ?? 0;

                if ($type === 'internal' || $type === 'onchain_out') {
                    
                    if (($fromWallet->type === 'reserved') && !$systemInitiated) {
                        throw new \Exception('Transfers from reserved wallets are blocked.');
                    }

                    if ($type === 'internal' && $fromBalance < $quantity + $fee) {                                         
                        throw new \Exception('Nicht genug Token in der Absender-Wallet.');
                    }
                }
            }

            // Admin has no family, this is hard coded
            
            $transfer = self::create([
                'type' => $type,
                'from_wallet_id' => $fromWallet?->id,
                'to_wallet_id' => $toWallet?->id,
                'token_id' => $token->id,
                'quantity' => $quantity,
                'fee' => $fee,
                'status' => ($type === 'onchain_out') ? 'pending' : 'completed',
                'note' => $note,
                'receiver_address' => $receiver_address
            ]);

            // Update balances immediately for internal and onchain_in transfers

            if ($type === 'internal' || $type === 'onchain_in') {
                              
                if ($fromWallet) {
                    $fromWallet->tokens()->updateExistingPivot($token->id, [
                        'quantity' => $fromPivot->pivot->quantity - $quantity - $fee,
                        'quantity_version' => $fromVersion + 1
                    ]);                
                }

                if ($toWallet) {
                    $toPivot = $toWallet->tokens()->where('token_id', $token->id)->first();
                    $toBalance = $toPivot?->pivot->quantity ?? 0;
                    $toVersion = $toPivot?->pivot->quantity_version ?? 0;

                    if ($toPivot) {
                        $toWallet->tokens()->updateExistingPivot($token->id, [
                            'quantity' => $toBalance + $quantity,
                            'quantity_version' => $toVersion + 1
                        ]);
                
                    } else {
                        $toWallet->tokens()->attach($token->id, [
                            'quantity' => $quantity,
                            'quantity_version' => 1
                        ]);
                    }
                }
            }            

            return $transfer;
        });
    }

    protected static function booted()
    {
        static::creating(function ($transfer) {
            $transfer->tx_hash = bin2hex(openssl_random_pseudo_bytes(32));
        });
    }

}
