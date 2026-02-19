<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {        
        $locale = $request->input('locale') ?? $request->cookie('locale') ?? $request->session()->get('locale') ?? $request->header('X-User-Locale') ?? 'en';

        $locale = strtolower(substr($locale, 0, 2));

        $supported = ['de', 'zh', 'en', 'es', 'fr', 'jp', 'bg', 'cz', 'dk', 'ee', 'fi', 'gr', 'hr', 'hu', 'ie', 'ir', 'it', 'lt', 'lv', 'mt', 'nl', 'pl', 'pt', 'ro', 'ru', 'sa', 'se', 'sk', 'sl', 'ua'];

        if (!in_array($locale, $supported)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        $request->session()->put('locale', $locale);

        return $next($request);
    }

}
