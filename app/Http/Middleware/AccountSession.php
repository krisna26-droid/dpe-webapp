<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Session\Middleware\AuthenticateSession;

class AccountSession extends AuthenticateSession
{
    public function handle($request, Closure $next)
    {
        if ($request->hasSession() && $request->user()) {
            $session = $request->session();
            $id = $request->user()->getAuthIdentifier();
            // A legitimately authenticated account switch must not reuse the previous account's password marker.
            if ($session->has('account_session_user_id') && $session->get('account_session_user_id') !== $id) {
                $session->forget('password_hash_'.$this->auth->getDefaultDriver());
            }
            $session->put('account_session_user_id', $id);
        }

        return parent::handle($request, $next);
    }
}
