<?php

namespace App\Http\Controllers\Settings;

use DB;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Token;
use App\Models\Wallet;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ProfileController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        
        // $user = auth()->user();

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }
        
        /*
        dd(
            $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail,
            $user->hasVerifiedEmail(),
            $user->email_verified_at,
        );
        */

        return Inertia::render('settings/Profile', [
            // 'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'mustVerifyEmail' => !empty($user->hasVerifiedEmail()) ? ! $user->hasVerifiedEmail() : true,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {            
        // $request->user()->fill($request->validated());

        $user = $request->user();

        $data = $request->validated();
       
        $user->fill($data);

        if (isset($data['payout'])) {
            $user->payout = $data['payout'];
        }
        
        $user->save();

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }
        
        if ($request->hasFile('photo')) {
            
            if ($request->user()->profile_photo_path) {
                Storage::disk('public')->delete($request->user()->profile_photo_path);
            }

            $photoPath = $request->file('photo')->store('avatars', 'public');
            $request->user()->profile_photo_path = $photoPath;
        }
                
        $request->user()->save();
        
        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($this->transferAllAssets($user->id)) {
            Auth::logout();

            Wallet::where('user_id', $user->id)->delete();

            $user->delete();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect('/');
    }

    private function transferAllAssets(int $userId): bool
    {        
        $data = [];
        
        $data = DB::table('users')
            ->join('wallets', 'wallets.user_id', '=', 'users.id')
            ->join('token_wallet', 'token_wallet.wallet_id', '=', 'wallets.id')
            ->join('tokens', 'tokens.id', '=', 'token_wallet.token_id')
            ->where('users.id', $userId)
            ->where('token_wallet.quantity', '>', 0)
            ->where(function ($q) {
                $q->where('wallets.type', 'available')
                  ->orWhere('wallets.type', 'reserved');
            })
            ->select([
                'tokens.policy_id',
                'tokens.name as asset_name',
                'tokens.name_hex as asset_hex',
                'tokens.fingerprint',
                'token_wallet.quantity as token_number',
                'token_wallet.quantity as quantity',
                'tokens.decimals',
                'wallets.id as wallet_id',
                'users.email as destination',
            ])
            ->get()
            ->toArray();

        foreach($data as $d) {
        
            if ($admin = User::where('email', config('chimera.admin_user'))->first()) {
                
                $d->fingerprint = !empty($d->fingerprint) ? $d->fingerprint : "";

                if ($token = Token::where('name', $d->asset_name)->where('fingerprint', $d->fingerprint)->first()) {

                    if ($fromWallet = Wallet::where('id', $d->wallet_id)->where('user_id', $userId)->first()) {

                        if ($toWallet = Wallet::where('type', 'available')->where('user_id', $admin->id)->first()) {
                            Transfer::execute($fromWallet, $toWallet, $token, $d->token_number, 'internal', 0, 'UTAW');
                        }
                    }
                }
            }
        }
        
        return true;
    }

}
