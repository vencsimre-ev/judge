@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Versenylapok</h1>
    <a class="btn btn-primary" href="{{ route('score-sheets.create') }}">Feltoltes</a>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kategoria</th>
                    <th>Ut / boulder</th>
                    <th>Biro</th>
                    <th>Sorok</th>
                    <th>Statusz</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($scoreSheets as $scoreSheet)
                <tr>
                    <td>#{{ $scoreSheet->id }}</td>
                    <td>{{ $scoreSheet->category ?? '-' }}</td>
                    <td>{{ $scoreSheet->route ?? '-' }}</td>
                    <td>{{ $scoreSheet->judge_name ?? '-' }}</td>
                    <td>{{ $scoreSheet->rows_count }}</td>
                    <td><span class="badge text-bg-{{ $scoreSheet->status === 'reviewed' ? 'success' : 'secondary' }}">{{ $scoreSheet->status }}</span></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('score-sheets.show', $scoreSheet) }}">Megnyitas</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted text-center py-4">Nincs meg versenylap.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($scoreSheets->hasPages())
        <div class="card-footer bg-white">{{ $scoreSheets->links() }}</div>
    @endif
</div>
@endsection
