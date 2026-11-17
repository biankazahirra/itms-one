@extends('layouts.app')

@section('title', 'Ubah Draft RAB')

@section('content')
    <x-page-header title="Ubah Draft RAB"
                   description="Hanya versi Draft yang dapat diubah. Item hasil impor PR tetap dapat diedit." />

    <x-session-alerts />

    @include('rab._form', [
        'action' => route('rab.update', $budget),
        'method' => 'PUT',
        'units' => $units,
        'prOptions' => null,
        'prefillPr' => null,
        'linkedPrTotal' => $linkedPrTotal,
        'isEdit' => true,
        'submitLabel' => 'Simpan Draft',
        'budget' => $budget,
    ])
@endsection
