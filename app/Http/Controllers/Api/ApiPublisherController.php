<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Token;
use App\Models\Wallet;
use App\Models\Transfer;
use App\Models\TokenWallet;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ApiPublisherController extends Controller
{    
    public function wallet(Request $request)
    {   
        $publisher = $request->publisher;
  
        $users = User::with([
                'avaWallet:id,user_id,address',
                'avaWallet.tokenWallets' => function ($query) {
                    $query->where('status', 'active')
                        ->where('quantity', '>', 0);
                },
                'avaWallet.tokenWallets.token:id,name,decimals'
            ])
            ->where('id', $publisher->user->id)
            ->limit(1)
            ->get()
            ->map(function ($user) {

                return [                    
                    'name' => $user->name,

                    'wallet' => [          
                        'address' => substr($user->avaWallet->address, 3),

                        'balances' => optional($user->avaWallet)->tokenWallets                            
                            ->map(function ($tokenWallet) {

                                $token = $tokenWallet->token;

                                $quantity = bcdiv($tokenWallet->quantity, bcpow("10", (string) $token->decimals), $token->decimals);
                                $reserved = bcdiv($tokenWallet->reserved_quantity, bcpow("10", (string) $token->decimals), $token->decimals) ?? 0;

                                return [                                  
                                    'token_name' => optional($tokenWallet->token)->name,
                                    'quantity' => $quantity,
                                    'reserved_quantity' => $reserved,
                                ];
                            })
                            ->values()
                    ]
                ];
            });

        return response()->json($users);
    }

    public function transfer(Request $request)
    {           
        $publisher = $request->publisher;

        $token = $request->input('token') ?? null;

        $value = $request->input('value') ?? null;
  
        $suser = $request->shadow_user;
                
        $fromWallet = Wallet::where('user_id', $publisher->user->id)->where('type', 'available')->first();

        $toWallet = Wallet::where('user_id', $suser->id)->where('type', 'available')->first();
        
        $pubToken = Token::where('name', $token)->first();

        $pubTokenWallet = TokenWallet::where('wallet_id', $fromWallet->id)->where('token_id', $pubToken->id)->first();
        
        $quantity = $pubTokenWallet->quantity;

        $reserved = $pubTokenWallet->reserved_quantity;

        $realValue = bcmul($value, bcpow("10", (string) $pubToken->decimals));
        
        $available = max($quantity - $reserved, 0);

        if ($realValue > $available) {
            return response()->json(['error' => 'Fehler']);
        }
            
        // Check, if publisher as enogh tokens, token value is provided before decimals
        
        if (!empty($fromWallet) && !empty($toWallet) && !empty($pubToken)) {
            
            Transfer::execute($fromWallet, $toWallet, $pubToken, $realValue, 'internal', 0, 'PTOU', false);

            $tokenWallet = TokenWallet::where('wallet_id', $toWallet->id)->where('token_id', $pubToken->id)->first();

            $quantity = bcdiv($tokenWallet->quantity, bcpow("10", (string) $pubToken->decimals), $pubToken->decimals);

            return response()->json(['success' => $quantity]);
        }

        return response()->json();
    }

    public function chargeback(Request $request)
    {           
        $publisher = $request->publisher;

        $token = $request->input('token') ?? null;

        $value = $request->input('value') ?? null;
  
        $suser = $request->shadow_user;
                
        $toWallet = Wallet::where('user_id', $publisher->user->id)->where('type', 'available')->first();

        $fromWallet = Wallet::where('user_id', $suser->id)->where('type', 'available')->first();
        
        $pubToken = Token::where('name', $token)->first();

        $pubTokenWallet = TokenWallet::where('wallet_id', $fromWallet->id)->where('token_id', $pubToken->id)->first();
        
        $quantity = $pubTokenWallet->quantity;

        $reserved = $pubTokenWallet->reserved_quantity;

        $realValue = bcmul($value, bcpow("10", (string) $pubToken->decimals));
        
        $available = max($quantity - $reserved, 0);

        if ($realValue > $available) {
            return response()->json(['error' => 'Fehler']);
        }
            
        // Check, if user as enogh tokens, token value is provided before decimals
        
        if (!empty($fromWallet) && !empty($toWallet) && !empty($pubToken)) {
            
            Transfer::execute($fromWallet, $toWallet, $pubToken, $realValue, 'internal', 0, 'UTOP', false);

            $tokenWallet = TokenWallet::where('wallet_id', $toWallet->id)->where('token_id', $pubToken->id)->first();

            $quantity = bcdiv($tokenWallet->quantity, bcpow("10", (string) $pubToken->decimals), $pubToken->decimals);

            return response()->json(['success' => $quantity]);
        }

        return response()->json();
    }
        
}
