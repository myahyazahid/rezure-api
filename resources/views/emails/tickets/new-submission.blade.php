<x-mail::message>
# New {{ $ticket->category }} ticket

**{{ $ticket->title }}**

{{ \Illuminate\Support\Str::limit($ticket->description, 300) }}

| | |
|---|---|
| Device | {{ $ticket->device->device_id }} |
| App version | {{ $ticket->app_version ?? '—' }} |
| OS version | {{ $ticket->os_version ?? '—' }} |
| Attachments | {{ $ticket->attachments->count() }} |

<x-mail::button :url="$dashboardUrl">
View ticket
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
