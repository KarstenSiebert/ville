<?php

namespace App\Http\Controllers;

use DB;
use Storage;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Token;
use App\Models\Wallet;
use Illuminate\Http\Request;
use App\Services\UserIdentifier;
use App\Services\HeadIdService;
use Illuminate\Pagination\LengthAwarePaginator;

class UserController extends Controller
{
    /*
    ** List all users accounts (type: available) for a specific token
    **
     */
    public function index(Request $request) 
    {
        $search = $request->input('search', null);
        
        $page = (int) $request->input('page', 1);
    
        $perPage = 10;        

        $totalTokens = 0;

        $user = auth()->user();

        if ($user->email !== config('chimera.admin_user')) {
            return redirect('deposits');
        }

        $fingerprint = $request->input('f', null);

        if ($fingerprint == 'ADA') {
            $token = Token::where('name', $fingerprint)->first();

        } else {
            $token = Token::where('fingerprint', $fingerprint)->first();
        }
        
        if (!empty($token)) {
            $decimals = !empty($token->decimals) ? $token->decimals : 0;
            $logo_url = !empty($token->logo_url) ? $token->logo_url : null;
            $tok_name = !empty($token->name) ? $token->name : '';
                   
            $holders = User::whereHas('wallets', function ($q) use ($token) {
                    $q->whereIn('type', ['available', 'reserved'])
                      ->whereHas('tokenWallets', function ($q2) use ($token) {
                            $q2->where('token_id', $token->id);
                        });
                    })
                    ->with(['wallets' => function ($q) use ($token) {
                        $q->whereIn('type', ['available', 'reserved'])
                            ->with(['tokenWallets' => function ($q2) use ($token) {
                                $q2->where('token_id', $token->id);
                        }]);
                    }])
                ->limit(500)
                ->get()
                ->map(function ($user) {
                    $total = $user->wallets->sum(function ($wallet) {
                        return $wallet->tokenWallets->sum('quantity');
                    });

                    $reserved = $user->wallets->sum(function ($wallet) {
                        return $wallet->tokenWallets->sum('reserved_quantity');
                    });
                    
                    $avatar = $user->profile_photo_path;

                    if (str_starts_with($avatar, '/storage')) {
                        $avatar = substr($avatar, 8);
                    }

                    return [
                        'user_id'      => $user->id,
                        'name'         => $user->name,
                        'email'        => $user->email,
                        'type'         => $user->type,
                        'avatar'       => !empty($avatar) ? Storage::url($avatar) : null,
                        'total_owned'  => $total,
                        'reserved'     => $reserved,
                    ];
                })
                ->filter(fn($item) => $item['total_owned'] > 0)
                ->sortByDesc('total_owned')
                ->values();                
            
            if ($holders) {

                $searchTerms = preg_split('/\+/', strtolower(trim($search)));

                $filteredHolders = $holders->filter(function($holder) use ($searchTerms) {

                    if (!$searchTerms) return true;

                    $name  = strtolower($holder['name']);
                    $email = strtolower($holder['email']);
                    $type = strtolower($holder['type']);
                    
                    return collect($searchTerms)->contains(fn($term) =>
                        str_contains($name, $term) || str_contains($email, $term) || str_contains($type, $term)
                    );
                });

                foreach($filteredHolders as $holder) {
                    $totalTokens += $holder['total_owned'];
                }

                $paginated = new LengthAwarePaginator(
                    $filteredHolders->forPage($page, $perPage)->values(),
                    $filteredHolders->count(),
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'query' => $request->query()]
                );
            }

        } else {
            $paginated = new LengthAwarePaginator([], 0, $perPage, $page);
        }
        
        return Inertia::render('users/Users', [
            'users' => [
                'data' => $paginated->items(),
                'links' => $paginated->linkCollection()->map(function($link){
                    if ($link['label'] === '&laquo; Previous') $link['label'] = 'Prev';
                    if ($link['label'] === 'Next &raquo;') $link['label'] = 'Next';
                    return $link;
                }),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
                'tok_name' => !empty($tok_name) ? $tok_name : '',
                'decimals' => !empty($decimals) ? $decimals : 0,   
                'total_tokens' => $totalTokens,
                'fingerprint' => $fingerprint             
            ]
        ]);
    }

    public function outgoings(Request $request)
    {
        $search = $request->input('search', null);
        
        $page = (int) $request->input('page', 1);
    
        $perPage = 10;

        $totalTokens = 0;
        
        $user = auth()->user();

        if ($user->email !== config('chimera.admin_user')) {
            return redirect('deposits');
        }

        $isAdmin = $user->hasRole('admin');

        $users = DB::table('wallets as w')
            ->join('users as u', 'u.id', '=', 'w.user_id')
            ->join('token_wallet as tw', 'tw.wallet_id', '=', 'w.id')
            ->join('tokens as t', 't.id', '=', 'tw.token_id')
            ->select(
                'w.id as wallet_id',
                'w.address as wallet_address',
                'u.id as user_id',
                'u.name as user_name',
                'u.payout as payout',
                DB::raw("json_agg(json_build_object(
                    'token_id', t.id,
                    'token_name', t.name,
                    'decimals', t.decimals,
                    'quantity', tw.quantity
                )) as tokens")
            )
            ->where('w.type', 'reserved')
            ->where('tw.quantity', '>', 0)
            ->when(!$user->hasRole('admin'), function ($query) use ($user) {
                $query->where('u.id', $user->id);
            })
            ->groupBy('w.id', 'w.address', 'u.id', 'u.name', 'u.payout')
            ->orderBy('u.id')
            ->orderBy('w.id')
            ->get();
            
        if (!empty($users)) {            
            $searchTerms = preg_split('/\+/', strtolower(trim($search)));

            $filteredUsers = $users->filter(function($user) use ($searchTerms, $isAdmin) {

                if (!$searchTerms || !$isAdmin) return true;
                
                $user_name = strtolower($user->user_name);
                $payout = strtolower($user->payout);
                    
                return collect($searchTerms)->contains(fn($term) =>
                    str_contains($user_name, $term) || str_contains($payout, $term)
                );                
            });

            $mappedUsers = $filteredUsers->map(function ($u) use ($isAdmin) {
                $userArray = [
                    'wallet_id' => $u->wallet_id,
                    'wallet_address' => $u->wallet_address,
                    'user_id' => $u->user_id,
                    'payout' => $u->payout,
                    'tokens' => $u->tokens,
                ];

                if ($isAdmin) {
                    $userArray['user_name'] = $u->user_name;
                }

                return $userArray;
            });
                
            $paginated = new LengthAwarePaginator(
                $mappedUsers->forPage($page, $perPage)->values(),
                $mappedUsers->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => $request->query()]
            );

        } else {
            $paginated = new LengthAwarePaginator([], 0, $perPage, $page);
        }

        return Inertia::render('users/Outgoings', [
            'users' => [
                'data' => $paginated->items(),
                'links' => $paginated->linkCollection()->map(function($link){
                    if ($link['label'] === '&laquo; Previous') $link['label'] = 'Prev';
                    if ($link['label'] === 'Next &raquo;') $link['label'] = 'Next';
                    return $link;
                }),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ]                
            ],
            'is_admin' => $isAdmin,
        ]);
    }

    public function lookup(string $worldId)
    {
        $id = null;

        try {
            $id = UserIdentifier::parse($worldId);
    
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        
        if (empty($id)) {
            return response()->json(['status' => 'not_found']);
        }

        if (($id->headId === config('chimera.head_id')) && ($user = User::where('email', $id->email)->first())) {
            return response()->json(['status' => 'found', 'world_id' => $worldId]);
    
        } else {        
            return response()->json(['status' => 'not_found']);
        }
    }


}
