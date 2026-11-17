@extends('layouts.app')

@section('title', 'Ubah Satuan')

@section('content')
    <x-page-header title="Ubah Satuan" description="{{ $unit->unit_name }}" />

    <x-session-alerts />

    <form method="POST" action="{{ route('master.units.update', $unit) }}"
          class="max-w-xl space-y-5 rounded-xl border border-line bg-surface p-6">
        @csrf
        @method('PUT')

        <x-field name="unit_name" label="Nama Satuan" required>
            <input id="unit_name" name="unit_name" type="text"
                   value="{{ old('unit_name', $unit->unit_name) }}"
                   required maxlength="80" autofocus
                   @error('unit_name') aria-invalid="true" aria-describedby="unit_name-error" @enderror
                   class="input @error('unit_name') input-error @enderror">
        </x-field>

        <fieldset>
            <legend class="mb-1.5 block text-sm font-medium">Status</legend>
            <input type="hidden" name="is_active" value="0">
            <label class="flex items-center gap-2 text-sm text-muted">
                <input type="checkbox" name="is_active" value="1"
                       @checked(old('is_active', $unit->is_active))
                       class="size-4 rounded border-line text-primary focus:ring-primary/20">
                Satuan aktif dan dapat dipilih pada RAB
            </label>
            @error('is_active')
                <p role="alert" class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </fieldset>

        <div class="flex items-center gap-2 border-t border-line pt-5">
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <a href="{{ route('master.units.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
@endsection
