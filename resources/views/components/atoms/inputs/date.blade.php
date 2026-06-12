@props([ 'name', 'value' => '', 'id' => '', 'placeholder' => ''])
@php
    $id ??= $name;
    if ($value instanceof \DateTimeInterface) {
        $value = $value->format('Y-m-d');
    }
@endphp

<div
    class="flex items-center rounded-md bg-white pl-3 outline -outline-offset-1 outline-gray-300 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-primary-400">
    <input
        type="date"
        id="{{$id}}"
        name="{{$name}}"
        value="{{old($name, $value)}}"
        placeholder="{{$placeholder}}"
        class="block min-w-0 grow bg-white py-1.5 pl-1 pr-3 text-base text-gray-900 placeholder:text-gray-400 focus:outline-0 sm:text-sm/6"
    />
</div>
