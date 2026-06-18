@component('mail::message')
# Nieuwe facturatieronde aangemaakt

Er is een nieuwe facturatieronde aangemaakt.

**Facturatieronde:** {{ $batchId }}<br>
**Verrekeningsdatum:** {{ $batchDate->format('d-m-Y') }}<br>
**Aantal facturen:** {{ $invoiceCount }}<br>
**Totaalbedrag:** {{ $invoiceTotal }}<br>

@component('mail::button', ['url' => $batchUrl, 'color' => 'primary'])
Bekijk facturatieronde
@endcomponent

Met vriendelijke groet,<br>
Almere Centraal ledenadministratie
@endcomponent
