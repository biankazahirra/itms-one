@extends('layouts.app')

@section('title', 'Ubah Kontrak')

@section('content')
    <x-page-header :title="'Ubah '.$contract->po_number"
                   description="{{ $contract->work_name }}" />

    <x-session-alerts />

    @include('contracts._form', [
        'action' => route('contracts.update', $contract),
        'method' => 'PUT',
        'submitLabel' => 'Simpan Perubahan',
    ])
@endsection
