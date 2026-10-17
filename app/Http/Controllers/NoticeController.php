<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNoticeRequest;
use App\Http\Requests\UpdateNoticeRequest;
use App\Models\Notice;
use App\Models\User;
use App\Notifications\NoticePublishedNotification;
use App\Services\NoticeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class NoticeController extends Controller
{
    private const TABS = ['draft', 'published', 'expired'];

    private const AUDIENCES = ['all', 'staff', 'teachers', 'students', 'parents'];

    /**
     * Publishers get a management table (drafts, published, expired). Everyone else gets a notice board
     * of what is addressed to them; authors also see their own drafts there.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canPublish = $user->can('notices.publish');
        abort_unless(
            $canPublish || $user->can('notices.create') || $user->can('notices.view') || $user->can('portal.notices.view'),
            403,
        );

        $search = $request->string('search')->trim()->toString();
        $tab = $canPublish && in_array($request->string('tab')->toString(), self::TABS, true) ? $request->string('tab')->toString() : '';
        $audience = $canPublish && in_array($request->string('audience')->toString(), self::AUDIENCES, true) ? $request->string('audience')->toString() : '';
        $today = today()->toDateString();

        $notices = $this->visibleNotices($user)
            ->with('creator:id,name')
            ->when($tab === 'expired',
                fn ($query) => $query->whereNotNull('expires_at')->whereDate('expires_at', '<', $today),
                fn ($query) => $query->where(fn ($inner) => $inner->whereNull('expires_at')->orWhereDate('expires_at', '>=', $today)))
            ->when($tab === 'draft', fn ($query) => $query->where('status', 'draft'))
            ->when($tab === 'published', fn ($query) => $query->where('status', 'published'))
            ->when($audience !== '', fn ($query) => $query->where('audience', $audience))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$search}%")
                ->orWhere('body', 'like', "%{$search}%")))
            ->orderByRaw("CASE WHEN status = 'draft' THEN 0 ELSE 1 END")
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Notice $notice): array => [
                'id' => $notice->id,
                'title' => $notice->title,
                'excerpt' => Str::limit($notice->body, 220),
                'audience' => $notice->audience,
                'status' => $notice->status,
                'createdBy' => $notice->creator->name,
                'publishedAt' => $notice->published_at?->toDateTimeString(),
                'expiresAt' => $notice->expires_at?->toDateString(),
                'expired' => (bool) $notice->expires_at?->isBefore(today()),
                'canEdit' => $this->canChangeDraft($user, $notice),
                'canDelete' => $this->canChangeDraft($user, $notice),
                'canPublish' => $canPublish && $notice->status === 'draft',
                'canWithdraw' => $canPublish && $notice->status === 'published' && ! $notice->expires_at?->isBefore(today()),
            ]);

        return Inertia::render('Notices/Index', [
            'mode' => $canPublish ? 'manage' : 'board',
            'notices' => $notices,
            'filters' => ['search' => $search, 'tab' => $tab, 'audience' => $audience],
            'counts' => $canPublish ? $this->counts($today) : null,
            'notifications' => $this->unreadNotifications($user)->take(10)->map(fn ($notification): array => [
                'id' => $notification->id,
                'noticeId' => $notification->data['notice_id'] ?? null,
                'title' => $notification->data['title'],
                'body' => Str::limit($notification->data['body'], 200),
            ])->values(),
            'can' => [
                'create' => $user->can('notices.create'),
                'publish' => $canPublish,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('notices.create'), 403);

        return Inertia::render('Notices/Create', ['canPublish' => $request->user()->can('notices.publish')]);
    }

    public function store(StoreNoticeRequest $request, NoticeService $service): RedirectResponse
    {
        $user = $request->user();
        $notice = $service->createDraft($request->validated(), $user);

        if ($request->boolean('publish') && $user->can('notices.publish')) {
            $published = $service->publish($notice, $user);
            $service->dispatchNotifications($published);

            return to_route('notices.show', $published)->with('status', 'Notice published and queued for delivery.');
        }

        return to_route('notices.show', $notice)->with('status', 'Notice saved as a draft.');
    }

    public function show(Request $request, Notice $notice): Response
    {
        $user = $request->user();
        $canPublish = $user->can('notices.publish');
        abort_unless($canPublish || $user->can('notices.create') || $user->can('notices.view') || $user->can('portal.notices.view'), 403);

        // Non-publishers may only open what is addressed to them (or their own draft), and not once it expired.
        abort_unless($this->visibleNotices($user)->whereKey($notice->id)->exists(), 404);
        abort_if(! $canPublish && $notice->expires_at?->isBefore(today()), 404);

        $notice->load('creator:id,name');

        // Opening the notice clears its notification.
        $this->unreadNotifications($user)
            ->filter(fn ($notification): bool => ($notification->data['notice_id'] ?? null) === $notice->id)
            ->each->markAsRead();

        return Inertia::render('Notices/Show', [
            'notice' => [
                'id' => $notice->id,
                'title' => $notice->title,
                'body' => $notice->body,
                'audience' => $notice->audience,
                'status' => $notice->status,
                'createdBy' => $notice->creator->name,
                'createdAt' => $notice->created_at->toDateTimeString(),
                'publishedAt' => $notice->published_at?->toDateTimeString(),
                'expiresAt' => $notice->expires_at?->toDateString(),
                'expired' => (bool) $notice->expires_at?->isBefore(today()),
            ],
            'showDetails' => $canPublish || $notice->created_by === $user->id,
            'can' => [
                'edit' => $this->canChangeDraft($user, $notice),
                'delete' => $this->canChangeDraft($user, $notice),
                'publish' => $canPublish && $notice->status === 'draft',
                'withdraw' => $canPublish && $notice->status === 'published' && ! $notice->expires_at?->isBefore(today()),
            ],
        ]);
    }

    public function edit(Request $request, Notice $notice): Response
    {
        abort_unless($this->canChangeDraft($request->user(), $notice), 403);

        return Inertia::render('Notices/Edit', [
            'notice' => [
                'id' => $notice->id,
                'title' => $notice->title,
                'body' => $notice->body,
                'audience' => $notice->audience,
                'expiresAt' => $notice->expires_at?->toDateString(),
            ],
        ]);
    }

    public function update(UpdateNoticeRequest $request, Notice $notice, NoticeService $service): RedirectResponse
    {
        $service->updateDraft($notice, $request->validated(), $request->user());

        return to_route('notices.show', $notice)->with('status', 'Notice updated.');
    }

    public function destroy(Request $request, Notice $notice, NoticeService $service): RedirectResponse
    {
        $service->deleteDraft($notice, $request->user());

        return to_route('notices.index')->with('status', 'Draft deleted.');
    }

    public function publish(Notice $notice, Request $request, NoticeService $service): RedirectResponse
    {
        $publishedNotice = $service->publish($notice, $request->user());
        $service->dispatchNotifications($publishedNotice);

        return back()->with('status', 'Notice published and queued for delivery.');
    }

    public function withdraw(Notice $notice, Request $request, NoticeService $service): RedirectResponse
    {
        $service->withdraw($notice, $request->user());

        return back()->with('status', 'Notice withdrawn from the board.');
    }

    public function markRead(string $notificationId, Request $request): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        return back()->with('status', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->unreadNotifications($request->user())->each->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }

    // ------------------------------------------------------------------ helpers

    /** Publishers see every notice; others see published notices for their audience, plus their own drafts. */
    private function visibleNotices(User $user): Builder
    {
        $query = Notice::query();

        if ($user->can('notices.publish')) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user): void {
            $inner->where(fn (Builder $published) => $published
                ->where('status', 'published')
                ->whereIn('audience', $this->audiencesFor($user)));

            if ($user->can('notices.create')) {
                $inner->orWhere(fn (Builder $own) => $own->where('status', 'draft')->where('created_by', $user->id));
            }
        });
    }

    /** @return list<string> */
    private function audiencesFor(User $user): array
    {
        $audiences = ['all'];
        if ($user->hasRole('Student')) {
            $audiences[] = 'students';
        } elseif ($user->hasRole('Parent')) {
            $audiences[] = 'parents';
        } else {
            $audiences[] = 'staff';
            if ($user->hasRole('Teacher')) {
                $audiences[] = 'teachers';
            }
        }

        return $audiences;
    }

    private function canChangeDraft(User $user, Notice $notice): bool
    {
        return $notice->status === 'draft'
            && ($user->can('notices.publish') || ($user->can('notices.create') && $notice->created_by === $user->id));
    }

    /** Unread "notice published" notifications for the user. */
    private function unreadNotifications(User $user)
    {
        return $user->unreadNotifications()
            ->where('type', NoticePublishedNotification::class)
            ->latest()
            ->limit(50)
            ->get(['id', 'data', 'created_at']);
    }

    /** @return array{all: int, draft: int, published: int, expired: int} */
    private function counts(string $today): array
    {
        $stats = Notice::query()->selectRaw(
            "COALESCE(SUM(CASE WHEN expires_at IS NULL OR expires_at >= ? THEN 1 ELSE 0 END), 0) as live, "
            ."COALESCE(SUM(CASE WHEN status = 'draft' AND (expires_at IS NULL OR expires_at >= ?) THEN 1 ELSE 0 END), 0) as drafts, "
            ."COALESCE(SUM(CASE WHEN status = 'published' AND (expires_at IS NULL OR expires_at >= ?) THEN 1 ELSE 0 END), 0) as published, "
            .'COALESCE(SUM(CASE WHEN expires_at IS NOT NULL AND expires_at < ? THEN 1 ELSE 0 END), 0) as expired',
            [$today, $today, $today, $today],
        )->first();

        return [
            'all' => (int) $stats->live,
            'draft' => (int) $stats->drafts,
            'published' => (int) $stats->published,
            'expired' => (int) $stats->expired,
        ];
    }
}