<?php

namespace App\Http\Controllers\ContentStudio;

use App\Http\Controllers\Controller;
use App\Models\AffiliateProduct;
use App\Models\Campaign;
use App\Models\GeneratedContent;
use App\Models\MediaAsset;
use App\Modules\ProductCatalog\ProductCategoryService;
use App\Modules\VideoProduction\VisualAssetManagerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VisualAssetController extends Controller
{
    public const CATEGORY_TAGS = [
        'product_photo' => 'Foto Produk Utama',
        'detail_closeup' => 'Foto Detail / Tekstur / Spesifikasi',
        'usage_demo' => 'Klip Demo / Penggunaan Produk',
        'unboxing_clip' => 'Klip Unboxing / Kemasan',
        'lifestyle_shot' => 'Foto / Klip Lifestyle & Konteks Ruang',
        'plant_photo' => 'Foto Tanaman / Berkebun',
        'plant_video' => 'Klip Video Tanaman',
        'greenhouse' => 'Suasana Kebun / Greenhouse',
        'bg_music' => 'Musik Latar Legal (Opsional)',
    ];

    public function __construct(
        private readonly VisualAssetManagerService $assetService,
        private readonly ProductCategoryService $categoryService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $typeFilter = $request->string('type')->toString();
        $contentFilter = $request->integer('content_id');
        $productFilter = $request->integer('product_id');
        $campaignFilter = $request->integer('campaign_id');
        $categoryFilter = $request->integer('category_id');

        $assets = MediaAsset::query()
            ->where('user_id', $user->id)
            ->whereIn('asset_type', ['image', 'video', 'bg_music'])
            ->with(['generatedContent', 'product', 'campaign', 'productCategory'])
            ->when(in_array($typeFilter, ['image', 'video', 'bg_music'], true), fn ($q) => $q->where('asset_type', $typeFilter))
            ->when($contentFilter > 0, fn ($q) => $q->where('generated_content_id', $contentFilter))
            ->when($productFilter > 0, fn ($q) => $q->where('affiliate_product_id', $productFilter))
            ->when($campaignFilter > 0, fn ($q) => $q->where('campaign_id', $campaignFilter))
            ->when($categoryFilter > 0, fn ($q) => $q->where('product_category_id', $categoryFilter))
            ->orderBy('display_order')
            ->latest()
            ->paginate(18)
            ->withQueryString();

        $contents = GeneratedContent::query()
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->limit(40)
            ->get(['id', 'title', 'category']);

        $products = AffiliateProduct::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if (in_array($user->role, ['owner', 'admin'], true)) {
                    $q->orWhereNull('user_id');
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'category']);

        $campaigns = Campaign::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhereNull('user_id');
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('content-studio.assets', [
            'assets' => $assets,
            'contents' => $contents,
            'products' => $products,
            'campaigns' => $campaigns,
            'categoryTree' => $this->categoryService->getTree(),
            'categoryTags' => self::CATEGORY_TAGS,
            'typeFilter' => $typeFilter,
            'contentFilter' => $contentFilter,
            'productFilter' => $productFilter,
            'campaignFilter' => $campaignFilter,
            'categoryFilter' => $categoryFilter,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'asset_file' => [
                'required',
                'file',
                'max:102400',
                'mimes:jpg,jpeg,png,webp,mp4,mov,mp3,wav',
            ],
            'category_tag' => ['required', 'string', 'in:' . implode(',', array_keys(self::CATEGORY_TAGS))],
            'generated_content_id' => ['nullable', 'integer', 'exists:generated_contents,id'],
            'affiliate_product_id' => ['nullable', 'integer', 'exists:affiliate_products,id'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
        ], [
            'asset_file.mimes' => 'Format file harus JPG, JPEG, PNG, WEBP, MP4, MOV, MP3, atau WAV.',
            'asset_file.max' => 'Ukuran file melebihi batas maksimum 100 MB.',
        ]);

        $contentId = null;
        if (! empty($data['generated_content_id'])) {
            $content = GeneratedContent::find($data['generated_content_id']);
            if ($content) {
                Gate::authorize('update', $content);
                $contentId = $content->id;
            }
        }

        $productId = null;
        if (! empty($data['affiliate_product_id'])) {
            $prod = AffiliateProduct::find($data['affiliate_product_id']);
            if ($prod && $prod->user_id !== null && (int) $prod->user_id !== $request->user()->id) {
                abort(403);
            }
            $productId = $prod?->id;
        }

        $file = $request->file('asset_file');
        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());

        if (in_array($mime, VisualAssetManagerService::ALLOWED_IMAGE_MIMES, true) && $file->getSize() > 15 * 1024 * 1024) {
            return back()->withErrors(['asset_file' => 'Ukuran gambar maksimal 15 MB.']);
        }
        if (in_array($mime, VisualAssetManagerService::ALLOWED_AUDIO_MIMES, true) && $file->getSize() > 25 * 1024 * 1024) {
            return back()->withErrors(['asset_file' => 'Ukuran file audio maksimal 25 MB.']);
        }

        try {
            $asset = $this->assetService->storeUpload(
                $request->user(),
                $file,
                $data['category_tag'],
                $contentId,
                $productId,
                isset($data['campaign_id']) ? (int) $data['campaign_id'] : null,
                isset($data['product_category_id']) ? (int) $data['product_category_id'] : null,
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['asset_file' => $e->getMessage()]);
        }

        return back()->with('status', "Aset '{$asset->original_name}' berhasil diunggah dan siap dipakai di Video Editor.");
    }

    public function update(Request $request, MediaAsset $asset): RedirectResponse
    {
        Gate::authorize('update', $asset);

        $data = $request->validate([
            'generated_content_id' => ['nullable', 'integer', 'exists:generated_contents,id'],
            'affiliate_product_id' => ['nullable', 'integer', 'exists:affiliate_products,id'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'category_tag' => ['nullable', 'string', 'in:' . implode(',', array_keys(self::CATEGORY_TAGS))],
            'display_order' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $content = null;
        if (! empty($data['generated_content_id'])) {
            $content = GeneratedContent::find($data['generated_content_id']);
            if ($content) {
                Gate::authorize('update', $content);
            }
        }

        if (! empty($data['affiliate_product_id'])) {
            $prod = AffiliateProduct::find($data['affiliate_product_id']);
            if ($prod && $prod->user_id !== null && (int) $prod->user_id !== $request->user()->id) {
                abort(403);
            }
        }

        $this->assetService->attachToContent(
            $asset,
            $content,
            (int) $data['display_order'],
            array_key_exists('affiliate_product_id', $data) ? (int) ($data['affiliate_product_id'] ?? 0) : null,
            array_key_exists('campaign_id', $data) ? (int) ($data['campaign_id'] ?? 0) : null,
            array_key_exists('product_category_id', $data) ? (int) ($data['product_category_id'] ?? 0) : null,
            $data['category_tag'] ?? null,
        );

        return back()->with('status', "Pengaturan aset '{$asset->original_name}' berhasil diperbarui.");
    }

    public function destroy(Request $request, MediaAsset $asset): RedirectResponse
    {
        Gate::authorize('delete', $asset);

        $name = $asset->original_name ?: basename($asset->file_path);
        $this->assetService->deleteAsset($asset, $request->user());

        return back()->with('status', "Aset '{$name}' berhasil dihapus.");
    }

    public function preview(MediaAsset $asset): BinaryFileResponse
    {
        Gate::authorize('view', $asset);

        $disk = Storage::disk($asset->disk ?: 'local');
        if (! $disk->exists($asset->file_path)) {
            abort(404, 'File aset tidak ditemukan di storage.');
        }

        return response()->file($disk->path($asset->file_path), [
            'Content-Type' => $asset->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
