<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if ($request->user()->must_change_password) {
            return redirect()->to(route('password.change', absolute: false));
        }

        return $next($request);
    }
}
