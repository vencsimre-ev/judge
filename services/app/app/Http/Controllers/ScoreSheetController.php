<?php

namespace App\Http\Controllers;

use App\Models\ScoreSheet;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ScoreSheetController extends Controller
{
    public function index()
    {
        $scoreSheets = Auth::user()
            ->scoreSheets()
            ->withCount('rows')
            ->latest()
            ->paginate(15);

        return view('score_sheets.index', compact('scoreSheets'));
    }

    public function create()
    {
        return view('score_sheets.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $image = $validated['image'];
        $imagePath = $image->store('score-sheets', 'public');

        try {
            $response = Http::timeout(120)
                ->attach('image', file_get_contents($image->getRealPath()), $image->getClientOriginalName())
                ->post(config('services.ai.url'));

            $response->throw();
            $aiJson = $response->json();
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($imagePath);

            return back()
                ->withInput()
                ->with('error', 'Az AI service hibaval tert vissza: '.$exception->getMessage());
        }

        $sheet = $aiJson['sheet'] ?? [];
        $scoreSheet = ScoreSheet::create([
            'user_id' => Auth::id(),
            'category' => $sheet['category'] ?? null,
            'route' => $sheet['route'] ?? null,
            'judge_name' => $sheet['judge_name'] ?? null,
            'image_path' => $imagePath,
            'raw_ai_json' => $aiJson,
            'status' => 'draft',
        ]);

        foreach (($aiJson['rows'] ?? []) as $row) {
            $scoreSheet->rows()->create([
                'row_number' => $row['row_number'] ?? 0,
                'start_time' => $row['start_time'] ?? null,
                'bib' => $row['bib'] ?? null,
                'name' => $row['name'] ?? null,
                'country' => $row['country'] ?? null,
                'attempts_raw' => $row['attempts_raw'] ?? null,
                'attempts_count' => $row['attempts_count'] ?? null,
                'zone_attempt' => $row['zone_attempt'] ?? null,
                'top_attempt' => $row['top_attempt'] ?? null,
                'zone_column_value' => $row['zone_column_value'] ?? null,
                'top_column_value' => $row['top_column_value'] ?? null,
                'confidence' => $row['confidence'] ?? null,
                'warnings' => $row['warnings'] ?? [],
            ]);
        }

        return redirect()->route('score-sheets.show', $scoreSheet)
            ->with('success', 'A versenylap feldolgozasa elkeszult.');
    }

    public function show(ScoreSheet $scoreSheet)
    {
        $this->authorizeOwner($scoreSheet);

        $scoreSheet->load('rows');

        return view('score_sheets.show', compact('scoreSheet'));
    }

    public function edit(ScoreSheet $scoreSheet)
    {
        $this->authorizeOwner($scoreSheet);

        $scoreSheet->load('rows');

        return view('score_sheets.edit', compact('scoreSheet'));
    }

    public function update(Request $request, ScoreSheet $scoreSheet): RedirectResponse
    {
        $this->authorizeOwner($scoreSheet);

        $validated = $request->validate([
            'category' => ['nullable', 'string', 'max:255'],
            'route' => ['nullable', 'string', 'max:255'],
            'judge_name' => ['nullable', 'string', 'max:255'],
            'rows' => ['array'],
            'rows.*.bib' => ['nullable', 'string', 'max:255'],
            'rows.*.name' => ['nullable', 'string', 'max:255'],
            'rows.*.country' => ['nullable', 'string', 'max:255'],
            'rows.*.attempts_raw' => ['nullable', 'string', 'max:255'],
            'rows.*.zone_attempt' => ['nullable', 'integer', 'min:1'],
            'rows.*.top_attempt' => ['nullable', 'integer', 'min:1'],
            'rows.*.confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $scoreSheet->update([
            'category' => $validated['category'] ?? null,
            'route' => $validated['route'] ?? null,
            'judge_name' => $validated['judge_name'] ?? null,
            'status' => 'reviewed',
        ]);

        foreach (($validated['rows'] ?? []) as $rowId => $rowData) {
            $row = $scoreSheet->rows()->whereKey($rowId)->first();
            if (! $row) {
                continue;
            }

            $attemptsRaw = $rowData['attempts_raw'] ?? null;
            $row->update([
                'bib' => $rowData['bib'] ?? null,
                'name' => $rowData['name'] ?? null,
                'country' => $rowData['country'] ?? null,
                'attempts_raw' => $attemptsRaw,
                'attempts_count' => $attemptsRaw ? mb_strlen($attemptsRaw) : null,
                'zone_attempt' => $rowData['zone_attempt'] ?? null,
                'top_attempt' => $rowData['top_attempt'] ?? null,
                'confidence' => $rowData['confidence'] ?? null,
            ]);
        }

        return redirect()->route('score-sheets.show', $scoreSheet)
            ->with('success', 'A javitasok mentve lettek.');
    }

    public function exportJson(ScoreSheet $scoreSheet)
    {
        $this->authorizeOwner($scoreSheet);

        return response()->json([
            'score_sheet' => $scoreSheet->load('rows'),
        ])->header('Content-Disposition', 'attachment; filename="score-sheet-'.$scoreSheet->id.'.json"');
    }

    public function exportCsv(ScoreSheet $scoreSheet): StreamedResponse
    {
        $this->authorizeOwner($scoreSheet);

        $scoreSheet->load('rows');

        return response()->streamDownload(function () use ($scoreSheet) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'row_number',
                'start_time',
                'bib',
                'name',
                'country',
                'attempts_raw',
                'attempts_count',
                'zone_attempt',
                'top_attempt',
            ]);

            foreach ($scoreSheet->rows as $row) {
                fputcsv($handle, [
                    $row->row_number,
                    $row->start_time,
                    $row->bib,
                    $row->name,
                    $row->country,
                    $row->attempts_raw,
                    $row->attempts_count,
                    $row->zone_attempt,
                    $row->top_attempt,
                ]);
            }

            fclose($handle);
        }, 'score-sheet-'.$scoreSheet->id.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function authorizeOwner(ScoreSheet $scoreSheet): void
    {
        abort_unless($scoreSheet->user_id === Auth::id(), 403);
    }
}
