@php @endphp
@component('mail::message')

Beste {{ $memberName }},

Bijgaand ontvangt u factuur {{ $invoiceNumber }} van {{ $invoiceDate->format('d-m-Y') }}.

<h1>Factuur {{ $invoiceNumber }}</h1>

{{ $memberName }}<br>
{{ $email  }}<br>
{!! str_replace("\n", "<br>", $address) !!}

@component('mail::table')
| Omschrijving | Aantal | Prijs | Sub totaal |
|:---|---:|---:|---:|
@foreach ($lines as $line)
| {{ $line->description }} | {{ $line->quantity }} | {{ $line->price }} |  {{ $line->subTotal }}  |
@endforeach
@endcomponent

**Totaal: {{ $total }}**

---

@if ($total->price < 0)
U ontvangt het terug te betalen bedrag op het bij ons bekende rekeningnummer {{ $recipientIban }}
@elseif ($sepaTransferDate)
Het bedrag wordt automatisch geïncasseerd op {{ $sepaTransferDate->format('d-m-Y') }}.
@else
@include('mail.partials.transfer-instructions', [
    'amount' => $total,
    'creditorIban' => $creditorIban,
    'creditorAccountName' => $creditorAccountName,
    'invoiceNumber' => $invoiceNumber,
])
@endif

Met vriendelijke groet,<br>
Almere Centraal
@endcomponent
