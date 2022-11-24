<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CORS
{
    public function handle(Request $request, Closure $next)
    {
        header('Access-Control-Allow-Methods: GET, PUT, POST, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Auth-Token, charset, boundary, Content-Length');
        header('Access-Control-Allow-Origin: *');
        return $next($request);
    }
}
