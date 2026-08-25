@extends('cms-core::admin.layouts.app', ['title' => 'Criar utilizador'])

@section('content')
    <h1 class="h3 mb-4">Criar utilizador</h1>
    @include('cms-core::admin.users.partials.form', ['action' => route('admin.users.store'), 'method' => 'POST', 'user' => null])
@endsection
