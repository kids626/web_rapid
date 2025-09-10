<?php

namespace App\Http\Middleware;

use Closure;

class ReportBasicAuth
{
    public function handle($request, Closure $next)
    {
        $user = env('REPORT_AUTH_USER');
        $pass = env('REPORT_AUTH_PASS');

        if (!$user) {
            return response('Unauthorized', 401);
        }

        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            header('WWW-Authenticate: Basic realm="Sales Report"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Unauthorized';
            exit;
        }

        $inputUser = $_SERVER['PHP_AUTH_USER'] ?? '';
        $inputPass = $_SERVER['PHP_AUTH_PW'] ?? '';

        if (!hash_equals($user, $inputUser) || !hash_equals($pass ?? '', $inputPass)) {
            header('WWW-Authenticate: Basic realm="Sales Report"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Unauthorized';
            exit;
        }

        return $next($request);
    }
}


