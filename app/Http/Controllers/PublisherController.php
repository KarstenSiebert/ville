<?php

namespace App\Http\Controllers;

use Str;
use Storage;
use Exception;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Market;
use App\Models\Wallet;
use App\Models\Transfer;
use App\Models\Publisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PublisherController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {           
        $search = $request->input('search', null);
                
        $page = (int) $request->input('page', 1);
            
        $perPage = 10;
        
        $user = auth()->user();
        
        $publishers = Publisher::with(['owner:id,name'])            
            ->get()
            ->map(function ($publisher) {
            
              return [
                    'id'          => $publisher->id,
                    'user_id'     => $publisher->user_id,
                    'name'        => $publisher->name,
                    'api_key'     => $publisher->api_key,
                    'api_secret'  => $publisher->api_secret,
                    'owner'       => $publisher->owner?->name,
                    'max_markets' => $publisher->max_markets,
                    'rate_limit'  => $publisher->rate_limit,
                    'max_shadows' => $publisher->max_shadows,
                    'tracking'    => $publisher->tracking ? true : false,
                    'reports'     => $publisher->reports ? true : false,
                    'active'      => $publisher->active ? true : false,
                ];
            });

        if (empty($publishers)) {
            $paginated = new LengthAwarePaginator([], 0, $perPage, $page);
        
        } else {
                $paginated = new LengthAwarePaginator(
                $publishers->forPage($page, $perPage)->values(),
                $publishers->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => $request->query()]
            );
        }

        // dd($paginated->items());
                            
        return Inertia::render('publishers/Publishers', [
            'publishers' => [
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
                'user' => [
                    'can_create' => $user->hasRole('admin'),
                    'can_delete' => $user->hasRole('admin')
                ]
            ]            
        ]);
    }

    public function create()
    {
        return Inertia::render('publishers/Create', [
        ]);
    }

    public function store(Request $request)
    {        
        $validated = $request->validate([            
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['required', 'integer', 'gt:0'],
            'description' => ['nullable', 'string', 'max:384'],            
            'settings' => 'required|array',
            'settings.rate_limit' => 'required|integer|min:0|max:10000',
            'settings.max_shadows' => 'required|integer|min:1|max:1000000',
            'settings.max_markets' => 'required|integer|min:1|max:100',
            'settings.features' => 'required|array',
            'settings.features.tracking' => 'required|boolean',
            'settings.features.reports' => 'required|boolean',
            'settings.active' => 'required|boolean',
        ]);     

        $validated['settings']['active'] = (bool) $validated['settings']['active'];
        $validated['settings']['features']['reports'] = (bool) $validated['settings']['features']['reports'];
        $validated['settings']['features']['tracking'] = (bool) $validated['settings']['features']['tracking'];

        // dd($validated);

        $publisher = DB::transaction(function() use ($validated) {
        
            $pUser = User::create([
                'name' => $validated['name'],
                'owner_user_id' => $validated['user_id'],
                'type' => 'OPERATOR',
                'parent_user_id' => null,                
                'profile_photo_path' => null,
                'is_system' => false,
            ]);

            $pUser->assignRole('operator');
     
            $toWallet = new Wallet;

            $toWallet->user_id = $pUser->id;

            $toWallet->address = 'avaaddr1'.bin2hex(openssl_random_pseudo_bytes(27));
                    
            $toWallet->type = 'available';
                                        
            $toWallet->user_type = 'OPERATOR';

            if ($toWallet->save()) {

                $publisher = Publisher::create([
                    'user_id' => $pUser->id,
                    'owner_user_id' => $validated['user_id'],
                    'name' => $validated['name'],
                    'type' => 'OPERATOR',
                    'api_key' => Str::uuid(),
                    'api_secret' => Str::random(32),            
                    'settings' => $validated['settings'],
                ]);

                $pUser->update(['publisher_id' => $publisher->id]);
                
                return $publisher;
            }
        });

        if (!empty($publisher)) {            
            return redirect('publishers')->with('success', __('operator_created_successfully'));
        }
        
        return redirect()->back()->with('error', __('operator_not_created'));
    }

    public function destroy($id)
    {
        $publisher = Publisher::with('user.avaWallet.tokenWallets.token')->find($id);

        if (empty($publisher)) {
            return back()->with('error', __('operator_does_not_exist'));
        }
        
        $markets = Market::where('publisher_id', $publisher->id)->get();

        if (!$markets->isEmpty()) {
            return back()->with('error', __('cancel_operator_markets_first'));
        }
        
        if ($this->authorize('delete', $publisher)) {

            $ownerWallet = Wallet::where('user_id', $publisher->owner_user_id)->where('type', 'available')->where('user_type', 'USER')->first();

            if (empty($ownerWallet)) {
                $ownerWallet = Wallet::where('user_id', 1)->where('type', 'available')->where('user_type', 'USER')->first();
            }

            DB::transaction(function () use ($publisher, $ownerWallet) {
    
                $chunkSize = 100;    
                $publisherWallet = $publisher->user->avaWallet;

                if (!$publisherWallet) {
                    throw new \Exception('Operatorr wallet not found.');
                }

                User::with(['avaWallet.tokenWallets.token'])
                    ->where('publisher_id', $publisher->id)
                    ->where('type', 'SHADOW')
                    ->orderBy('id')
                    ->chunk($chunkSize, function ($users) use ($publisherWallet) {
            
                    foreach ($users as $user) {
                        $wallet = $user->avaWallet;
                
                        if (!$wallet) continue;
            
                        foreach ($wallet->tokenWallets as $tw) {
                
                            if ($tw->quantity > 0) {                                
                                Transfer::execute($wallet, $publisherWallet, $tw->token, $tw->quantity, 'internal', 0, 'SHADOWS REMOVED');
                            }
                        }

                        $wallet->tokenWallets()->delete();
                        $wallet->delete();
                        $user->delete();
                    }
                });
                
                $publisherWallet->load('tokenWallets.token');

                foreach ($publisherWallet->tokenWallets as $tw) {
        
                    if ($tw->quantity > 0) {
                        Transfer::execute($publisherWallet, $ownerWallet, $tw->token, $tw->quantity, 'internal', 0, 'OPERATOR REMOVED');
                    }                  
                }

                $publisherWallet->tokenWallets()->delete();
                $publisherWallet->delete();
                $publisher->user->delete();
                $publisher->delete();               
            });
            
            return redirect()->back()->with('success', __('operator_removed_successfully'));
        }

        return redirect()->back()->with('error', __('operator_not_removed'));
    }

    public function limit(Request $request, int $publisherId)
    {                
        $data = $request->validate([            
            'rate_limit'  => 'required|integer|min:0|max:10000',
            'max_shadows' => 'required|integer|min:1|max:1000000',
            'max_markets' => 'required|integer|min:1|max:100',
            'tracking'    => 'required|boolean',
            'reports'     => 'required|boolean',
            'active'      => 'required|boolean',
        ]);
        
        $publisher = Publisher::findOrFail($publisherId);
        
        if ($this->authorize('update', $publisher)) {          
            $settings = $publisher->settings ?? [];
            
            foreach ($settings['features'] as $key => $value) {
               $settings['features'][$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }

            $settings['active'] = $data['active'] ?? $settings['active'];

            $settings['rate_limit'] = strval($data['rate_limit']) ?? strval($settings['rate_limit']);

            $settings['max_markets'] = strval($data['max_markets']) ?? strval($settings['max_markets']);

            $settings['max_shadows'] = strval($data['max_shadows']) ?? strval($settings['max_shadows']);

            $settings['features']['reports'] = $data['reports'] ?? $settings['features']['reports'];

            $settings['features']['tracking'] = $data['tracking'] ?? $settings['features']['tracking'];
            
            $publisher->update(['settings' => $settings]);
        }

        return redirect()->back();
    }

}
