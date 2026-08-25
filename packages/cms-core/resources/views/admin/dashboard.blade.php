@extends('cms-core::admin.layouts.app', ['title' => 'Painel de Administração'])

@section('content')
    <div class="bg-white border rounded-2 p-4">
        <h1 class="h3 mb-3">Painel de Administração</h1>
        <p class="mb-1">Bem-vindo, {{ auth()->user()->name }}.</p>
        <p class="text-secondary mb-0">Sessão iniciada com o email {{ auth()->user()->email }}.</p>
    </div>
@endsection
