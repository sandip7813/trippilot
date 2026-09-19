<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendRegistrationOtpRequest;
use App\Services\Auth\RegistrationOtpService;
use Illuminate\Http\RedirectResponse;

class SendRegistrationOtpController extends Controller
{
    public function __invoke(
        SendRegistrationOtpRequest $request,
        RegistrationOtpService $registrationOtpService,
    ): RedirectResponse {
        $email = $request->validated('email');

        $registrationOtpService->send($email);

        $request->session()->put([
            'otp_sent' => true,
            'otp_email' => $email,
        ]);

        return back();
    }
}
