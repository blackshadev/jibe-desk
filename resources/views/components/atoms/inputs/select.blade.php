@props([ 'name', 'options' => [], 'value' => ''])
@php
    $id ??= $name;
    $oldValue = old($name, $value);
    if ($oldValue instanceof UnitEnum) {
        $oldValue = $oldValue->value;
    }
@endphp

<div
    class="flex items-center rounded-md bg-white pl-3 outline -outline-offset-1 outline-gray-300 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-primary-400">
    <select
        id="{{$id}}"
        name="{{$name}}"
        class="block min-w-0 grow bg-white py-1.5 pl-1 pr-3 text-base text-gray-900 placeholder:text-gray-400 focus:outline-0 sm:text-sm/6"
    >
        <option disabled @selected($oldValue === '')>{{__('labels.select_option')}}</option>
        @foreach($options as $optionValue => $optionLabel)
            <option
                value="{{$optionValue}}"
                @selected($oldValue === (string) $optionValue)
            >
                {{$optionLabel}}
            </option>
        @endforeach
    </select>
</div>
