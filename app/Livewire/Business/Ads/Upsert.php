<?php

namespace App\Livewire\Business\Ads;

use Throwable;
use App\Models\Ad;
use App\Models\Post;
use App\Rules\X\XRule;
use Livewire\Component;
use App\Enums\Ad\AdStatus;
use App\Enums\Ad\AdApproval;
use App\Enums\Post\PostStatus;
use App\Enums\Post\PostType;
use App\Constants\Filesystem;
use Livewire\WithFileUploads;
use App\Enums\Media\MediaType;
use App\Enums\Media\MediaStatus;
use App\Actions\Media\DeleteMediaAction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Services\Timeline\TopicExtractionService;
use App\Services\Filesystem\Upload\ImageUploadService;
use App\Services\Filesystem\Upload\VideoUploadService;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Services\Filesystem\Upload\VideoThumbnailService;

class Upsert extends Component
{
    use WithFileUploads;

    public Ad $adData;
    public string $upsertType;
    public $formData = [];
    public $creative = null;
    public $adMedia = null;
    public string $boostPostSearch = '';

    public function mount()
    {
        $this->adMedia = $this->adData->media;

        $this->formData = [
            'source_type' => $this->adData->source_type ?: 'creative',
            'source_post_id' => $this->adData->source_post_id,
            'media_type' => $this->adData->type ?: MediaType::IMAGE->value,
            'title' => $this->adData->title,
            'content' => $this->adData->content,
            'cta_text' => $this->adData->cta_text ?: __('business/ads.form.cta_presets.send_message'),
            'total_budget' => $this->adData->total_budget,
            'price_per_view' => $this->adData->price_per_view ?: config('ads.price_per_view'),
            'target_topics' => $this->adData->target_topics_text,
            'target_url' => $this->adData->target_url,
        ];
    }

    public function updatedCreative()
    {
        if($this->creative) {
            $this->uploadCreative();
        }
    }

    public function render()
    {
        return view('livewire.business.ads.upsert', [
            'boostablePosts' => $this->boostablePostOptions(),
            'selectedSourcePost' => $this->selectedSourcePost(),
            'previewData' => $this->previewData(),
        ]);
    }

    public function getRules()
    {
        $noButton = __('business/ads.form.cta_presets.no_button');
        $rules = [
            'formData.source_type' => ['required', Rule::in(['creative', 'post'])],
            'formData.cta_text' => [
                'required',
                'string',
                XRule::join('max', config('ads.ad.validation.cta_text.max'))
            ],
            'formData.total_budget' => [
                'required',
                'numeric',
                XRule::join('min', config('ads.ad.validation.total_budget.min')),
                XRule::join('max', config('ads.ad.validation.total_budget.max'))
            ],
            'formData.price_per_view' => [
                'required',
                'numeric',
                XRule::join('min', config('ads.price_per_view_limits.min')),
                XRule::join('max', config('ads.price_per_view_limits.max'))
            ],
            'formData.target_topics' => [
                'nullable',
                'string',
                XRule::join('max', config('ads.targeting.topics_max_length')),
            ],
        ];

        if(($this->formData['cta_text'] ?? '') !== $noButton) {
            $rules['formData.target_url'] = [
                'required',
                'url',
                XRule::join('max', config('ads.ad.validation.target_url.max')),
            ];
        }

        if(($this->formData['source_type'] ?? 'creative') === 'post') {
            $rules['formData.source_post_id'] = ['required', 'integer'];
        }
        else {
            $rules['formData.media_type'] = ['required', Rule::in([MediaType::IMAGE->value, MediaType::VIDEO->value])];
            $rules['formData.title'] = [
                'required',
                'string',
                XRule::join('max', config('ads.ad.validation.title.max'))
            ];
            $rules['formData.content'] = [
                'required',
                'string',
                XRule::join('max', config('ads.ad.validation.content.max'))
            ];
        }

        return $rules;
    }

    public function submitForm()
    {
        $this->validate(rules: $this->getRules(), attributes: [
            'formData.title' => __('business/ads.form.title'),
            'formData.content' => __('business/ads.form.content'),
            'formData.cta_text' => __('business/ads.form.cta'),
            'formData.total_budget' => __('business/ads.form.budget'),
            'formData.price_per_view' => __('business/ads.form.price_per_view'),
            'formData.target_topics' => __('business/ads.form.target_topics'),
            'formData.target_url' => __('business/ads.form.target_url'),
            'formData.source_post_id' => __('business/ads.form.source_post'),
            'formData.media_type' => __('business/ads.form.media_type'),
        ]);

        $sourceType = $this->formData['source_type'] ?? 'creative';
        $sourcePost = null;

        if($sourceType === 'post') {
            $sourcePost = $this->selectedSourcePost();

            if(empty($sourcePost)) {
                $this->addError('formData.source_post_id', __('business/ads.form.source_post_required'));

                return false;
            }
        }
        else if($this->freshAdMedia()->isEmpty()) {
            $this->addError('creative', __('business/ads.form.creative_required'));

            return false;
        }

        $updateData = [
            'source_type' => $sourceType,
            'source_post_id' => $sourcePost?->id,
            'title' => e($sourceType === 'post' ? $this->postPreviewTitle($sourcePost) : $this->formData['title']),
            'content' => e($sourceType === 'post' ? $sourcePost->content : $this->formData['content']),
            'cta_text' => e($this->formData['cta_text']),
            'price_per_view' => $this->formData['price_per_view'],
            'target_topics' => $this->normalizeTargetTopics(),
            'target_url' => ($this->formData['cta_text'] ?? '') === __('business/ads.form.cta_presets.no_button')
                ? null
                : $this->formData['target_url'],
            'type' => $sourceType === 'post' ? $this->postAdType($sourcePost) : $this->formData['media_type'],
        ];

        if($this->upsertType == 'create') {
            $updateData['approval'] = AdApproval::PENDING;
            $updateData['status'] = AdStatus::PUBLISHED;
            $updateData['total_budget'] = $this->formData['total_budget'];
            $updateData['funding_metadata'] = null;
            $updateData['pause_reason'] = null;
        }
        else if($this->upsertType == 'edit') {
            // If the ad is rejected, set it to pending.
            // This is to allow the user to update the ad and resubmit it for approval.
            if($this->adData->approval->isRejected()) {
                $updateData['approval'] = AdApproval::PENDING;
            }

            if((float) $this->formData['total_budget'] != (float) $this->adData->total_budget) {
                $this->addError('formData.total_budget', __('business/ads.form.budget_edit'));

                return false;
            }
        }

        if($sourceType === 'post') {
            $this->deleteAdMedia();
        }

        $this->adData->update($updateData);

        return redirect()->route('business.ads.index');
    }

    private function uploadCreative()
    {
        if(($this->formData['source_type'] ?? 'creative') === 'post') {
            $this->addError('creative', __('business/ads.form.creative_post_mode'));

            return false;
        }

        if($this->adMedia->count()) {
            $this->addError('creative', __('business/ads.form.creative_max'));

            return false;
        }

        try {
            if(($this->formData['media_type'] ?? MediaType::IMAGE->value) === MediaType::VIDEO->value) {
                return $this->uploadVideoCreative();
            }

            $this->validate(rules: [
                'creative' => [
                    'required',
                    'image',
                    XRule::join('mimes', config('ads.ad.validation.creative.mimes')),
                    XRule::join('mimetypes', config('ads.ad.validation.creative.mimetypes')),
                    XRule::join('max', config('ads.ad.validation.creative.max'))
                ]
            ], attributes: [
                'creative' => __('business/ads.form.creative')
            ]);

            $imageUploadService = app(ImageUploadService::class);
            $roundRobinService = app(RoundRobinService::class);

            $imageData = $imageUploadService
                ->load($this->creative->getRealPath())
                ->setStorageDisk($roundRobinService->getNextDisk())
                ->setNamespace(Filesystem::mediaNamespace('ads/creatives'))
                ->crop(config('ads.ad.image_width'), config('ads.ad.image_height'))
                ->compress()
                ->upload();

            if($imageData) {
                $this->adData->media()->create([
                    'source_path' => $imageData['image_path'],
                    'disk' => $imageData['disk'],
                    'type' => MediaType::IMAGE,
                    'status' => MediaStatus::PROCESSED,
                    'extension' => $this->creative->getClientOriginalExtension(),
                    'mime' => $this->creative->getClientMimeType(),
                    'size' => $imageData['image_size'],
                    'metadata' => []
                ]);

                $this->refreshAdMedia();
                $this->creative = null;
            }
        }

        catch (ValidationException $e) {
            $this->addError('creative', collect($e->errors())->flatten()->first() ?: $e->getMessage());
        }
    }

    private function uploadVideoCreative()
    {
        $this->validate(rules: [
            'creative' => [
                'required',
                'file',
                XRule::join('mimes', config('ads.ad.validation.video.mimes')),
                XRule::join('mimetypes', config('ads.ad.validation.video.mimetypes')),
                XRule::join('max', config('ads.ad.validation.video.max'))
            ]
        ], attributes: [
            'creative' => __('business/ads.form.creative')
        ]);

        $videoUploadService = app(VideoUploadService::class);
        $videoThumbnailService = app(VideoThumbnailService::class);
        $imageUploadService = app(ImageUploadService::class);
        $roundRobinService = app(RoundRobinService::class);
        $videoStorageDisk = $roundRobinService->getNextDisk();
        $videoData = null;
        $videoThumbnailPath = null;

        try {
            $videoData = $videoUploadService
                ->setStorageDisk($videoStorageDisk)
                ->tempSaveLocally($this->creative);

            $maxDuration = (int) config('ads.ad.validation.video.max_duration_seconds', 30);

            if((int) $videoData['seconds'] > $maxDuration) {
                Storage::disk('local')->delete($videoData['video_path']);

                throw ValidationException::withMessages([
                    'creative' => __('business/ads.form.video_duration_limit', ['seconds' => $maxDuration])
                ]);
            }

            $videoThumbnailPath = $videoThumbnailService->generateThumbnail($videoData['video_path']);

            $imageData = $imageUploadService
                ->load($videoThumbnailPath)
                ->setNamespace(Filesystem::mediaNamespace('ads/video_thumbnails'))
                ->setStorageDisk($videoStorageDisk)
                ->compress()
                ->upload();

            $extension = $this->creative->getClientOriginalExtension() ?: 'mp4';
            $videoOutputData = $videoUploadService
                ->setStorageDisk($videoStorageDisk)
                ->setNamespace(Filesystem::mediaNamespace('ads/videos'))
                ->setDefaultExtension($extension)
                ->upload(storage_local_path($videoData['video_path']));

            if($videoThumbnailPath && file_exists($videoThumbnailPath)) {
                unlink($videoThumbnailPath);
            }

            $this->adData->media()->create([
                'source_path' => $videoOutputData['video_path'],
                'type' => MediaType::VIDEO,
                'status' => MediaStatus::PROCESSED,
                'disk' => $videoOutputData['disk'],
                'extension' => $extension,
                'mime' => $this->creative->getClientMimeType(),
                'size' => $videoOutputData['video_size'],
                'thumbnail_path' => $imageData['image_path'],
                'thumbnail_size' => $imageData['image_size'],
                'thumbnail_disk' => $imageData['disk'],
                'metadata' => [
                    'duration' => $videoData['duration'],
                    'duration_seconds' => $videoData['seconds'],
                    'dimensions' => $videoData['dimensions'],
                    'aspect_ratio' => $videoData['aspect_ratio'],
                    'is_portrait' => $videoData['is_portrait'],
                ]
            ]);

            $this->refreshAdMedia();
            $this->creative = null;

            return true;
        }
        catch (ValidationException $e) {
            throw $e;
        }
        catch (Throwable $th) {
            if($videoData && ! empty($videoData['video_path'])) {
                Storage::disk('local')->delete($videoData['video_path']);
            }

            if($videoThumbnailPath && file_exists($videoThumbnailPath)) {
                unlink($videoThumbnailPath);
            }

            $this->addError('creative', $th->getMessage());

            return false;
        }
    }

    public function updatedFormDataSourceType($value)
    {
        if($value === 'post' && empty($this->formData['source_post_id'])) {
            $firstPost = $this->boostablePosts()->first();

            if($firstPost) {
                $this->formData['source_post_id'] = $firstPost->id;
                $this->formData['target_url'] = $firstPost->url;
            }
        }
    }

    public function setSourceType(string $value): void
    {
        if(! in_array($value, ['creative', 'post'], true)) {
            return;
        }

        $this->formData['source_type'] = $value;
        $this->updatedFormDataSourceType($value);
    }

    public function setMediaType(string $value): void
    {
        if(! in_array($value, [MediaType::IMAGE->value, MediaType::VIDEO->value], true)) {
            return;
        }

        $this->formData['media_type'] = $value;
        $this->updatedFormDataMediaType($value);
    }

    public function setCtaText(string $value): void
    {
        $this->formData['cta_text'] = $value;
    }

    public function updatedFormDataSourcePostId($value)
    {
        $post = $this->selectedSourcePost();

        if($post) {
            $this->formData['target_url'] = $post->url;

            if(blank($this->formData['cta_text'] ?? '')) {
                $this->formData['cta_text'] = __('business/ads.form.cta_presets.learn_more');
            }
        }
    }

    public function updatedFormDataMediaType($value)
    {
        $currentMedia = $this->adMedia->first();

        if($currentMedia && $currentMedia->type->value !== $value) {
            $this->deleteAdMedia();
        }
    }

    private function normalizeTargetTopics(): array
    {
        $topicExtractionService = app(TopicExtractionService::class);
        $topics = preg_split('/[\s,]+/u', (string) ($this->formData['target_topics'] ?? ''));

        return collect($topics)
            ->map(fn($topic) => $topicExtractionService->normalizeTopic($topic))
            ->filter()
            ->unique()
            ->take((int) config('ads.targeting.topics_limit'))
            ->values()
            ->all();
    }

    public function deleteAdCreative(int $mediaId)
    {
        $adMediaItem = $this->adMedia->find($mediaId);

        if($adMediaItem) {
            try {
                (new DeleteMediaAction($adMediaItem))->execute();

                $this->refreshAdMedia();
            }

            catch (Throwable $th) {
                $this->addError('creative', $th->getMessage());
            }
        }
    }

    private function boostablePosts()
    {
        return Post::query()
            ->where('user_id', me()->id)
            ->where('status', PostStatus::ACTIVE)
            ->whereIn('type', $this->supportedBoostPostTypes())
            ->when(filled($this->boostPostSearch), function($query) {
                $search = trim($this->boostPostSearch);

                $query->where(function($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->with(['media'])
            ->latest('id')
            ->limit(30)
            ->get();
    }

    private function boostablePostOptions(): array
    {
        return $this->boostablePosts()
            ->map(fn(Post $post) => [
                'key' => $post->id,
                'value' => $this->postOptionLabel($post),
            ])
            ->all();
    }

    private function selectedSourcePost(): ?Post
    {
        $postId = (int) ($this->formData['source_post_id'] ?? 0);

        if($postId < 1) {
            return null;
        }

        return Post::query()
            ->where('user_id', me()->id)
            ->where('status', PostStatus::ACTIVE)
            ->whereIn('type', $this->supportedBoostPostTypes())
            ->with(['media'])
            ->find($postId);
    }

    private function supportedBoostPostTypes(): array
    {
        return [
            PostType::TEXT->value,
            PostType::IMAGE->value,
            PostType::VIDEO->value,
            PostType::GIF->value,
        ];
    }

    private function postOptionLabel(Post $post): string
    {
        return trim($post->type->label() . ' - ' . str($this->postPreviewTitle($post))->limit(72));
    }

    private function postPreviewTitle(?Post $post): string
    {
        if(empty($post)) {
            return '';
        }

        return filled($post->title) ? $post->title : (string) str($post->content)->limit(80);
    }

    private function postAdType(?Post $post): string
    {
        $media = $post?->media?->first();

        return $media?->type->value ?: ($post?->type->value ?: MediaType::IMAGE->value);
    }

    private function previewData(): array
    {
        $noButton = __('business/ads.form.cta_presets.no_button');
        $ctaText = $this->formData['cta_text'] ?: __('business/ads.preview.cta_placeholder');

        if(($this->formData['source_type'] ?? 'creative') === 'post') {
            $post = $this->selectedSourcePost();
            $media = $post?->media?->first();

            return [
                'source_type' => 'post',
                'title' => $this->postPreviewTitle($post) ?: __('business/ads.preview.title_placeholder'),
                'content' => $post?->content ?: __('business/ads.preview.content_placeholder'),
                'cta_text' => $ctaText,
                'target_url' => $ctaText === $noButton ? '' : ($this->formData['target_url'] ?: ($post?->url ?: '')),
                'media_type' => $media?->type->value ?: ($post?->type->value ?: 'text'),
                'media_url' => $media?->source_url,
                'thumbnail_url' => $media?->thumbnail_url,
            ];
        }

        $media = $this->adMedia->first();

        return [
            'source_type' => 'creative',
            'title' => $this->formData['title'] ?: __('business/ads.preview.title_placeholder'),
            'content' => $this->formData['content'] ?: __('business/ads.preview.content_placeholder'),
            'cta_text' => $ctaText,
            'target_url' => $ctaText === $noButton ? '' : ($this->formData['target_url'] ?: __('business/ads.preview.url_placeholder')),
            'media_type' => $media?->type->value ?: ($this->formData['media_type'] ?? MediaType::IMAGE->value),
            'media_url' => $media?->source_url,
            'thumbnail_url' => $media?->thumbnail_url,
        ];
    }

    private function deleteAdMedia(): void
    {
        $this->freshAdMedia()->each(function ($mediaItem) {
            (new DeleteMediaAction($mediaItem))->execute();
        });

        $this->refreshAdMedia();
    }

    private function freshAdMedia()
    {
        $this->adData->unsetRelation('media');

        return $this->adData->media()->get();
    }

    private function refreshAdMedia(): void
    {
        $this->adMedia = $this->freshAdMedia();
    }
}
