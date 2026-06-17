@php use App\Formatters\PriceFormatter; @endphp
@component('mail::message')
# Factuur {{ $invoiceNumber }}

Beste {{ $memberName }},

Bijgaand ontvangt u factuur {{ $invoiceNumber }} van {{ $invoiceDate }}.

## Overzicht

@component('mail::table')
| Omschrijving | Aantal | Prijs | Sub totaal |
|:---|---:|---:|---:|
@foreach ($lines as $line)
| {{ $line->description }} | {{ $line->quantity }} | {{ PriceFormatter::format($line->price->price) }} |  {{ PriceFormatter::format($line->subTotal->price) }}  |
@endforeach
@endcomponent

---

**Totaal: {{ $total }}**

@if ($sepaTransferDate)
Het bedrag wordt automatisch geïncasseerd op {{ $sepaTransferDate }}.
@endif

Met vriendelijke groet,<br>
Almere Centraal
@endcomponent
