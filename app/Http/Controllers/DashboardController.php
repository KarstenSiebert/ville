<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\User;
use App\Models\Market;
use App\Models\Wallet;
use App\Models\Transfer;
use App\Models\TokenWallet;
use Illuminate\Http\Request;
use App\Helpers\CardanoCliWrapper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Http\Services\OutcomeService;
use Illuminate\Pagination\LengthAwarePaginator;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $search = $request->input('search', null);
    
        $page = (int) $request->input('page', 1);
    
        $perPage = 9;
        
        $markets = Market::with(['outcomes', 'baseToken', 'wallet.tokenWallets'])
            // ->whereNull('publisher_id')
            ->orderBy('created_at', 'desc')
            // ->running()
            ->limit(90)
            ->get()
            ->map(function ($market) {        
                  
                $marketWallet = $market->wallet;
                $baseTokenWallet = $marketWallet->tokenWallets->firstWhere('token_id', $market->baseToken->id);
                        
                // $market->currentLiquidity = $baseTokenWallet ? $baseTokenWallet->quantity : 0;

                $liquidity = app(OutcomeService::class)->getLiquidity($market);
          
                return [
                    'id' => $market->id,
                    'title' => $market->title,
                    'description' => $market->description,
                    'logo_url' => $market->logo_url,
                    'status' => $market->status,
                    'category' => $market->category,
                    'close_time' => $market->close_time,
                    'currentLiquidity' => $market->currentLiquidity,
                    'b' => $market->b,
                    'base_token' => [
                        'name' => $market->baseToken->name,
                        'decimals' => $market->baseToken->decimals,
                        'logo_url' => $market->baseToken->logo_url,
                        'minPrice' => $market->baseToken->minPrice,
                    ],
                    'outcomes' => $market->outcomes->map(function ($o) {
                        return [
                            'id' => $o->id,
                            'name' => $o->name,
                            'link' => $o->link,
                        ];
                    }),
                ];
                
            });   
            
        if (!empty($search)) {   
            $searchTerms = preg_split('/\+/', strtolower(trim($search)));

            $filtered = $markets->filter(function($item) use ($searchTerms) {
            
                $title = strtolower($item->title ?? '');
                                                
                return collect($searchTerms)->contains(fn($term) =>
                    str_contains($title, $term));
            });
        
        } else {
            $filtered = $markets;
        }
        
        // dd($markets);

        if ($filtered->count()) {

            $paginated = new LengthAwarePaginator(
                    $filtered->forPage($page, $perPage)->values(),
                    $filtered->count(),
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'query' => $request->query()]
                );
        
        } else {
            $paginated = new LengthAwarePaginator([], 0, $perPage, $page);            
        }
            
        return Inertia::render('Dashboard', [            
            'markets' => [
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
            ]
        ]);
    }
 
}

