<x-mail::message>
# We've replied to your message

Hi {{ $name }}, thanks for getting in touch. Our reply to ticket **{{ $reference }}** is below.

<x-mail::message-box label="Our reply" :meta="$repliedAt" :text="$replyBody" tone="reply" />

## Your original message

<x-mail::details :rows="[
    'Ticket number' => $reference,
    'Sent on' => $sentAt,
    'Topic' => $topic,
    'Subject' => $originalSubject,
]" />

<x-mail::message-box label="Your message" :text="$originalMessage" />

<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>

Need more help? Send us a new message and mention ticket **{{ $reference }}**.

Thanks,<br>
The {{ config('app.name') }} team
</x-mail::message>
