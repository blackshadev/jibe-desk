@component('mail::message')

Beste {{ $memberName }},

De automatische incasso van factuur {{ $invoiceNumber }} van {{ $invoiceDate->format('d-m-Y') }} is niet gelukt. Het bedrag van {{ $total }} is van uw rekening teruggeboekt.

**Wat kunt u doen?**

@include('mail.partials.transfer-instructions', [
    'amount' => $total,
    'creditorIban' => $creditorIban,
    'creditorAccountName' => $creditorAccountName,
    'invoiceNumber' => $invoiceNumber,
])

@if ($mandateNeedsReauthorisation)
Uw machtiging voor automatische incasso is ingetrokken of verlopen. Neem contact op met de ledenadministratie om een nieuwe machtiging af te geven. Zodra wij die hebben ontvangen, kunnen wij het bedrag opnieuw incasseren.
@else
Wilt u het bedrag liever opnieuw laten incasseren? Neem dan contact op met de ledenadministratie.
@endif

Met vriendelijke groet,<br>
Almere Centraal
@endcomponent
