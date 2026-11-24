<?php

namespace App\Modules\TextToSpeech;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class AudioConverterService
{
    /**
     * Decode Base64 audio payload from Gemini TTS, validate bytes, convert via FFmpeg to WAV (master) & MP3 (preview).
     *
     * @return array{
     *     ok: bool,
     *     wav_path?: string,
     *     mp3_path?: string,
     *     mp3_base64?: string,
     *     mime_type?: string,
     *     duration_seconds?: float,
     *     sample_rate?: int,
     *     channels?: int,
     *     wav_size_bytes?: int,
     *     mp3_size_bytes?: int,
     *     message?: string
     * }
     */
    public function decodeAndConvert(string $base64Audio, string $sourceMimeType, string $subdir = 'tts/previews'): array
    {
        $cleanBase64 = preg_replace('/\s+/', '', $base64Audio) ?? '';
        if ($cleanBase64 === '') {
            return ['ok' => false, 'message' => 'Data audio dari Gemini TTS kosong.'];
        }

        $rawBytes = base64_decode($cleanBase64, true);
        if ($rawBytes === false || strlen($rawBytes) < 64) {
            return ['ok' => false, 'message' => 'Response audio dari Gemini TTS tidak valid atau rusak (corrupt base64/payload terlalu kecil).'];
        }

        $maxBytes = 30 * 1024 * 1024; // 30 MB safety limit
        if (strlen($rawBytes) > $maxBytes) {
            return ['ok' => false, 'message' => 'Ukuran file audio melebihi batas keamanan 30 MB.'];
        }

        $parsed = $this->parseMimeType($sourceMimeType);
        $uuid = Str::uuid()->toString();
        $disk = Storage::disk('local');

        $tmpDir = 'tts/tmp';
        $masterDir = 'tts/masters';
        $previewDir = trim($subdir, '/');

        try {
            $disk->makeDirectory($tmpDir);
            $disk->makeDirectory($masterDir);
            $disk->makeDirectory($previewDir);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal menyiapkan direktori penyimpanan audio: ' . $e->getMessage()];
        }

        $tmpRel = "{$tmpDir}/{$uuid}." . $parsed['ext'];
        $wavRel = "{$masterDir}/gemini-{$uuid}.wav";
        $mp3Rel = "{$previewDir}/gemini-{$uuid}.mp3";

        if (! $disk->put($tmpRel, $rawBytes)) {
            return ['ok' => false, 'message' => 'Gagal menyimpan file audio sementara ke storage.'];
        }

        $tmpAbs = $disk->path($tmpRel);
        $wavAbs = $disk->path($wavRel);
        $mp3Abs = $disk->path($mp3Rel);
        $ffmpeg = (string) config('affiliate_ai.video.ffmpeg_binary', 'ffmpeg');

        try {
            // 1. Convert source (PCM L16 24kHz mono / WAV / MP3) -> Master WAV (Remotion-ready)
            $wavCmd = $parsed['is_raw_pcm']
                ? [$ffmpeg, '-y', '-f', 's16le', '-ar', (string) $parsed['rate'], '-ac', (string) $parsed['channels'], '-i', $tmpAbs, '-c:a', 'pcm_s16le', $wavAbs]
                : [$ffmpeg, '-y', '-i', $tmpAbs, '-ar', (string) $parsed['rate'], '-ac', (string) $parsed['channels'], '-c:a', 'pcm_s16le', $wavAbs];

            $wavProc = new Process($wavCmd);
            $wavProc->setTimeout(60);
            $wavProc->run();

            if (! $wavProc->isSuccessful() || ! is_file($wavAbs) || filesize($wavAbs) < 44) {
                return [
                    'ok' => false,
                    'message' => 'FFmpeg gagal mengonversi audio ke format WAV master: ' . trim($wavProc->getErrorOutput()),
                ];
            }

            // 2. Convert Master WAV -> Preview MP3 (browser playable)
            $mp3Cmd = [$ffmpeg, '-y', '-i', $wavAbs, '-codec:a', 'libmp3lame', '-b:a', '128k', $mp3Abs];
            $mp3Proc = new Process($mp3Cmd);
            $mp3Proc->setTimeout(60);
            $mp3Proc->run();

            if (! $mp3Proc->isSuccessful() || ! is_file($mp3Abs) || filesize($mp3Abs) < 128) {
                return [
                    'ok' => false,
                    'message' => 'FFmpeg gagal mengonversi audio ke format MP3 preview: ' . trim($mp3Proc->getErrorOutput()),
                ];
            }

            // 3. Probe metadata via ffprobe (duration, sample_rate, channels)
            $probe = $this->probeAudio($wavAbs);
            $mp3Bytes = file_get_contents($mp3Abs);

            return [
                'ok' => true,
                'wav_path' => $wavRel,
                'mp3_path' => $mp3Rel,
                'mp3_base64' => $mp3Bytes !== false ? base64_encode($mp3Bytes) : '',
                'mime_type' => 'audio/mpeg',
                'duration_seconds' => $probe['duration_seconds'],
                'sample_rate' => $probe['sample_rate'] ?: $parsed['rate'],
                'channels' => $probe['channels'] ?: $parsed['channels'],
                'wav_size_bytes' => (int) filesize($wavAbs),
                'mp3_size_bytes' => (int) filesize($mp3Abs),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal memproses audio dengan FFmpeg: ' . $e->getMessage()];
        } finally {
            $disk->delete($tmpRel);
        }
    }

    /**
     * Cleanup stale temporary preview audio files older than $olderThanHours.
     */
    public function cleanupOldPreviews(int $olderThanHours = 24): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subHours($olderThanHours)->timestamp;
        $deleted = 0;

        foreach ($disk->files('tts/previews') as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @return array{is_raw_pcm: bool, rate: int, channels: int, ext: string}
     */
    private function parseMimeType(string $mime): array
    {
        $lower = strtolower($mime);
        $rate = 24000;
        if (preg_match('/rate=(\d+)/', $lower, $m)) {
            $rate = max(8000, min(96000, (int) $m[1]));
        }

        $channels = 1;
        if (preg_match('/channels=(\d+)/', $lower, $m)) {
            $channels = max(1, min(2, (int) $m[1]));
        }

        if (str_contains($lower, 'audio/wav') || str_contains($lower, 'audio/x-wav')) {
            return ['is_raw_pcm' => false, 'rate' => $rate, 'channels' => $channels, 'ext' => 'wav'];
        }
        if (str_contains($lower, 'audio/mpeg') || str_contains($lower, 'audio/mp3')) {
            return ['is_raw_pcm' => false, 'rate' => $rate, 'channels' => $channels, 'ext' => 'mp3'];
        }

        // Default Gemini TTS output: audio/L16;codec=pcm;rate=24000
        return ['is_raw_pcm' => true, 'rate' => $rate, 'channels' => $channels, 'ext' => 'pcm'];
    }

    /**
     * @return array{duration_seconds: float, sample_rate: int, channels: int}
     */
    private function probeAudio(string $absPath): array
    {
        $ffprobe = (string) config('affiliate_ai.video.ffprobe_binary', 'ffprobe');
        $proc = new Process([
            $ffprobe, '-v', 'error', '-select_streams', 'a:0',
            '-show_entries', 'stream=sample_rate,channels:format=duration',
            '-of', 'json', $absPath,
        ]);
        $proc->setTimeout(15);
        $proc->run();

        if (! $proc->isSuccessful()) {
            return ['duration_seconds' => 0.0, 'sample_rate' => 24000, 'channels' => 1];
        }

        $json = json_decode($proc->getOutput(), true);
        $duration = (float) data_get($json, 'format.duration', 0.0);
        $sampleRate = (int) data_get($json, 'streams.0.sample_rate', 24000);
        $channels = (int) data_get($json, 'streams.0.channels', 1);

        return [
            'duration_seconds' => round($duration, 2),
            'sample_rate' => $sampleRate,
            'channels' => $channels,
        ];
    }
}
