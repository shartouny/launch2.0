<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class Recaptcha
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if(config('app.recaptcha_private_key')) {
            $recaptchaUrl = 'https://www.google.com/recaptcha/api/siteverify';
            $recaptchaSecret = config('app.recaptcha_private_key');
            $recaptchaResponse = $request->recaptchaToken;

            // Make and decode POST request:
            $recaptcha = file_get_contents($recaptchaUrl . '?secret=' . $recaptchaSecret . '&response=' . $recaptchaResponse);
            $recaptcha = json_decode($recaptcha);

            if (!$recaptcha->success || $recaptcha->score < 0.5) {
//            if(isset($recaptcha->{'error-codes'})) {
//                foreach ($recaptcha->{'error-codes'} as $errorCode) {
//                    if ($errorCode == 'timeout-or-duplicate') {
//
//                    }
//                }
//            }
                Log::warning("Recaptcha failed | IP: {$request->ip()} | Response: " . json_encode($recaptcha));
                return response('Recaptcha failed, please refresh page and try again', Controller::HTTP_BAD_REQUEST);
            }
        }

        return $next($request);
    }
}
