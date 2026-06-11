@props(['label','name' => null, 'description' => null])
<div class="flex flex-col gap-2 flex-1">
    <label for="username" class="block text-sm/6 font-medium text-gray-900 ">{{$label}}</label>
    <div class="gap-1">
        <div>
            {{$slot}}
        </div>

        @if (!empty($description))
            <p class="text-gray-700 text-xs">
                {{$description}}
            </p>
        @endif
        @if (!empty($name))
            @error($name)
            <x-atoms.error>{{$message}}</x-atoms.error>
            @enderror
        @endif
    </div>
</div>
