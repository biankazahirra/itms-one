@extends('layouts.app')

@section('title', 'Ubah PR')

@section('content')
    <x-page-header title="Ubah PR {{ $purchaseRequest->pr_number }}"
                   description="Perubahan hanya berlaku pada versi Draft saat ini." />

    <x-session-alerts />

    @include('purchase-requests._form', [
        'purchaseRequest' => $purchaseRequest,
        'departments' => $departments,
        'action' => route('purchase-requests.update', $purchaseRequest),
        'method' => 'PUT',
        'submitLabel' => 'Simpan Perubahan',
    ])
@endsection
