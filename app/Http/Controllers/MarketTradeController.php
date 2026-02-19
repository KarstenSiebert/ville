<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use Carbon\Carbon;
use App\Models\User;
use Inertia\Inertia;
use App\Models\MarketTrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MarketTradeController extends Controller
{
     use AuthorizesRequests;
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
             
        $search = $request->input('search', null);

        $page = (int) $request->input('page', 1);
    
        $perPage = 10;
        
        if ($user !== null) {

            $trades = MarketTrade::with(['market.token', 'outcome'])
                ->when(!$user->hasRole('admin'), function ($query) use ($user) {
                    $query->where('user_id', $user->id);                    
                })                
                ->whereHas('market')
                ->whereHas('outcome')
                ->orderByDesc('created_at')
                ->limit(500)
                ->get()
                ->map(function ($trade) {

                    $decimals = $trade->market->token?->decimals ?? 0;
                    $divisor = bcpow('10', (string)$decimals);

                    $trade->price_real = bcdiv($trade->price_paid, $divisor, $decimals);                    

                    return [
                        'id'           => $trade->id,
                        'tx_type'      => $trade->tx_type,
                        'share_amount' => $trade->share_amount,
                        'price_paid'   => $trade->finalPriceReal,
                        'fee'          => 0,
                        'outcome'      => $trade->outcome?->name,

                        'market' => [
                            'id'    => $trade->market->id,
                            'title' => $trade->market->title,
                            'logo'  => $trade->outcome?->logo_url ? $trade->outcome?->logo_url : $trade->market->logo_url,
                        ],
                        'token' => [
                            'name'     => $trade->market->token?->name,
                            'decimals' => $decimals,
                        ],
            
                        'created_at' => $trade->created_at->toDateTimeString(),
                    ];
                });
                     
            // dd($trades);

            $paginated = new LengthAwarePaginator(
                $trades->forPage($page, $perPage)->values(),
                $trades->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => $request->query()]
            );            
        }

        if (empty($paginated)) {
            $paginated = new LengthAwarePaginator([], 0, $perPage, $page);
        } 

        return Inertia::render('trades/Trades', [
            'trades' => [
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
            ],
        ]);    
    }
  
}
