<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function store(Request $request)
    {
        $err = [];
        
        $err['response'] = 'error';
        $err['message'] = '';  
       
        // dd($request->all());

        $request->merge(['symbol' => strtoupper($request->symbol)]);

        $user = auth()->user();
        
        $validated = $request->validate([
            'productName'     => ['required', 'string', 'max:32'],  
            'symbol'          => ['required', 'string', 'max:8', Rule::unique('tokens', 'name')->where(fn ($query) => $query->where('user_id', auth()->id()))],
            'totalSupply'     => ['required', 'integer', 'min:1', 'max:1000000000'],
            'metadata'        => ['nullable', 'array'],            
            'status'          => ['required', 'string', 'in:active,inactive,private'],
            'additional_info' => ['nullable', 'string'],
        ],
        [            
            'symbol.unique'   => __('this_token_already_exists_for_your_account')
        ]);
        
        $txPrefix = '/var/www/ville/storage/app/private/transactions/'.bin2hex(openssl_random_pseudo_bytes(4)).'/';
      
        if (!empty($user) && CardanoCliWrapper::make_dir($txPrefix)) {

            $symbol   = strtoupper($validated['symbol']);

            $policyId = $user->createPolicy($txPrefix);
            $assetHex = bin2hex($symbol);

            $product = [];

            $product['name']        = $symbol;
            $product['name_hex']    = $assetHex;
            $product['policy_id']   = $policyId;
            $product['fingerprint'] = CardanoFingerprint::fromPolicyAndName($policyId, $symbol);
            $product['decimals']    = 0;
            $product['description'] = '';
            $product['logo_url']    = null;            
                        
            $metaData = new CreateNftMetadata();
        
            $additional_info = !empty($validated['additional_info']) ? $validated['additional_info'] : '';

            $nftMetadata = $metaData->generateNftMetadata($policyId, $product, $additional_info);
                
            if (!empty($nftMetadata)) {
                            
                $jsonData = CardanoFingerprint::getProductJson($symbol, $policyId, $nftMetadata);
                
                if ($jsonData) {                    
                    $product['description'] = !empty($jsonData['description']) ? substr($jsonData['description'], 0, 2048) : '';

                    $product['logo_url']    = !empty($jsonData['image']) ? $jsonData['image'] : '';

                    $product['logo_url']    = ImageStorage::saveBase64Image($product['logo_url'], trim($validated['symbol']));
                }
            }
        
            $product['metadata']       = null;
            $product['supply']         = $validated['totalSupply'];
            $product['token_type']     = 'SHARE';
            $product['metadata']       = json_encode($validated['metadata']);
            $product['status']         = $validated['status'];
            $product['user_id']        = $user->id;
               
            if (empty($product['logo_url'])) {
                $product['logo_url'] = '/storage/logos/wechselstuben-logo.png';
            }

            $token = Token::updateOrCreate(
                [
                    'name'           => $product['name'],
                    'fingerprint'    => $product['fingerprint'],
                    'user_id'        => $product['user_id'],
                ],
                [
                    'name_hex'             => $product['name_hex'],
                    'policy_id'            => $product['policy_id'],
                    'decimals'             => $product['decimals'],
                    'logo_url'             => $product['logo_url'],
                    'metadata'             => $product['metadata'],
                    'description'          => $product['description'],
                    'supply'               => $product['supply'],                    
                    'token_type'           => $product['token_type'],                    
                    'status'               => $product['status'],
                ]
            );
            
            if (!empty($token)) {
                
                logger("💸 Token created");

                if ($avWallet = Wallet::where('user_id', $token->user_id)->where('type', 'available')->first()) {
                    Transfer::execute(null, $avWallet, $token, $token->supply, 'internal', 0, 'Market token minted');

                    logger("💸 Transfer excuted");
                
                } else {
                    $token->delete();

                    logger("💸 Token deleted");
                }
            
            } else {
                    logger("💸 Token not created");
            }

            CardanoCiWrapper::remove_dir($txPrefix);
            
            return redirect('mints')->with('success', __('market_token_successfully_created'));
        }

        return redirect('mints')->with('error', __('not_implemented_yet'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    { 
        $err = __('product_token_deleted');

        $asset = !empty($request->input('asset')) ? $request->input('asset') : []; 

        DB::transaction(function() use ($asset) {

            $tokenWallet = TokenWallet::with('token.tokenWallets')->findOrFail($asset['token_wallet_id']);

            $token = $tokenWallet->token;            

            $isRemovable = in_array($token->token_type, ['SHARE'], true);

            if (!$isRemovable || $token->supply !== $tokenWallet->quantity || $tokenWallet->reserved_quantity !== 0 || auth()->user()->email == config('chimera.admin_user')) {
                abort(403, "Token cannot be burned.");
            }            

            $allTokenWallets = $token->tokenWallets;
            
            foreach ($allTokenWallets as $tokenWallet) {

                if ($tokenWallet->quantity >= 0) {
                    $tokenWallet->delete();
                
                } else {
                    $tokenWallet->update(['status' => 'inactive']);
                }
            }

            // $token->delete();
    
            $token->update(['status' => 'inactive']);
        });

        return redirect()->back()->with('success', $err);
    }
    
}
