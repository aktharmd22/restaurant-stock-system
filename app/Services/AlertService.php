<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\RoleName;
use App\Events\RequestEvent;
use App\Models\StockRequest;
use App\Models\User;
use App\Notifications\RequestNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * One place that decides who gets told what, in which words, with which sound.
 *
 * Every alert does three things at once: it broadcasts (a sound and a toast
 * for anyone with the app open), it writes a database notification (so a
 * missed alert can still be found), and it carries a link straight to the
 * thing that changed.
 */
class AlertService
{
    public const ADMIN_CHANNEL = 'admin.main';

    /** The seven sounds. The frontend synthesises each one. */
    public const SOUND_NEW_REQUEST = 'new_request';

    public const SOUND_APPROVED = 'approved';

    public const SOUND_PARTIAL = 'partial';

    public const SOUND_REJECTED = 'rejected';

    public const SOUND_SENT = 'sent';

    public const SOUND_LOW_STOCK = 'low_stock';

    public const SOUND_FAILED = 'failed';

    /** A branch asked for stock. Two rising chimes at the main store. */
    public function requestSubmitted(StockRequest $request): void
    {
        $branchName = $request->fromBranch->name;
        $late = $request->is_late ? ' (late)' : '';

        $this->send(
            recipients: $this->adminUsers(),
            request: $request,
            channel: self::ADMIN_CHANNEL,
            sound: self::SOUND_NEW_REQUEST,
            message: "New request from {$branchName}{$late}.",
            url: "/admin/requests?selected={$request->id}",
        );
    }

    /** The admin decided. What the branch hears depends on the answer. */
    public function requestReviewed(StockRequest $request): void
    {
        [$sound, $message] = match ($request->status) {
            RequestStatus::Approved => [self::SOUND_APPROVED, 'Your request was approved.'],
            RequestStatus::Partial => [self::SOUND_PARTIAL, 'Some items were cut. Tap to see why.'],
            RequestStatus::Rejected => [self::SOUND_REJECTED, 'Your request was not approved. Tap to see why.'],
            default => [self::SOUND_APPROVED, 'Your request was looked at.'],
        };

        $this->send(
            recipients: $this->branchUsers($request->from_branch_id),
            request: $request,
            channel: "branch.{$request->from_branch_id}",
            sound: $sound,
            message: $message,
            url: "/b/requests/{$request->id}",
        );
    }

    /** The goods left the store. */
    public function requestDispatched(StockRequest $request): void
    {
        $this->send(
            recipients: $this->branchUsers($request->from_branch_id),
            request: $request,
            channel: "branch.{$request->from_branch_id}",
            sound: self::SOUND_SENT,
            message: 'Your stock is on the way. Confirm it when it arrives.',
            url: "/b/receive/{$request->id}",
        );
    }

    /** The branch confirmed. The store keeper can stop wondering. */
    public function requestReceived(StockRequest $request): void
    {
        $branchName = $request->fromBranch->name;

        $this->send(
            recipients: $this->adminUsers(),
            request: $request,
            channel: self::ADMIN_CHANNEL,
            sound: self::SOUND_APPROVED,
            message: "{$branchName} confirmed the delivery.",
            url: "/admin/requests?status=received&selected={$request->id}",
        );
    }

    public function requestCancelled(StockRequest $request): void
    {
        $branchName = $request->fromBranch->name;

        $this->send(
            recipients: $this->adminUsers(),
            request: $request,
            channel: self::ADMIN_CHANNEL,
            sound: self::SOUND_REJECTED,
            message: "{$branchName} called back a request.",
            url: "/admin/requests?status=cancelled&selected={$request->id}",
        );
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function send(
        Collection $recipients,
        StockRequest $request,
        string $channel,
        string $sound,
        string $message,
        string $url,
    ): void {
        RequestEvent::dispatch($request, $sound, $message, $channel, $url);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new RequestNotification($request, $sound, $message, $url));
        }
    }

    /** @return Collection<int, User> */
    private function adminUsers(): Collection
    {
        return User::query()
            ->active()
            ->role([RoleName::SuperAdmin->value, RoleName::MainAdmin->value])
            ->get();
    }

    /** @return Collection<int, User> */
    private function branchUsers(int $branchId): Collection
    {
        return User::query()->active()->where('branch_id', $branchId)->get();
    }
}
