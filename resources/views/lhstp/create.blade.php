@extends('layouts.app')

@section('title', 'Buat LHSTP')

@section('content')
    <x-page-header :back="route('lhstp.index')" title="Buat LHSTP"
                   description="Pilih kontrak lebih dulu. Data kontrak diisi otomatis dan tetap dapat disesuaikan sebelum disimpan." />

    <x-session-alerts />

    @include('lhstp._form', [
        'action' => route('lhstp.store'),
        'method' => 'POST',
    ])
@endsection
