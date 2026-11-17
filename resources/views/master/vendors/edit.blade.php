@extends('layouts.app')

@section('title', 'Ubah Vendor')

@section('content')
    <x-page-header title="Ubah Vendor" description="{{ $vendor->company_name }}"></x-page-header>

    <x-session-alerts />

    <form method="POST" action="{{ route('master.vendors.update', $vendor) }}"
          class="max-w-2xl space-y-5 rounded-xl border border-line bg-surface p-6">
        @csrf
        @method('PUT')

        <x-field name="company_name" label="Nama Perusahaan" required>
            <input id="company_name" name="company_name" type="text"
                   value="{{ old('company_name', $vendor->company_name) }}"
                   required maxlength="200" autofocus
                   @error('company_name') aria-invalid="true" aria-describedby="company_name-error" @enderror
                   class="input @error('company_name') input-error @enderror">
        </x-field>

        <x-field name="company_address" label="Alamat Perusahaan" required>
            <textarea id="company_address" name="company_address" rows="3" required maxlength="5000"
                      @error('company_address') aria-invalid="true" aria-describedby="company_address-error" @enderror
                      class="input h-auto py-2 @error('company_address') input-error @enderror">{{ old('company_address', $vendor->company_address) }}</textarea>
        </x-field>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="npwp" label="NPWP" help="Opsional. Nomor pajak perusahaan.">
                <input id="npwp" name="npwp" type="text" value="{{ old('npwp', $vendor->npwp) }}" maxlength="40"
                       class="input @error('npwp') input-error @enderror">
            </x-field>

            <x-field name="phone" label="Telepon">
                <input id="phone" name="phone" type="text" value="{{ old('phone', $vendor->phone) }}" maxlength="40"
                       class="input @error('phone') input-error @enderror">
            </x-field>
        </div>

        <x-field name="email" label="Email">
            <input id="email" name="email" type="email" value="{{ old('email', $vendor->email) }}" maxlength="190"
                   class="input @error('email') input-error @enderror">
        </x-field>

        <fieldset>
            <legend class="mb-1.5 block text-sm font-medium">Status</legend>
            <input type="hidden" name="is_active" value="0">
            <label class="flex items-center gap-2 text-sm text-muted">
                <input type="checkbox" name="is_active" value="1"
                       @checked(old('is_active', $vendor->is_active))
                       class="size-4 rounded border-line text-primary focus:ring-primary/20">
                Vendor aktif dan dapat dipilih pada kontrak
            </label>
            @error('is_active')
                <p role="alert" class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </fieldset>

        @if (session('duplicate_warning'))
            <label class="flex items-start gap-2 rounded-lg border border-line bg-app px-3 py-2.5 text-sm">
                <input type="checkbox" name="confirm_duplicate" value="1" @checked(old('confirm_duplicate')) class="mt-0.5 size-4 rounded border-line text-primary focus:ring-primary/20">
                <span>Tetap simpan meskipun nama perusahaan mirip dengan vendor lain.</span>
            </label>
        @endif

        <div class="flex items-center gap-2 border-t border-line pt-5">
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <a href="{{ route('master.vendors.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
@endsection
