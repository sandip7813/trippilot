<x-mail::message>
# New contact message

**{{ $name }}** sent a message through the contact page.

## Ticket

<x-mail::details :rows="[
    'Ticket number' => $reference,
    'Received' => $receivedAt,
    'Topic' => $topic,
    'Subject' => $subject,
]" />

## Sender

<x-mail::details :rows="[
    'Name' => $name,
    'Email' => $email,
    'Phone' => $phone,
    'Account' => $isRegistered ? 'Registered user' : 'Guest',
]" />

<x-mail::message-box label="Message" :text="$body" />

<x-mail::button :url="$inboxUrl">
Reply in the admin inbox
</x-mail::button>

Replying from the admin inbox emails the sender and keeps the conversation on record.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
