@props(['type' => 'button', 'onclick' => ''])
@php
if ($type === 'back') {
    $type = 'button';
    $onclick = "history.back()";
}
@endphp

<button type="{{ $type }}" onclick="{{$onclick}}"
{{ $attributes->merge(['class' => "rounded-md bg-primary-400 px-4 py-2 font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"]) }}>
{{ $slot }}
</button>
