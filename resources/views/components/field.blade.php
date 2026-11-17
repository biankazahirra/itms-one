@props(['name', 'label', 'required' => false, 'help' => null])

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium">
        {{ $label }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span>@endif
    </label>

    {{ $slot }}

    @if ($help)
        <p class="mt-1.5 text-xs text-muted">{{ $help }}</p>
    @endif

    @error($name)
        <p id="{{ $name }}-error" role="alert" class="mt-1.5 text-sm text-danger">{{ $message }}</p>
    @enderror
</div>
