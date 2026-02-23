<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ApiMobileClientController extends Controller
{
    public function index(Request $request)
    {
        $shadowId = $request->shadow_user?->id ?? null;

        return response()->json(['hallo' => $request->public_id]);
    }

}
