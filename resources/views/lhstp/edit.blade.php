@extends('layouts.app')

@section('title', 'Ubah LHSTP')

@section('content')
    <x-page-header :back="route('lhstp.index')" :title="'Ubah '.$lhstp->lhstp_number"
                   :description="'Draft '.$version?->label().'. Perubahan menimpa versi Draft ini.'" />

    <x-session-alerts />

    @include('lhstp._form', [
        'action' => route('lhstp.update', $lhstp),
        'method' => 'PUT',
    ])
@endsection
