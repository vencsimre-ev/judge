@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h3 mb-3">Versenylap feltoltese</h1>
                @php
                    $provider = $aiStatus['provider'] ?? null;
                    $model = $aiStatus['model'] ?? null;
                    $configured = (bool) ($aiStatus['configured'] ?? false);
                    $providerLabel = match ($provider) {
                        'mock' => 'Mock',
                        'openai' => 'OpenAI'.($model ? ' – '.$model : ''),
                        'ollama' => 'Helyi Ollama'.($model ? ' – '.$model : ''),
                        default => 'Ismeretlen',
                    };
                    $alertClass = $provider === 'mock' && $configured
                        ? 'warning'
                        : ($configured ? 'success' : 'danger');
                @endphp
                <div class="alert alert-{{ $alertClass }}" role="alert">
                    <div><strong>Aktív AI mód:</strong> {{ $providerLabel }}</div>
                    <div>{{ $aiStatus['message'] ?? 'Az AI provider állapota nem állapítható meg.' }}</div>
                </div>
                <form method="post" action="{{ route('score-sheets.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="image">Foto</label>
                        <input class="form-control" type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                        <div class="form-text">Tamogatott formatumok: jpg, jpeg, png, webp. Maximum 8 MB.</div>
                    </div>
                    <button class="btn btn-primary" type="submit" @disabled(! $configured)>Feltoltes es AI feldolgozas</button>
                    <a class="btn btn-outline-secondary" href="{{ route('score-sheets.index') }}">Megse</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
