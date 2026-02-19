<?php

namespace App\Http\Controllers;

use DB;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Wallet;
use App\Models\History;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;

class HistoryController extends Controller
{
    public function index(Request $request)
    {        
        $search = $request->input('search', null);
    
        $page = (int) $request->input('page', 1);
    
        $perPage = 10;

        $user = auth()->user();
         
        if (!$user->hasRole('admin')) {
            return redirect('dashboard');
        }

        $address = Wallet::where('user_id', $user->id)->where('type', 'deposit')->value('address');
          
        $items = Cache::tags(['user:' . $user->id])->remember('history', 180, function () use ($address) {            
            return collect($this->getHistory($address));
        });
        
        if (!empty($search)) {   
            $searchTerms = preg_split('/\+/', strtolower(trim($search)));

            $filtered = $items->filter(function($item) use ($searchTerms) {

                $tx_hash   = strtolower($item->tx_hash ?? '');                
                $incoming  = strtolower($item->incoming_amount ?? '');
                $outgoing  = strtolower($item->outgoing_amount ?? '');
                $balance   = strtolower($item->balance_change ?? '');
                $timestamp = strtolower($item->timestamp ?? '');
                $direction = strtolower($item->direction ?? '');
                                                
                return collect($searchTerms)->contains(fn($term) =>
                    str_contains($tx_hash, $term) || str_contains($incoming, $term) || str_contains($outgoing, $term) || str_contains($direction, $term) || str_contains($balance, $term) || str_contains($timestamp, $term));
            });
        
        } else {
            $filtered = $items;
        }
        
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
        
        return Inertia::render('history/History', [
            'history' => [
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

    private function getHistory(string $wallet)
    {
        try {
            $response = Http::timeout(5)->get(config('chimera.cexplorer').'/cardano/history', [
                'address' => $wallet,
            ]);

            if ($response->successful()) {
                return array_map(fn($item) => (object) $item, $response->json());

            } else {
                Log::error('History API failed: '.$response->body());
                return [];
            }

        } catch (\Exception $e) {
            Log::error('History API exception: '.$e->getMessage());
            return [];
        }
    }


}
