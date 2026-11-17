@extends('layouts.app')

@section('title', 'Tambah Satuan')

@section('content')
    <x-page-header title="Tambah Satuan" description="Nama satuan harus unik." />

    <x-session-alerts />

    <form method="POST" action="{{ route('master.units.store') }}"
          class="max-w-xl space-y-5 rounded-xl border border-line bg-surface p-6">
        @csrf

        <x-field name="unit_name" label="Nama Satuan" required help="Contoh: Unit, Paket, Bulan, Orang.">
            <input id="unit_name" name="unit_name" type="text" value="{{ old('unit_name') }}"
                   required maxlength="80" autofocus
                   @error('unit_name') aria-invalid="true" aria-describedby="unit_name-error" @enderror
                   class="input @error('unit_name') input-error @enderror">
        </x-field>

        <div class="flex items-center gap-2 border-t border-line pt-5">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('master.units.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
@endsection
