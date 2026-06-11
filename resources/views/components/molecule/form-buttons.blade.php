@props([ 'back' => null, 'nextLabel' => null ])
@php
    $nextLabel = $nextLabel ?? __('labels.next');
@endphp

<div @class([
    "flex",
    "justify-between" => $back,
    "justify-end" => !$back,
])>
    @if ($back)
       <x-atoms.button class="self-start" url="{{$back}}">{{__('labels.back')}}</x-atoms.button>
    @endif

    <x-atoms.button class="self-end" type="submit">{{$nextLabel}}</x-atoms.button>
</div>
