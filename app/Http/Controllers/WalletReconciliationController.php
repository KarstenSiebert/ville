<?php

namespace App\Http\Controllers;

use DB;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Token;
use App\Models\Wallet;
use App\Models\History;
use App\Models\Transfer;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Helpers\ImageStorage;
use App\Helpers\CardanoFingerprint;
use Illuminate\Pagination\LengthAwarePaginator;

class WalletReconciliationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search', null);
    
        $page = (int) $request->input('page', 1);
    
        $perPage = 10;

        $user = auth()->user();

        if ($user->hasRole('user')) {
            return redirect('deposits');
        }
                          
        $sql = "SELECT 
            r.id,
            r.transfer_id,
            r.wallet_id,
            t.name AS asset_name,
            t.logo_url,
            t.decimals,
            t.fingerprint,
            r.quantity_before,
            r.quantity_after,
            r.change,
            r.tx_hash,
            r.note,
            r.created_at
        FROM wallet_reconciliations AS r        
        JOIN tokens AS t ON t.id = r.token_id
        ORDER BY r.created_at DESC, r.transfer_id DESC LIMIT 500";

        $items = DB::select($sql);
        
        $items = collect($items)->map(function ($item) {
    
            if (!str_starts_with($item->logo_url, 'https') && !str_starts_with($item->logo_url, '/storage')) {
                $item->logo_url = ImageStorage::saveBase64Image($item->logo_url, trim($item->asset_name));
            }
        
            return $item;
        });
        
        if (!empty($search)) {   
            $searchTerms = preg_split('/\+/', strtolower(trim($search)));

            $filtered = $items->filter(function($item) use ($searchTerms) {

                $transfer_id = strtolower($item->transfer_id ?? '');
                $incoming    = strtolower($item->incoming_amount ?? '');
                $outgoing    = strtolower($item->outgoing_amount ?? '');
                $balance     = strtolower($item->balance_change ?? '');
                $asset_name  = strtolower($item->asset_name ?? '');                
                $timestamp   = strtolower($item->timestamp ?? '');
                $direction   = strtolower($item->direction ?? '');
                $note        = strtolower($item->note ?? '');
                                                
                return collect($searchTerms)->contains(fn($term) =>
                    str_contains($transfer_id, $term) || 
                    str_contains($note, $term) || 
                    str_contains($asset_name, $term) ||                     
                    str_contains($incoming, $term) || 
                    str_contains($outgoing, $term) || 
                    str_contains($direction, $term) || 
                    str_contains($balance, $term) || 
                    str_contains($timestamp, $term));
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
        
        return Inertia::render('reconciliation/Reconciliation', [
            'reconciliation' => [
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

