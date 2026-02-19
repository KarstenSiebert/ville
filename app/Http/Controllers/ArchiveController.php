<?php

namespace App\Http\Controllers;

use DB;
use Inertia\Inertia;
use App\Models\Wallet;
use App\Models\Transfer;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Helpers\ImageStorage;
use App\Helpers\CardanoFingerprint;
use Illuminate\Pagination\LengthAwarePaginator;

class ArchiveController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', null);
    
        $page = (int) $request->input('page', 1);
    
        $perPage = 10;

        $user = auth()->user();
                    
        $address = null;

        $ownWallet = null;

        $ownDepWallet = null;
        $ownAvaWallet = null;
        $ownResWallet = null;
        
        $walletQuery = Wallet::where('user_id', $user->id)->where('type', 'available');

        if (!$user->hasRole('admin')) {
            $address = $walletQuery->value('address');           
        }

        $ownWallet = Wallet::where('user_id', $user->id)->where('type', 'deposit')->first();
            
        if ($ownWallet) {            
            $ownDepWallet = $ownWallet->id;
            $ownAvaWallet = Wallet::where('parent_wallet_id', $ownWallet->id)->where('type', 'available')->value('id');
            $ownResWallet = Wallet::where('parent_wallet_id', $ownWallet->id)->where('type', 'reserved')->value('id');            
        }

        // dd($ownDepWallet, $ownAvaWallet, $ownResWallet);
        
        $items = Transfer::query()
            ->select([
                'transfers.id as tx_id',
                'transfers.type',
                'transfers.quantity',
                'transfers.status',
                'transfers.tx_hash',
                'transfers.from_wallet_id',
                'transfers.to_wallet_id',
                'transfers.note',
                'transfers.created_at as timestamp',
                'tokens.name as token_name',
                'tokens.token_type as token_type',
                'tokens.fingerprint as fingerprint',
                'tokens.decimals as decimals',
                'tokens.logo_url as token_logo_url',
                \DB::raw('COALESCE(u_from.name, CAST(w_from.id AS TEXT)) as sender'),
                \DB::raw('COALESCE(u_to.name, CAST(w_to.id AS TEXT)) as receiver'),    
            ])
            ->leftJoin('tokens', 'transfers.token_id', '=', 'tokens.id')
            ->leftJoin('wallets as w_from', 'transfers.from_wallet_id', '=', 'w_from.id')
            ->leftJoin('users as u_from', 'w_from.user_id', '=', 'u_from.id')
            ->leftJoin('wallets as w_to', 'transfers.to_wallet_id', '=', 'w_to.id')
            ->leftJoin('users as u_to', 'w_to.user_id', '=', 'u_to.id') 
            ->when(!$user->hasRole('admin'), function ($query) use ($user) {
                // IDs aller Wallets des Users
                $userWalletIds = $user->wallets()->pluck('id')->toArray();

                $allWalletIds = array_unique(array_merge($userWalletIds));

                $query->where(function ($sub) use ($allWalletIds) {
                    $sub->whereIn('w_from.id', $allWalletIds)
                        ->orWhereIn('w_to.id', $allWalletIds);
                });
            })
            ->orderByDesc('transfers.id') 
            ->limit(200)
            ->get();

        if (!empty($search)) {   
            $searchTerms = preg_split('/\+/', strtolower(trim($search)));

            $filtered = $items->filter(function($item) use ($searchTerms) {
                
                $type       = strtolower($item->type ?? '');
                $tx_id      = strtolower($item->tx_id ?? '');
                $status     = strtolower($item->status ?? '');                
                $sender     = strtolower($item->sender ?? '');                
                $receiver   = strtolower($item->receiver ?? '');
                $quantity   = strtolower($item->quantity ?? '');                
                $timestamp  = strtolower($item->timestamp ?? '');
                $token_name = strtolower($item->token_name ?? '');
                                                
                return collect($searchTerms)->contains(fn($term) =>
                    str_contains($tx_id, $term) || 
                    str_contains($token_name, $term) || 
                    str_contains($sender, $term) || 
                    str_contains($receiver, $term) ||                     
                    str_contains($quantity, $term) || 
                    str_contains($timestamp, $term) || 
                    str_contains($type, $term) || 
                    str_contains($status, $term));
            });
        
        } else {
            $filtered = $items;
        }
        
        $assignedItems = collect($filtered)->map(function ($asset) use($ownAvaWallet, $ownDepWallet, $ownResWallet) {
                                            
            $asset['status'] = $asset['status'] == 'completed' ? '✅' : '⚠️';
            
            if ($asset['type'] == 'onchain_out') {
                $asset['type'] = '⬆️';
                $asset['to_wallet_id'] = '➡️';

            } else if ($asset['type'] == 'onchain_in') {
                $asset['type'] = '⬇️';
                $asset['from_wallet_id'] = '➡️';
                    
            } else {
                $asset['type'] = '🔁';
            }

            if ($asset['from_wallet_id'] == $ownAvaWallet) {
                $asset['from_wallet_id'] = '🏠';
            
            } else {
                $asset['from_wallet_id'] = '↘️';            
            }
                    
            if ($asset['from_wallet_id'] == $ownDepWallet) {
                $asset['from_wallet_id'] = '➡️🏠';
            }

            if ($asset['to_wallet_id'] == $ownAvaWallet) {
                $asset['to_wallet_id'] = '🏠';
            
            } else {
                $asset['to_wallet_id'] = '↗️';
            }

            if ($asset['to_wallet_id'] == $ownDepWallet) {
                $asset['to_wallet_id'] = '➡️🏠';
            }

            if ($asset['to_wallet_id'] == $ownResWallet) {
                $asset['to_wallet_id'] = '🏠➡️';
            }
            
            if (!str_starts_with($asset['token_logo_url'], 'https') && !str_starts_with($asset['token_logo_url'], '/storage')) {
                $asset['token_logo_url'] = ImageStorage::saveBase64Image($asset['token_logo_url'], trim($asset['token_name']));
            }
            
            return $asset;
        });            
        
        if ($assignedItems->count()) {

            $paginated = new LengthAwarePaginator(
                    $assignedItems->forPage($page, $perPage)->values(),
                    $assignedItems->count(),
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'query' => $request->query()]
                );
        
        } else {
            $paginated = new LengthAwarePaginator([], 0, $perPage, $page);            
        }

        return Inertia::render('archives/Archive', [
            'archive' => [
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
