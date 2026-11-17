@extends('layouts.app')

@section('title', 'Tambah Vendor')

@section('content')
    <x-page-header title="Tambah Vendor"
                   description="Lengkapi data perusahaan. Hubungi bagian administrasi bila data vendor sudah terdaftar." />

    <x-session-alerts />

    <form method="POST" action="{{ route('master.vendors.store') }}"
          class="max-w-2xl space-y-5 rounded-xl border border-line bg-surface p-6">
        @csrf

        <x-field name="company_name" label="Nama Perusahaan" required>
            <input id="company_name" name="company_name" type="text" value="{{ old('company_name') }}"
                   required maxlength="200" autofocus
                   @error('company_name') aria-invalid="true" aria-describedby="company_name-error" @enderror
                   class="input @error('company_name') input-error @enderror">
        </x-field>

        <x-field name="company_address" label="Alamat Perusahaan" required>
            <textarea id="company_address" name="company_address" rows="3" required maxlength="5000"
                      @error('company_address') aria-invalid="true" aria-describedby="company_address-error" @enderror
                      class="input h-auto py-2 @error('company_address') input-error @enderror">{{ old('company_address') }}</textarea>
        </x-field>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="npwp" label="NPWP" help="Opsional. Nomor pajak perusahaan.">
                <input id="npwp" name="npwp" type="text" value="{{ old('npwp') }}" maxlength="40"
                       class="input @error('npwp') input-error @enderror">
            </x-field>

            <x-field name="phone" label="Telepon">
                <input id="phone" name="phone" type="text" value="{{ old('phone') }}" maxlength="40"
                       class="input @error('phone') input-error @enderror">
            </x-field>
        </div>

        <x-field name="email" label="Email">
            <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="190"
                   class="input @error('email') input-error @enderror">
        </x-field>

        @if (session('duplicate_warning'))
            <label class="flex items-start gap-2 rounded-lg border border-line bg-app px-3 py-2.5 text-sm">
                <input type="checkbox" name="confirm_duplicate" value="1" @checked(old('confirm_duplicate')) class="mt-0.5 size-4 rounded border-line text-primary focus:ring-primary/20">
                <span>Tetap simpan meskipun nama perusahaan mirip dengan vendor lain.</span>
            </label>
        @endif

        <div class="flex items-center gap-2 border-t border-line pt-5">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('master.vendors.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
@endsection
