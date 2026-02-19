<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\LogMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;

class LogMessageController extends Controller
{
    public function index(Request $request)
    {        
        $search = $request->input('search', null);
    
        $page = (int) $request->input('page', 1);
    
        $perPage = 10;

        $user = auth()->user();
         
        if ($user->hasRole('child')) {
            return redirect('deposits');
        }

        $items = Cache::remember('messages', 60, function () {
            return LogMessage::limit(300)->orderBy('id', 'DESC')->get();
        });
        
        // $items = LogMessage::limit(300)->orderBy('id', 'DESC')->get();

        if (!empty($search)) {   
            $searchTerms = preg_split('/\+/', strtolower(trim($search)));

            $filtered = $items->filter(function($item) use ($searchTerms) {

                $level   = strtolower($item->level ?? '');                
                $level_name = strtolower($item->level_name ?? '');
                $message  = strtolower($item->message ?? '');                
                                                
                return collect($searchTerms)->contains(fn($term) =>
                    str_contains($level, $term) || str_contains($level_name, $term) || str_contains($message, $term));
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
          
        return Inertia::render('log/Messages', [
            'messages' => [
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
