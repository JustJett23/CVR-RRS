<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AccountRolePath;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountRolePrefix
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active, 403);

        $rolePath = AccountRolePath::for($user);
        URL::defaults(['role' => $rolePath]);

        $routeRole = $request->route('role');

        if ($routeRole !== $rolePath) {
            if (is_string($routeRole) && ! AccountRolePath::recognizes($routeRole) && AccountRolePath::isLegacyPath($routeRole)) {
                abort_unless(in_array($request->method(), ['GET', 'HEAD'], true), 403);

                $destination = '/'.$rolePath.'/'.implode('/', $request->segments());
                $query = $request->getQueryString();

                return redirect()->to($destination.($query === null ? '' : '?'.$query));
            }

            abort(403);
        }

        return $next($request);
    }
}
