@props([ 'back' => true ])

<div @class([
    "flex",
    "justify-between" => $back,
    "justify-end" => !$back,
])>
    @if ($back)
       <x-atoms.button class="self-start" type="back">{{__('labels.back')}}</x-atoms.button>
    @endif

    <x-atoms.button class="self-end" type="submit">{{__('labels.next')}}</x-atoms.button>
</div>
