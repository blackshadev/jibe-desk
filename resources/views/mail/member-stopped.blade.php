@component('mail::message')
# Lidmaatschap stopgezet

Beste {{ $memberName }},

@if ($isImmediate)
Je lidmaatschap van Watersportvereniging Almere Centraal is per direct stopgezet.
@else
Je lidmaatschap van Watersportvereniging Almere Centraal wordt stopgezet per {{ $stopDate }}.
@endif

Bedankt voor je lidmaatschap. Mocht je vragen hebben, neem dan gerust contact met ons op.

Met vriendelijke groet,<br>
Almere Centraal ledenadministratie
@endcomponent
