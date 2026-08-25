@extends('cms-core::admin.layouts.app', ['title' => 'Criar role'])

@section('content')
    <h1 class="h3 mb-4">Criar role</h1>
    @include('cms-core::admin.roles.partials.form', ['action' => route('admin.roles.store'), 'method' => 'POST', 'role' => null])
@endsection
