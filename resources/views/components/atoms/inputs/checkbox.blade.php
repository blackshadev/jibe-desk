@props(['id', 'name', 'value' => false, 'hasDescription' => false, 'hasLabel' => false])
@php
    $id ??= $name;
@endphp

<div class="grid size-4 grid-cols-1 group">
    <input
        id="{{$id}}"
        type="checkbox"
        name="{{$name}}"
        @if ($hasLabel) aria-label="{{$name}}" @endif
        @if ($hasDescription) aria-describedby="{{$name}}-description" @endif
        @checked(old($name, $value))
        value="1"
        class="col-start-1 row-start-1 appearance-none rounded border border-gray-300 bg-white checked:border-primary-400 checked:bg-primary-400 indeterminate:border-primary-400 indeterminate:bg-primary-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-400 disabled:border-gray-300 disabled:bg-gray-100 disabled:checked:bg-gray-100 forced-colors:appearance-auto"/>
    <svg viewBox="0 0 14 14" fill="none"
         class="pointer-events-none col-start-1 row-start-1 size-3.5 self-center justify-self-center stroke-white group-has-[:disabled]:stroke-gray-950/25 dark:group-has-[:disabled]:stroke-white/25">
        <path d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
              class="opacity-0 group-has-[:checked]:opacity-100"/>
        <path d="M3 7H11" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
              class="opacity-0 group-has-[:indeterminate]:opacity-100"/>
    </svg>
</div>
