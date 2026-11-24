<?php

namespace App\Modules\VideoProduction;

use App\Models\SubtitleSegment;
use App\Models\VideoProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class SubtitleGeneratorService
{
    private const STOPWORDS = [
        'yang', 'dan', 'di', 'ke', 'dari', 'untuk', 'pada', 'dengan', 'ini', 'itu',
        'tidak', 'bisa', 'akan', 'adalah', 'atau', 'juga', 'kalau', 'karena', 'agar',
        'kita', 'kamu', 'anda', 'lebih', 'sangat', 'sudah', 'harus', 'dalam', 'bikin',
    ];

    /**
     * Generate natural Indonesian phrase-level subtitles distributed proportionally by word count.
     * Timing is explicitly marked as `estimated` and editable manually in the Video Editor.
     *
     * @return Collection<int, SubtitleSegment>
     */
    public function generateForProject(VideoProject $project, string $narrationText, float $totalDurationSeconds): Collection
    {
        $phrases = $this->segmentIndonesianPhrases($narrationText);
        if (empty($phrases)) {
            $phrases = [trim($project->title) ?: 'Plant Coaction'];
        }

        $totalDuration = max(3.0, $totalDurationSeconds);
        $wordCounts = array_map(fn (string $p) => max(1, count(preg_split('/\s+/', trim($p)) ?: [])), $phrases);
        $sumWords = max(1, array_sum($wordCounts));

        $project->subtitles()->delete();

        $cursor = 0.0;
        $segments = collect();

        foreach ($phrases as $idx => $phrase) {
            $isLast = $idx === count($phrases) - 1;
            $share = ($wordCounts[$idx] / $sumWords) * $totalDuration;
            $end = $isLast ? round($totalDuration, 2) : round(min($totalDuration, $cursor + max(1.1, $share)), 2);
            if ($end <= $cursor) {
                $end = round($cursor + 0.8, 2);
            }

            $seg = SubtitleSegment::create([
                'video_project_id' => $project->id,
                'segment_order' => $idx + 1,
                'start_seconds' => round($cursor, 2),
                'end_seconds' => $end,
                'text' => $phrase,
                'highlight_words' => $this->pickHighlightWords($phrase),
                'timing_source' => 'estimated',
            ]);
            $segments->push($seg);
            $cursor = $end;
        }

        $srtRelPath = $this->exportSrt($project);
        $project->update(['subtitle_path' => $srtRelPath]);

        return $segments;
    }

    /**
     * Split narration into short readable mobile chunks (5–9 words each, respecting sentence punctuation).
     *
     * @return array<int, string>
     */
    public function segmentIndonesianPhrases(string $text): array
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', $text));
        if ($normalized === '') {
            return [];
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $normalized) ?: [$normalized];
        $chunks = [];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }

            $words = preg_split('/\s+/', $sentence) ?: [];
            if (count($words) <= 8) {
                $chunks[] = $sentence;
                continue;
            }

            $buffer = [];
            foreach ($words as $w) {
                $buffer[] = $w;
                $endsComma = str_ends_with($w, ',') || str_ends_with($w, ';');
                if (count($buffer) >= 7 || ($endsComma && count($buffer) >= 4)) {
                    $chunks[] = implode(' ', $buffer);
                    $buffer = [];
                }
            }
            if (! empty($buffer)) {
                if (count($buffer) <= 2 && ! empty($chunks)) {
                    $chunks[count($chunks) - 1] .= ' ' . implode(' ', $buffer);
                } else {
                    $chunks[] = implode(' ', $buffer);
                }
            }
        }

        return $chunks;
    }

    /** @return array<int, string> */
    public function pickHighlightWords(string $phrase): array
    {
        $words = preg_split('/\s+/', $phrase) ?: [];
        $candidates = [];
        foreach ($words as $w) {
            $clean = preg_replace('/[^a-zA-Z0-9\-]/', '', $w) ?? '';
            if (mb_strlen($clean) >= 5 && ! in_array(mb_strtolower($clean), self::STOPWORDS, true)) {
                $candidates[] = $clean;
            }
        }

        return array_slice(array_values(array_unique($candidates)), 0, 2);
    }

    public function exportSrt(VideoProject $project): string
    {
        $segments = $project->subtitles()->orderBy('segment_order')->get();
        $lines = [];

        foreach ($segments as $idx => $seg) {
            $lines[] = ($idx + 1) . "\n"
                . $this->formatSrtTime((float) $seg->start_seconds)
                . ' --> '
                . $this->formatSrtTime((float) $seg->end_seconds) . "\n"
                . trim($seg->text) . "\n";
        }

        $relPath = 'renders/subtitles/project-' . $project->id . '.srt';
        Storage::disk('local')->put($relPath, implode("\n", $lines));

        return $relPath;
    }

    private function formatSrtTime(float $sec): string
    {
        $sec = max(0.0, $sec);
        $ms = (int) round(($sec - floor($sec)) * 1000);
        $total = (int) floor($sec);
        if ($ms >= 1000) {
            $total++;
            $ms = 0;
        }
        $s = $total % 60;
        $m = intdiv($total, 60) % 60;
        $h = intdiv($total, 3600);

        return sprintf('%02d:%02d:%02d,%03d', $h, $m, $s, $ms);
    }
}
