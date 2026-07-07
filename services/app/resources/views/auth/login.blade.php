@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h3 mb-3">Bejelentkezes</h1>
                <p class="text-muted">A demo Google OAuth bejelentkezest hasznal, igy minden versenylap a sajat felhasznalodhoz kerul.</p>
                <a class="btn btn-primary w-100" href="{{ route('auth.google') }}">Bejelentkezes Google fiokkal</a>
            </div>
        </div>
    </div>
</div>
@endsection
