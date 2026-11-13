<?php

namespace App\Http\Middleware;

use App\Filament\Pages\ChangePassword;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a user who signed in with a temporary password to the page where they choose their own.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->mustChangePassword() && ! $request->routeIs(ChangePassword::getRouteName()) && ! $request->routeIs('livewire.*')) {
            return redirect(ChangePassword::getUrl());
        }

        return $next($request);
    }
}
