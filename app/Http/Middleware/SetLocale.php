<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The application is in English only: the language of the browser, the session or the user is ignored.
 */
class SetLocale
{
    public const LOCALE = 'en';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(self::LOCALE);
        Carbon::setLocale(self::LOCALE);

        return $next($request);
    }
}
