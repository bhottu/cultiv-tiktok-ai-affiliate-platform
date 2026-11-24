<?php

namespace App\Modules\VideoProduction;

use App\Contracts\VideoRendererInterface;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class RemotionVideoRenderer implements VideoRendererInterface
{
    public function getSlug(): string
    {
        return 'remotion';
    }

    public function checkReadiness(): array
    {
        $finder = new ExecutableFinder();
        $nodePath = $finder->find('node');
        $ffmpegPath = $finder->find((string) config('affiliate_ai.video.ffmpeg_binary', 'ffmpeg'));
        $remotionDir = base_path((string) config('affiliate_ai.video.remotion_project_path', 'remotion'));
        $remotionInstalled = is_dir($remotionDir . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . 'remotion');

        $ready = $nodePath !== null && $ffmpegPath !== null && $remotionInstalled;
        $message = $ready
            ? 'Node.js, FFmpeg, dan Remotion v4 siap merender video vertikal 9:16 (1080×1920, 30 FPS).'
            : 'Pastikan Node.js, FFmpeg, dan paket npm di folder remotion/ telah terpasang.';

        return [
            'ready' => $ready,
            'node' => $nodePath !== null,
            'ffmpeg' => $ffmpegPath !== null,
            'remotion_installed' => $remotionInstalled,
            'browser_executable' => $this->findBrowserExecutable(),
            'message' => $message,
        ];
    }

    /**
     * Execute real Remotion CLI render producing 1080x1920 30 FPS H.264 + AAC MP4.
     *
     * @param  array<string, mixed>  $props
     * @param  callable(int, string): void|null  $onProgress
     * @return array{ok: bool, status: string, output_path?: string, metrics?: array<string, mixed>, message: string}
     */
    public function render(string $compositionId, array $props, string $outputPath, ?callable $onProgress = null): array
    {
        $readiness = $this->checkReadiness();
        if (! $readiness['ready']) {
            return [
                'ok' => false,
                'status' => 'failed',
                'message' => 'Lingkungan Remotion belum siap: ' . $readiness['message'],
            ];
        }

        $remotionDir = base_path((string) config('affiliate_ai.video.remotion_project_path', 'remotion'));
        File::ensureDirectoryExists(dirname($outputPath));

        $propsDir = storage_path('app/renders/props');
        File::ensureDirectoryExists($propsDir);
        $propsFile = $propsDir . DIRECTORY_SEPARATOR . 'props-' . uniqid('', true) . '.json';
        file_put_contents($propsFile, json_encode($props, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $concurrency = max(1, min(4, (int) env('REMOTION_CONCURRENCY', 2)));
        $remotionCliJs = $remotionDir . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . '@remotion' . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . 'remotion-cli.js';
        $finder = new ExecutableFinder();
        $nodeBin = $finder->find('node') ?: 'node';

        $cmd = [
            $nodeBin,
            $remotionCliJs,
            'render',
            'src/index.ts',
            $compositionId,
            $outputPath,
            '--props=' . $propsFile,
            '--codec=h264',
            '--audio-codec=aac',
            '--pixel-format=yuv420p',
            '--concurrency=' . $concurrency,
            '--overwrite',
            '--log=info',
        ];

        $browser = $this->findBrowserExecutable();
        if ($browser) {
            $cmd[] = '--browser-executable=' . $browser;
        }

        try {
            $proc = new Process($cmd, $remotionDir, null, null, 600);
            $lastPct = 10;

            $proc->run(function (string $type, string $buffer) use ($onProgress, &$lastPct): void {
                if (! $onProgress) {
                    return;
                }
                if (preg_match('/Rendered\s+(\d+)\/(\d+)/i', $buffer, $m)) {
                    $cur = (int) $m[1];
                    $tot = max(1, (int) $m[2]);
                    $pct = min(92, max(15, (int) round(15 + ($cur / $tot) * 75)));
                    if ($pct > $lastPct) {
                        $lastPct = $pct;
                        $onProgress($pct, "Merender frame {$cur}/{$tot}");
                    }
                } elseif (stripos($buffer, 'Stitching') !== false) {
                    $onProgress(94, 'Menggabungkan frame & audio (Stitching MP4)');
                }
            });

            if (! $proc->isSuccessful() || ! is_file($outputPath) || filesize($outputPath) < 1024) {
                $errOut = trim($proc->getErrorOutput() ?: $proc->getOutput());

                return [
                    'ok' => false,
                    'status' => 'failed',
                    'message' => 'Remotion gagal merender video: ' . mb_substr($errOut, -600),
                ];
            }

            $metrics = $this->probeRenderedVideo($outputPath);

            return [
                'ok' => true,
                'status' => 'completed',
                'output_path' => $outputPath,
                'metrics' => $metrics,
                'message' => 'Video Remotion berhasil dirender.',
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => 'failed',
                'message' => 'Exception saat menjalankan Remotion render: ' . $e->getMessage(),
            ];
        } finally {
            @unlink($propsFile);
        }
    }

    /**
     * Probe actual MP4 stream metadata via FFprobe.
     *
     * @return array<string, mixed>
     */
    public function probeRenderedVideo(string $absPath): array
    {
        $ffprobe = (string) config('affiliate_ai.video.ffprobe_binary', 'ffprobe');
        $proc = new Process([
            $ffprobe, '-v', 'error',
            '-show_entries', 'stream=codec_type,codec_name,width,height,r_frame_rate:format=duration,size',
            '-of', 'json', $absPath,
        ]);
        $proc->setTimeout(45);
        try {
            $proc->run();
        } catch (\Throwable) {
            return [
                'width' => 0, 'height' => 0, 'fps' => 0.0, 'video_codec' => null,
                'audio_codec' => null, 'has_audio' => false, 'duration_seconds' => 0.0,
                'file_size_bytes' => is_file($absPath) ? (int) filesize($absPath) : 0,
            ];
        }

        $out = [
            'width' => 0,
            'height' => 0,
            'fps' => 0.0,
            'video_codec' => null,
            'audio_codec' => null,
            'has_audio' => false,
            'duration_seconds' => 0.0,
            'file_size_bytes' => is_file($absPath) ? (int) filesize($absPath) : 0,
        ];

        if (! $proc->isSuccessful()) {
            return $out;
        }

        $json = json_decode($proc->getOutput(), true);
        foreach ((array) ($json['streams'] ?? []) as $s) {
            if (($s['codec_type'] ?? '') === 'video') {
                $out['width'] = (int) ($s['width'] ?? 0);
                $out['height'] = (int) ($s['height'] ?? 0);
                $out['video_codec'] = (string) ($s['codec_name'] ?? '');
                $rateStr = (string) ($s['r_frame_rate'] ?? '0/1');
                if (str_contains($rateStr, '/')) {
                    [$num, $den] = array_map('floatval', explode('/', $rateStr, 2));
                    $out['fps'] = $den > 0 ? round($num / $den, 2) : 0.0;
                }
            } elseif (($s['codec_type'] ?? '') === 'audio') {
                $out['has_audio'] = true;
                $out['audio_codec'] = (string) ($s['codec_name'] ?? '');
            }
        }
        $out['duration_seconds'] = round((float) data_get($json, 'format.duration', 0.0), 2);

        return $out;
    }

    public function extractThumbnail(string $mp4AbsPath, string $thumbAbsPath): bool
    {
        File::ensureDirectoryExists(dirname($thumbAbsPath));
        $ffmpeg = (string) config('affiliate_ai.video.ffmpeg_binary', 'ffmpeg');
        $proc = new Process([
            $ffmpeg, '-y', '-loglevel', 'error', '-ss', '00:00:01', '-i', $mp4AbsPath,
            '-vframes', '1', '-q:v', '3', $thumbAbsPath,
        ]);
        $proc->setTimeout(60);
        try {
            $proc->run();
        } catch (\Throwable) {
            return false;
        }

        return $proc->isSuccessful() && is_file($thumbAbsPath) && filesize($thumbAbsPath) > 500;
    }

    public function findBrowserExecutable(): ?string
    {
        $candidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
