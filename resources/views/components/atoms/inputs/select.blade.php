@props([ 'name', 'options' => [], 'value' => ''])
@php
    $id ??= $name;
@endphp

<div
    class="flex items-center rounded-md bg-white pl-3 outline -outline-offset-1 outline-gray-300 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-primary-400">
    <select
        id="{{$id}}"
        name="{{$name}}"
        class="block min-w-0 grow bg-white py-1.5 pl-1 pr-3 text-base text-gray-900 placeholder:text-gray-400 focus:outline-0 sm:text-sm/6"
    >
        @foreach($options as $optionValue => $optionLabel)
            <option value="{{$optionValue}}" @selected(old($name, $value) === (string) $optionValue)>{{$optionLabel}}</option>
        @endforeach
    </select>
</div>
