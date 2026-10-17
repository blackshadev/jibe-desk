@component('mail::message')
# Nieuwe SEPA ronde aangemaakt

Er is een nieuwe SEPA ronde aangemaakt.

**SEPA ronde:** {{ $batchId }}<br>
**Verrekeningsdatum:** {{ $batchDate->format('d-m-Y') }}<br>
**Aantal facturen:** {{ $invoiceCount }}<br>
**Totaalbedrag:** {{ $invoiceTotal }}<br>

@component('mail::button', ['url' => $batchUrl, 'color' => 'primary'])
Bekijk SEPA ronde
@endcomponent

Met vriendelijke groet,<br>
Almere Centraal ledenadministratie
@endcomponent
