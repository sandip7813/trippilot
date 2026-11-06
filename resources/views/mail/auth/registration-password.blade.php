<x-mail::message>
# Welcome aboard, {{ $firstName }}!

Your TripPilot account is ready. Use the one-time password below to log in for the first time.

<x-mail::details :rows="[
    'Email' => $email,
]" />

<x-mail::code-box label="One-time password" :code="$password" hint="Case-sensitive. Copy it exactly as shown." />

<x-mail::button :url="$loginUrl">
Log in to TripPilot
</x-mail::button>

Right after you log in, you will be asked to choose your own password. The one-time password stops working once you do.

Happy travels,<br>
The {{ config('app.name') }} team

<x-slot:subcopy>
If the button does not work, paste this link into your browser: [{{ $loginUrl }}]({{ $loginUrl }})

Did not create a TripPilot account? You can safely ignore this email.
</x-slot:subcopy>
</x-mail::message>
