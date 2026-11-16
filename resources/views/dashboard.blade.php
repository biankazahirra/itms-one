@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <section class="rounded-xl border border-line bg-surface p-6">
        <h2 class="text-lg font-semibold tracking-tight">Selamat datang, {{ auth()->user()->name }}</h2>
        <p class="mt-1 max-w-prose text-sm text-muted">Ringkasan administrasi ITMS akan ditampilkan di halaman ini.</p>
    </section>
@endsection
