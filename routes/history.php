<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\LogMessageController;

Route::middleware('auth', 'verified')->group(function () {
    Route::resource('history', HistoryController::class)->except(['show']);

    Route::resource('log', LogMessageController::class)->except(['show']);

    Route::get('/users/search', function(Request $request) {
        $query = $request->input('q', '');

        if (!$query) {
            return response()->json([]);
        }
            
        $users = User::where(function ($q) use ($query) {
            $q->whereRaw('LOWER(name) LIKE ?', ["%{$query}%"])
                ->orWhereRaw('LOWER(email) LIKE ?', ["%{$query}%"]);
            })
            ->where('is_system', false)
            // ->where('parent_user_id', null)
            ->take(5)
            ->get(['id', 'name', 'email']);            
    
        return response()->json($users);
    });

    Route::get('/shadows/{publisher}/search', function (Request $request, $publisher) {
        $query = $request->input('q', '');

        if (!$query) {
            return response()->json([]);
        }

        $publisherUser = User::findOrFail($publisher);
        
        $realPublisherId = optional($publisherUser->publisher)->id ?? $publisherUser->publisher_id  ?? $publisherUser->id;
        
        $users = User::query()            
            ->where('publisher_id', $realPublisherId)
            ->where('is_system', false)
            ->where('type', 'SHADOW')
            // ->whereNull('parent_user_id')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                ->orWhere('external_user_id', 'like', "%{$query}%");                
            })
            ->limit(5)            
            ->get(['id', 'name', 'email']);
        
        return response()->json($users);
    });

});