@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1">Versenylap #{{ $scoreSheet->id }}</h1>
        <p class="text-muted mb-0">{{ $scoreSheet->category ?? 'Nincs kategoria' }} · {{ $scoreSheet->route ?? 'Nincs ut' }}</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('score-sheets.export.json', $scoreSheet) }}">JSON export</a>
        <a class="btn btn-outline-secondary" href="{{ route('score-sheets.export.csv', $scoreSheet) }}">CSV export</a>
        <a class="btn btn-primary" href="{{ route('score-sheets.edit', $scoreSheet) }}">Javitas</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white">Feltoltott kep</div>
            <img class="img-fluid sheet-image w-100" src="{{ asset('storage/'.$scoreSheet->image_path) }}" alt="Feltoltott versenylap">
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">Nyers AI JSON</div>
            <div class="card-body">
                <pre class="small mb-0">{{ json_encode($scoreSheet->raw_ai_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">Feldolgozott sorok</div>
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Ido</th>
                    <th>Rajtszam</th>
                    <th>Nev</th>
                    <th>Orszag</th>
                    <th>Probak</th>
                    <th>Zone</th>
                    <th>Top</th>
                    <th>Biz.</th>
                    <th>Warnings</th>
                </tr>
            </thead>
            <tbody>
            @foreach($scoreSheet->rows as $row)
                <tr>
                    <td>{{ $row->row_number }}</td>
                    <td>{{ $row->start_time ?? '-' }}</td>
                    <td>{{ $row->bib ?? '-' }}</td>
                    <td>{{ $row->name ?? '-' }}</td>
                    <td>{{ $row->country ?? '-' }}</td>
                    <td>{{ $row->attempts_raw ?? '-' }}</td>
                    <td>{{ $row->zone_attempt ?? '-' }}</td>
                    <td>{{ $row->top_attempt ?? '-' }}</td>
                    <td>{{ $row->confidence ?? '-' }}</td>
                    <td>
                        @foreach(($row->warnings ?? []) as $warning)
                            <span class="badge text-bg-warning">{{ $warning }}</span>
                        @endforeach
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
