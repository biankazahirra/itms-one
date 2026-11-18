@extends('layouts.app')

@section('title', 'Buat Kontrak')

@section('content')
    <x-page-header title="Buat Kontrak"
                   description="PO Number menjadi identitas kontrak. Nomor PO tidak boleh sama dengan kontrak lain." />

    <x-session-alerts />

    @include('contracts._form', [
        'action' => route('contracts.store'),
        'method' => 'POST',
        'submitLabel' => 'Simpan Kontrak',
    ])
@endsection
