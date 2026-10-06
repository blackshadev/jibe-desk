@component('mail::message')
# Welkom bij Almere Centraal!

Beste {{ $memberName }},

Wat leuk dat je lid bent geworden van Watersportvereniging Almere Centraal!

We hebben een account voor je aangemaakt. Klik op onderstaande knop om je wachtwoord in te stellen en in te loggen op je persoonlijke omgeving.

@component('mail::button', ['url' => $setPasswordUrl])
Stel wachtwoord in
@endcomponent

Mocht je in de tussentijd vragen hebben, neem dan gerust contact met ons op.

Met vriendelijke groet,<br>
Almere Centraal ledenadministratie
@endcomponent
