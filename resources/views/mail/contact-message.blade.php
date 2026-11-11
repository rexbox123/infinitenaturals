<x-mail::message>
# New message from the website

**From:** {{ $m->name }} ({{ $m->email }}{{ $m->phone ? ', '.$m->phone : '' }})<br>
@if ($m->subject)
**Subject:** {{ $m->subject }}<br>
@endif

{{ $m->message }}
</x-mail::message>
