@component('mail::message')
# Nieuwe aanmelding

Er heeft zich een nieuw lid aangemeld:

**Naam:** {{ $memberName }}

**Aanmeldgegevens:**
- Reguliere surflessen: {{ $membershipData->regularWindsurfingLessons ? 'Ja' : 'Nee' }}
- RTC: {{ $membershipData->rtc ? 'Ja' : 'Nee' }}
- Clubhuis toegang: {{ $membershipData->clubhouseAccess ? 'Ja' : 'Nee' }}
- Board opslag: {{ $membershipData->boardStorage ? 'Ja' : 'Nee' }}
- Watersportbond nummer: {{ $membershipData->watersportFederationNumber ?: 'Niet opgegeven' }}

@component('mail::button', ['url' => $editUrl, 'color' => 'primary'])
Bekijk lid in administratie
@endcomponent

Met vriendelijke groet,<br>
Almere Centraal ledenadministratie
@endcomponent
