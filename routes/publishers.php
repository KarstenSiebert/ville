<?php

use App\Models\Publisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublisherController;

Route::middleware('auth', 'verified')->group(function () {     
    Route::resource('/publishers', PublisherController::class)->except(['show']);

    Route::post('/publishers/limit/{id}', [PublisherController::class, 'limit'])->name('publishers.limit');  
    
    Route::get('/publishers/search', function(Request $request) {
        $query = $request->input('q', '');

        $publishers = null;

        $query = strtolower($query);
            
        $publishers = Publisher::where(function ($q) use ($query) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$query}%"]);
            })            
            ->take(5)
            ->get();
    
        // $result = $publishers;
        
        $result = $publishers->map(function($publisher) {
                return [
                    'id' => $publisher->id,
                    'name' => $publisher->name
                ];
        });
        
        return response()->json($result);
    });

});
