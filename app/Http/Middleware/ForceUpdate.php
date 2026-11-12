<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceUpdate
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow POST requests through so form submissions work
        if ($request->isMethod('post')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && $user->isStudent() && $user->student?->force_update) {
            $accountRoute = route('student.account');

            // If not already on the account page, redirect there
            if (! $request->is('account*')) {
                return redirect($accountRoute);
            }
        }

        return $next($request);
    }
}
