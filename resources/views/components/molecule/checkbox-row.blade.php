@props([
    'name',
    'label',
    'value' => false,
    'description' => '',
])

<label class="flex gap-3">
    <div class="flex h-6 shrink-0 items-center">
        <x-atoms.inputs.checkbox
            :name="$name"
            :value="$value"
            :has-label="true"
            :has-description="filled($description)"/>
    </div>
    <div class="text-sm/6">
        <div id="{{$name}}" class="font-medium text-gray-900 ">{{$label}}</div>
        @if (filled($description))
            <p id="{{$name}}-description" class="text-gray-500">{{$description}}</p>
        @endif
        @error($name)
        <x-atoms.error>{{$message}}</x-atoms.error>
        @enderror
    </div>
</label>
