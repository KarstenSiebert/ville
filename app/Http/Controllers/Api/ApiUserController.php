<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ApiUserController extends Controller
{
    public function index(Request $request)
    {            
        $name = $request->input('external_user_id') ?? null;

        $publisher = $request->publisher;
  
        $users = User::with([
                'avaWallet:id,user_id,address',
                'avaWallet.tokenWallets' => function ($query) {
                    $query->where('status', 'active')
                        ->where('quantity', '>', 0);
                },
                'avaWallet.tokenWallets.token:id,name,decimals'
            ])
            ->where('publisher_id', $publisher->id)
            ->whereNot('id', $publisher->user->id)
            ->when($name, function ($query) use ($name) {
                $query->where('name', $name);
            })
            ->orderBy('created_at', 'desc')
            ->limit(1000)
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

    public function wallet(Request $request)
    {                
        return $this->index($request);
    }

}
