<?php

namespace App\Modules\VideoProduction;

use App\Models\GeneratedContent;
use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\ActivityLogs\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class VisualAssetManagerService
{
    public const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
    public const ALLOWED_VIDEO_MIMES = ['video/mp4', 'video/quicktime'];
    public const ALLOWED_AUDIO_MIMES = ['audio/mpeg', 'audio/wav', 'audio/x-wav'];

    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function storeUpload(
        User $user,
        UploadedFile $file,
        string $categoryTag = 'product_photo',
        ?int $generatedContentId = null,
        ?int $affiliateProductId = null,
        ?int $campaignId = null,
        ?int $productCategoryId = null,
    ): MediaAsset {
        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');

        $assetType = match (true) {
            in_array($mime, self::ALLOWED_IMAGE_MIMES, true) => 'image',
            in_array($mime, self::ALLOWED_VIDEO_MIMES, true) => 'video',
            in_array($mime, self::ALLOWED_AUDIO_MIMES, true) => 'bg_music',
            default => throw new \InvalidArgumentException("Tipe file {$mime} tidak didukung."),
        };

        $disk = Storage::disk('local');
        $dir = "media/uploads/{$user->id}";
        $disk->makeDirectory($dir);

        $safeFileName = Str::uuid()->toString() . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
        $relPath = $disk->putFileAs($dir, $file, $safeFileName);
        if (! $relPath) {
            throw new \RuntimeException('Gagal menyimpan file aset ke storage lokal.');
        }

        $absPath = $disk->path($relPath);
        $width = null;
        $height = null;
        $duration = null;

        if ($assetType === 'image') {
            $imgInfo = @getimagesize($absPath);
            if (is_array($imgInfo)) {
                $width = (int) ($imgInfo[0] ?? 0) ?: null;
                $height = (int) ($imgInfo[1] ?? 0) ?: null;
            }
        } else {
            $probe = $this->probeMedia($absPath);
            $width = $probe['width'];
            $height = $probe['height'];
            $duration = $probe['duration'];
        }

        $maxOrder = (int) MediaAsset::where('user_id', $user->id)
            ->when($generatedContentId, fn ($q) => $q->where('generated_content_id', $generatedContentId))
            ->max('display_order');

        $asset = MediaAsset::create([
            'user_id' => $user->id,
            'affiliate_product_id' => $affiliateProductId,
            'generated_content_id' => $generatedContentId,
            'campaign_id' => $campaignId,
            'product_category_id' => $productCategoryId,
            'category_tag' => $categoryTag,
            'asset_type' => $assetType,
            'source_type' => 'user_upload',
            'license_status' => 'owner_uploaded',
            'disk' => 'local',
            'file_path' => $relPath,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $mime,
            'file_size_bytes' => (int) $file->getSize(),
            'width' => $width,
            'height' => $height,
            'duration_seconds' => $duration,
            'display_order' => $maxOrder + 1,
            'metadata' => [
                'category_tag' => $categoryTag,
                'uploaded_at_wib' => now('Asia/Jakarta')->toIso8601String(),
            ],
        ]);

        $this->logger->log(
            'media_assets',
            'asset_uploaded',
            "Aset {$assetType} '{$asset->original_name}' berhasil diunggah.",
            'info',
            ['media_asset_id' => $asset->id, 'mime' => $mime, 'size' => $asset->file_size_bytes],
            MediaAsset::class,
            $asset->id,
            $user->id,
        );

        return $asset;
    }

    public function deleteAsset(MediaAsset $asset, User $actor): void
    {
        if (Storage::disk($asset->disk)->exists($asset->file_path)) {
            Storage::disk($asset->disk)->delete($asset->file_path);
        }

        $name = $asset->original_name ?: basename($asset->file_path);
        $asset->delete();

        $this->logger->log(
            'media_assets',
            'asset_deleted',
            "Aset '{$name}' dihapus oleh pemilik.",
            'info',
            ['media_asset_id' => $asset->id],
            MediaAsset::class,
            $asset->id,
            $actor->id,
        );
    }

    public function attachToContent(
        MediaAsset $asset,
        ?GeneratedContent $content,
        int $order = 1,
        ?int $affiliateProductId = null,
        ?int $campaignId = null,
        ?int $productCategoryId = null,
        ?string $categoryTag = null,
    ): MediaAsset {
        $payload = [
            'generated_content_id' => $content?->id,
            'display_order' => max(1, $order),
        ];

        if ($affiliateProductId !== null) {
            $payload['affiliate_product_id'] = $affiliateProductId ?: null;
        }
        if ($campaignId !== null) {
            $payload['campaign_id'] = $campaignId ?: null;
        }
        if ($productCategoryId !== null) {
            $payload['product_category_id'] = $productCategoryId ?: null;
        }
        if ($categoryTag !== null && $categoryTag !== '') {
            $payload['category_tag'] = $categoryTag;
        }

        $asset->update($payload);

        return $asset->fresh();
    }

    /**
     * @return array{width: ?int, height: ?int, duration: ?float}
     */
    private function probeMedia(string $absPath): array
    {
        $ffprobe = (string) config('affiliate_ai.video.ffprobe_binary', 'ffprobe');
        $proc = new Process([
            $ffprobe, '-v', 'error',
            '-show_entries', 'stream=width,height:format=duration',
            '-of', 'json', $absPath,
        ]);
        $proc->setTimeout(15);
        $proc->run();

        if (! $proc->isSuccessful()) {
            return ['width' => null, 'height' => null, 'duration' => null];
        }

        $json = json_decode($proc->getOutput(), true);
        $width = null;
        $height = null;
        foreach ((array) ($json['streams'] ?? []) as $stream) {
            if (! empty($stream['width']) && ! empty($stream['height'])) {
                $width = (int) $stream['width'];
                $height = (int) $stream['height'];
                break;
            }
        }
        $dur = isset($json['format']['duration']) ? round((float) $json['format']['duration'], 2) : null;

        return ['width' => $width, 'height' => $height, 'duration' => $dur];
    }
}
