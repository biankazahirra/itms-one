@extends('layouts.app')

@section('title', 'Buat RAB')

@section('content')
    <x-page-header :back="route('rab.index')" title="Buat RAB"
                   description="Buat RAB mandiri, atau impor item dari satu PR tersimpan." />

    <x-session-alerts />

    @include('rab._form', [
        'action' => route('rab.store'),
        'method' => 'POST',
        'units' => $units,
        'prOptions' => $prOptions,
        'prefillPr' => $prefillPr,
        'linkedPrTotal' => null,
        'isEdit' => false,
        'submitLabel' => 'Simpan Draft',
        'budget' => $budget,
    ])
@endsection
