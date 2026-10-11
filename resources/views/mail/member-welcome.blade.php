@component('mail::message')
# Welkom als lid!

Beste {{ $memberName }},

Gefeliciteerd! Je bent nu lid van Watersportvereniging Almere Centraal.

We hebben een account voor je aangemaakt. Stel hieronder je wachtwoord in om in te loggen op je persoonlijke omgeving.

@component('mail::button', ['url' => $setPasswordUrl])
Stel wachtwoord in
@endcomponent

Inloggen kan via: {{ $loginUrl }}

Mocht je vragen hebben, neem dan gerust contact met ons op.

Met vriendelijke groet,<br>
Almere Centraal ledenadministratie
@endcomponent
