<?php

namespace App\Http\Controllers;

use Auth;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Wallet;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use BaconQrCode\Renderer\Color\Rgb;
use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;

class WalletController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
     public function show(): Response
    {        
        $user = User::find(Auth::user()->id);

        if ($user) {
            
            $foregroundColor = new Rgb(148, 164, 163);

            $backgroundColor = new Rgb(255, 255, 255);

            $fill = Fill::uniformColor($foregroundColor, $backgroundColor);

            $address = Wallet::where('user_id', $user->id)->where('type', 'deposit')->value('address');
                            
            $renderer = new ImageRenderer(new RendererStyle(400, 1, null, null, $fill), new ImagickImageBackEnd());
        
            $qrcode = (new Writer($renderer))->writeString($address);
        
            $qrcode = 'data:image/png;base64,'.base64_encode($qrcode);
                                    
            return Inertia::render('settings/Wallet', [
                'address'     => $address,
                'qrcode'      => $qrcode                
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Wallet $wallet)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Wallet $wallet)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Wallet $wallet)
    {
        //
    }
}
