@extends('layouts.app')

@section('content')
<form method="post" action="{{ route('score-sheets.update', $scoreSheet) }}">
    @csrf
    @method('PUT')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Versenylap javitasa #{{ $scoreSheet->id }}</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('score-sheets.show', $scoreSheet) }}">Megse</a>
            <button class="btn btn-primary" type="submit">Mentes</button>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Kategoria</label>
                <input class="form-control" name="category" value="{{ old('category', $scoreSheet->category) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Ut / boulder</label>
                <input class="form-control" name="route" value="{{ old('route', $scoreSheet->route) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Biro neve</label>
                <input class="form-control" name="judge_name" value="{{ old('judge_name', $scoreSheet->judge_name) }}">
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">Sorok</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
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
                        <td><input class="form-control form-control-sm" name="rows[{{ $row->id }}][bib]" value="{{ old("rows.{$row->id}.bib", $row->bib) }}"></td>
                        <td><input class="form-control form-control-sm" name="rows[{{ $row->id }}][name]" value="{{ old("rows.{$row->id}.name", $row->name) }}"></td>
                        <td><input class="form-control form-control-sm" name="rows[{{ $row->id }}][country]" value="{{ old("rows.{$row->id}.country", $row->country) }}"></td>
                        <td><input class="form-control form-control-sm" name="rows[{{ $row->id }}][attempts_raw]" value="{{ old("rows.{$row->id}.attempts_raw", $row->attempts_raw) }}"></td>
                        <td><input class="form-control form-control-sm" type="number" min="1" name="rows[{{ $row->id }}][zone_attempt]" value="{{ old("rows.{$row->id}.zone_attempt", $row->zone_attempt) }}"></td>
                        <td><input class="form-control form-control-sm" type="number" min="1" name="rows[{{ $row->id }}][top_attempt]" value="{{ old("rows.{$row->id}.top_attempt", $row->top_attempt) }}"></td>
                        <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" max="1" name="rows[{{ $row->id }}][confidence]" value="{{ old("rows.{$row->id}.confidence", $row->confidence) }}"></td>
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
</form>
@endsection
