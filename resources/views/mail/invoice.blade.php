@php @endphp
@component('mail::message')

    Beste {{ $memberName }},

    Bijgaand ontvangt u factuur {{ $invoiceNumber }} van {{ $invoiceDate->format('d-m-Y') }}.

    # Factuur {{ $invoiceNumber }}
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
        Het bedrag dient u over te maken naar rekeningnummer **{{ $creditorIban }}**
        ten name van **{{ $creditorAccountName }}**
        onder vermelding van **factuurnummer {{ $invoiceNumber }}**.
    @endif

    Met vriendelijke groet,<br>
    Almere Centraal
@endcomponent
