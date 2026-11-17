@extends('layouts.app')

@section('title', 'Buat PR')

@section('content')
    <x-page-header title="Buat Purchase Requisition"
                   description="PR disimpan sebagai Draft dan dapat difinalisasi setelah data lengkap." />

    <x-session-alerts />

    @include('purchase-requests._form', [
        'purchaseRequest' => $purchaseRequest,
        'departments' => $departments,
        'action' => route('purchase-requests.store'),
        'method' => 'POST',
        'submitLabel' => 'Simpan Draft',
    ])
@endsection
