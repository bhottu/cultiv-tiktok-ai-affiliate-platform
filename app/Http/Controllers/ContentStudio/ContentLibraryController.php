<?php

namespace App\Http\Controllers\ContentStudio;

use App\Enums\ContentFormatType;
use App\Enums\ContentPipelineStatus;
use App\Enums\VideoTemplateType;
use App\Http\Controllers\Controller;
use App\Models\AffiliateProduct;
use App\Models\Campaign;
use App\Models\GeneratedContent;
use App\Models\VideoProject;
use App\Modules\ContentStrategy\AIContentResearchService;
use App\Modules\ProductCatalog\ProductCategoryService;
use App\Modules\VideoProduction\VideoProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContentLibraryController extends Controller
{
    public function __construct(
        private readonly VideoProjectService $videoProjectService,
        private readonly ProductCategoryService $categoryService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $statusFilter = $request->string('status')->toString();
        $approvalFilter = $request->string('approval_status')->toString();
        $categoryFilter = $request->string('category')->toString();
        $categoryIdFilter = $request->integer('category_id');
        $productFilter = $request->integer('product_id');
        $formatFilter = $request->string('content_format')->toString();
        $campaignFilter = $request->integer('campaign_id');
        $templateFilter = $request->string('template_slug')->toString();
        $search = trim($request->string('q')->toString());

        $query = GeneratedContent::query()
            ->where('user_id', $user->id)
            ->with(['idea', 'affiliateProduct', 'productCategory', 'campaign', 'videoProject.latestRenderJob', 'videoProject.voiceoverAsset', 'approvalLogs'])
            ->latest('updated_at');

        if ($statusFilter !== '' && in_array($statusFilter, ContentPipelineStatus::values(), true)) {
            $query->where('status', $statusFilter);
        }

        if ($approvalFilter !== '' && in_array($approvalFilter, ['pending_approval', 'approved', 'rejected', 'archived', 'draft'], true)) {
            if ($approvalFilter === 'draft') {
                $query->whereIn('status', ['idea', 'script_draft', 'voiceover_ready', 'ready_to_render', 'rendering', 'video_ready']);
            } else {
                $query->where('status', $approvalFilter);
            }
        }

        if ($categoryIdFilter > 0) {
            $query->where('product_category_id', $categoryIdFilter);
        } elseif ($categoryFilter !== '') {
            $query->where('category', $categoryFilter);
        }

        if ($productFilter > 0) {
            $query->where('affiliate_product_id', $productFilter);
        }

        if ($formatFilter !== '' && in_array($formatFilter, ContentFormatType::values(), true)) {
            $query->where('content_format', $formatFilter);
        }

        if ($campaignFilter > 0) {
            $query->where('campaign_id', $campaignFilter);
        }

        if ($templateFilter !== '' && in_array($templateFilter, VideoTemplateType::values(), true)) {
            $query->whereHas('videoProject', fn ($vq) => $vq->where('template_slug', $templateFilter));
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', '%' . $search . '%')
                    ->orWhere('hook', 'ilike', '%' . $search . '%')
                    ->orWhere('caption', 'ilike', '%' . $search . '%');
            });
        }

        $contents = $query->paginate(12)->withQueryString();

        $statusCounts = GeneratedContent::query()
            ->where('user_id', $user->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $selectedId = $request->integer('inspect');
        $selectedContent = null;
        if ($selectedId > 0) {
            $selectedContent = GeneratedContent::with([
                'idea',
                'affiliateProduct',
                'productCategory',
                'campaign',
                'mediaAssets',
                'videoProject.scenes.mediaAsset',
                'videoProject.subtitles',
                'videoProject.voiceoverAsset',
                'videoProject.latestRenderJob',
                'approvalLogs.actor',
            ])->find($selectedId);

            if ($selectedContent && Gate::denies('view', $selectedContent)) {
                abort(403);
            }
        }

        $products = AffiliateProduct::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if (in_array($user->role, ['owner', 'admin'], true)) {
                    $q->orWhereNull('user_id');
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $campaigns = Campaign::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhereNull('user_id');
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('content-studio.library', [
            'contents' => $contents,
            'statuses' => ContentPipelineStatus::cases(),
            'statusCounts' => $statusCounts,
            'categoryTree' => $this->categoryService->getTree(),
            'categories' => AIContentResearchService::CATEGORIES,
            'products' => $products,
            'campaigns' => $campaigns,
            'contentFormats' => ContentFormatType::options(),
            'videoTemplates' => VideoTemplateType::cases(),
            'statusFilter' => $statusFilter,
            'approvalFilter' => $approvalFilter,
            'categoryFilter' => $categoryFilter,
            'categoryIdFilter' => $categoryIdFilter,
            'productFilter' => $productFilter,
            'formatFilter' => $formatFilter,
            'campaignFilter' => $campaignFilter,
            'templateFilter' => $templateFilter,
            'search' => $search,
            'selectedContent' => $selectedContent,
        ]);
    }

    public function updateMetadata(Request $request, GeneratedContent $content): RedirectResponse
    {
        Gate::authorize('update', $content);

        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:2500'],
            'hashtags_text' => ['nullable', 'string', 'max:1000'],
            'disclosure_text' => ['nullable', 'string', 'max:500'],
        ]);

        $hashtags = [];
        if (! empty($data['hashtags_text'])) {
            foreach (preg_split('/[\s,]+/', trim($data['hashtags_text'])) ?: [] as $tok) {
                $tok = trim($tok);
                if ($tok !== '') {
                    $hashtags[] = str_starts_with($tok, '#') ? $tok : '#' . $tok;
                }
            }
        }

        $content->update([
            'caption' => $data['caption'] ?? '',
            'hashtags' => $hashtags ?: $content->hashtags,
            'disclosure_text' => $data['disclosure_text'] ?? '',
            'revision_count' => ((int) $content->revision_count) + 1,
        ]);

        return back()->with('status', "Caption, hashtag, dan affiliate disclosure untuk '{$content->title}' berhasil diperbarui.");
    }

    public function decideApproval(Request $request, GeneratedContent $content): RedirectResponse
    {
        Gate::authorize('approve', $content);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject,submit,archive'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->videoProjectService->decideApproval(
            $content,
            $request->user(),
            $data['decision'],
            $data['notes'] ?? null,
        );

        $label = match ($data['decision']) {
            'approve' => 'disetujui dan siap diekspor ke TikTok',
            'reject' => 'ditolak untuk revisi ulang',
            'submit' => 'diajukan ke antrean persetujuan',
            'archive' => 'diarsipkan',
        };

        return back()->with('status', "Konten '{$content->title}' berhasil {$label}.");
    }

    public function streamVideo(VideoProject $project): BinaryFileResponse
    {
        Gate::authorize('view', $project);

        $disk = Storage::disk('local');
        if (! $project->output_video_path || ! $disk->exists($project->output_video_path)) {
            abort(404, 'File video belum tersedia.');
        }

        return response()->file($disk->path($project->output_video_path), [
            'Content-Type' => 'video/mp4',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function exportVideo(VideoProject $project): StreamedResponse
    {
        Gate::authorize('view', $project);

        $disk = Storage::disk('local');
        if (! $project->output_video_path || ! $disk->exists($project->output_video_path)) {
            abort(404, 'File video MP4 hasil render belum tersedia untuk diekspor.');
        }

        $safeSlug = Str::slug($project->title ?: 'affiliate-video');
        $downloadName = "tiktok-affiliate-{$safeSlug}-{$project->id}.mp4";

        return $disk->download($project->output_video_path, $downloadName, [
            'Content-Type' => 'video/mp4',
        ]);
    }

    public function thumbnail(VideoProject $project): BinaryFileResponse
    {
        Gate::authorize('view', $project);

        $disk = Storage::disk('local');
        if (! $project->thumbnail_path || ! $disk->exists($project->thumbnail_path)) {
            abort(404, 'Thumbnail belum tersedia.');
        }

        return response()->file($disk->path($project->thumbnail_path), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
