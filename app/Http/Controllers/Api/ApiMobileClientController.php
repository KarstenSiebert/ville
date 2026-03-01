<?php

namespace App\Http\Controllers\Api;

use DB;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Wallet;
use App\Models\Market;
use BaconQrCode\Writer;
use App\Models\Transfer;
use App\Models\Publisher;
use App\Models\TokenWallet;
use Illuminate\Http\Request;
use App\Helpers\ImageStorage;
use BaconQrCode\Renderer\Color\Rgb;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cookie;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use App\Http\Controllers\Api\ApiMobileClientController;

class ApiMobileClientController extends Controller
{
    public function index(Request $request)
    {
        $shadowId = $request->shadow_user?->id ?? null;

        return response()->json(['hallo' => $request->public_id]);
    }

    public function detail(Request $request, $id)
    {              
        if ($locale = $request->query('locale')) {
            
            app()->setLocale($locale);
    
            $request->session()->put('locale', $locale);
        }

        $cookie = Cookie::queue('locale', $locale, 60*24*365, '/', null, false, false);        

        if (empty($id)) {
             return redirect('dashboard');
        }
        
        $user = auth()->user();

        if (!$user) {
            return redirect('login');
        }

        $market = Market::with(['baseToken:id,name,decimals,token_type,fingerprint,logo_url', 'outcomes:id,market_id,name,link,logo_url'])
                ->where('markets.id', $id)
                ->first();

        if (empty($market)) {
             return redirect('dashboard');
        }        
                
        return Inertia::render('api/MarketDetail', [
            'market' => $market,
            'tokenValue' => $this->getAvailableTokens($market, $user),
        ]);
    }

    public function webview(Request $request, $id)
    {
        $shadowUser = $request->shadow_user;

        $loginUrl = URL::temporarySignedRoute('webview.login', now()->addMinutes(5), ['user' => $shadowUser->id, 'market' => $id, 'locale' => $request->locale]);

        return  response()->json(['access' => $loginUrl], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); 
    }

    private function getAvailableTokens(Market $market, User $user)
    {
        $baseToken = $market->baseToken;

        $wallet = TokenWallet::where('wallet_id', $user?->avaWallet?->id)->where('token_id', $baseToken->id)->first();

        $tokenValue = $wallet?->available ?? 0;
                
        $availableTokens = bcdiv($tokenValue, bcpow("10", (string) $baseToken->decimals, $baseToken->decimals), $baseToken->decimals);

        return $availableTokens;
    }

    public function deposit(Request $request, $id)
    {
        $shadowUser = $request->shadow_user;

        $loginUrl = URL::temporarySignedRoute('deposit.wallet', now()->addMinutes(5), ['user' => $shadowUser->id, 'market' => $id, 'locale' => $request->locale]);

        return  response()->json(['access' => $loginUrl], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function wallet(Request $request, $id)
    {           
        if ($locale = $request->query('locale')) {
            
            app()->setLocale($locale);
    
            $request->session()->put('locale', $locale);
        }

        $cookie = Cookie::queue('locale', $locale, 60*24*365, '/', null, false, false); 
             
        $user = auth()->user();
        
        if (!$user) {
            return redirect('login');
        }

        $tokens = [];
        
        if (!empty($user) && ($user->type === 'SHADOW')) {
            
            if ($usrWallet = Wallet::where('user_id', $user->id)->where('type', 'available')->where('user_type', $user->type)->first()) {
                                                      
                $tokens = TokenWallet::with(['token.outcomes.market', 'token.markets'])->forWallet($usrWallet->id)
                    ->withQuantity()
                    ->withActiveToken()
                    ->loadTokenData()
                    ->limit(50)                 
                    ->get()
                    ->map(function ($tw) use ($user, $id) {

                        if (!str_starts_with($tw->token->logo_url, 'https') && !str_starts_with($tw->token->logo_url, '/storage')) {                            
                            $tw->token->logo_url = ImageStorage::saveBase64Image($tw->token->logo_url, trim($tw->token->name));
                        }

                        $isUserToken = false;

                        if ($tw->token->user_id == auth()->id()) {
                            $isUserToken = true;
                        }

                        $minimal_tokens = 0;

                        $download = null;
                        
                        /*
                        foreach ($tw->token->outcomes as $outcome) {

                            if ($outcome->market) {                            
                        
                            }
                        }
                        */

                        foreach ($tw->token->markets as $market) {
                            
                            if (($market->id == $id) && ($market->baseToken->fingerprint == $tw->token->fingerprint) && ($tw->token->token_type == 'BASE')) {
                                $minimal_tokens = (int) max($market->b / $market->max_subscribers, 0);

                                $download = $market->download ?? null;
                            }
                        }

                        return [
                            'id'             => $tw->id,
                            'asset_name'     => $tw->token->name,
                            'quantity'       => $tw->quantity,
                            'decimals'       => $tw->token->decimals,
                            'fingerprint'    => $tw->token->fingerprint,
                            'logo_url'       => $tw->token->logo_url,                            
                            'token_type'     => $tw->token->token_type,
                            'download'       => $download,
                            'minimal_tokens' => max($tw->quantity - $tw->reserved_quantity, 0)
                            // 'minimal_tokens' => max($tw->quantity - $minimal_tokens, 0)

                            // 'minimal_tokens' => 4
                        ];                        
                    });                    
            }            
        }        

        return Inertia::render('api/Deposits', [
                'assets' => $tokens,
            ],
        );        
    }

    public function qrcode(Request $request, $id)
    {
        $tokenWallet = TokenWallet::with(['user'])->findOrFail($id);
            
        $foregroundColor = new Rgb(148, 164, 163);

        $backgroundColor = new Rgb(255, 255, 255);

        $fill = Fill::uniformColor($foregroundColor, $backgroundColor);

        $marketData = ['user' => $tokenWallet->user->name];
        
        $data = json_encode($marketData);
                            
        $renderer = new ImageRenderer(new RendererStyle(320, 1, null, null, $fill), new ImagickImageBackEnd());
        
        $qrcode = 'data:image/png;base64,'.base64_encode((new Writer($renderer))->writeString($data));
                                    
        return response()->json($qrcode, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);        
    }

    public function destroy($id)
    {        
        $tokenWallet = TokenWallet::with(['token.markets', 'user.avaWallet'])->where('id', $id)->first();
        
        $srcWallet = $tokenWallet->user->avaWallet;
        $publisher = null;
        $baseToken = null;

        foreach ($tokenWallet->token->markets as $market) {
                                    
            if (($market->baseToken->fingerprint == $tokenWallet->token->fingerprint) && ($tokenWallet->token->token_type == 'BASE')) {
                // $minimal_tokens = (int) max($market->b / $market->max_subscribers, 0);     
                
                // $minimal_tokens = max($tokenWallet->quantity - $tokenWallet->reserved_quantity - $minimal_tokens, 0);

                $minimal_tokens = max($tokenWallet->quantity - $tokenWallet->reserved_quantity, 0);

                $publisher = Publisher::with('user.avaWallet')->where('id', $market->publisher_id)->first();

                $baseToken = $market->baseToken;
            }
        }
                
        if ($publisher) {           
            $pubWallet = $publisher->user->avaWallet;

            if (!empty($baseToken) && !empty($srcWallet) && !empty($pubWallet) && ($minimal_tokens > 0)) {
                Transfer::execute($srcWallet, $pubWallet, $baseToken, $minimal_tokens, 'internal', 0, 'REDEEM', false);
            }
        }

        return redirect()->back();
    }

}
