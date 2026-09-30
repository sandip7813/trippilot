<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    /**
     * Sign the freshly registered user back out and send them to the login
     * page: they must first log in with the one-time password we emailed.
     *
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        Auth::guard(config('fortify.guard'))->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with(
            'status',
            __('Your account has been created. We have emailed you a one-time password to log in.'),
        );
    }
}
