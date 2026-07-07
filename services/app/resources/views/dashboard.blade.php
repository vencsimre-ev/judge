@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Dashboard</h1>
        <p class="text-muted mb-0">Versenybiroi lapok feltoltese, AI feldolgozasa es javitasa.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('score-sheets.create') }}">Uj versenylap</a>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">Legutobbi versenylapok</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kategoria</th>
                    <th>Ut / boulder</th>
                    <th>Statusz</th>
                    <th>Datum</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($scoreSheets as $scoreSheet)
                    <tr>
                        <td>#{{ $scoreSheet->id }}</td>
                        <td>{{ $scoreSheet->category ?? '-' }}</td>
                        <td>{{ $scoreSheet->route ?? '-' }}</td>
                        <td><span class="badge text-bg-{{ $scoreSheet->status === 'reviewed' ? 'success' : 'secondary' }}">{{ $scoreSheet->status }}</span></td>
                        <td>{{ $scoreSheet->created_at->format('Y-m-d H:i') }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('score-sheets.show', $scoreSheet) }}">Megnyitas</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted text-center py-4">Meg nincs feltoltott versenylap.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
