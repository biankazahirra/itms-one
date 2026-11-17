@extends('layouts.app')

@section('title', 'Ubah Departemen')

@section('content')
    <x-page-header title="Ubah Departemen" description="{{ $department->name }}" />

    <x-session-alerts />

    <form method="POST" action="{{ route('master.departments.update', $department) }}"
          class="max-w-xl space-y-5 rounded-xl border border-line bg-surface p-6">
        @csrf
        @method('PUT')

        <x-field name="name" label="Nama Departemen" required>
            <input id="name" name="name" type="text"
                   value="{{ old('name', $department->name) }}"
                   required maxlength="150" autofocus
                   @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
                   class="input @error('name') input-error @enderror">
        </x-field>

        <fieldset>
            <legend class="mb-1.5 block text-sm font-medium">Status</legend>
            <input type="hidden" name="is_active" value="0">
            <label class="flex items-center gap-2 text-sm text-muted">
                <input type="checkbox" name="is_active" value="1"
                       @checked(old('is_active', $department->is_active))
                       class="size-4 rounded border-line text-primary focus:ring-primary/20">
                Departemen aktif dan dapat dipilih pada PR
            </label>
            @error('is_active')
                <p role="alert" class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </fieldset>

        <div class="flex items-center gap-2 border-t border-line pt-5">
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <a href="{{ route('master.departments.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
@endsection
