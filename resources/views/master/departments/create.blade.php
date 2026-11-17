@extends('layouts.app')

@section('title', 'Tambah Departemen')

@section('content')
    <x-page-header title="Tambah Departemen" description="Nama departemen harus unik." />

    <x-session-alerts />

    <form method="POST" action="{{ route('master.departments.store') }}"
          class="max-w-xl space-y-5 rounded-xl border border-line bg-surface p-6">
        @csrf

        <x-field name="name" label="Nama Departemen" required>
            <input id="name" name="name" type="text" value="{{ old('name') }}"
                   required maxlength="150" autofocus
                   @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
                   class="input @error('name') input-error @enderror">
        </x-field>

        <div class="flex items-center gap-2 border-t border-line pt-5">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('master.departments.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
@endsection
