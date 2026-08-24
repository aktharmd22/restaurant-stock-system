<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Enums\RoleName;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\Sms\SmsSender;
use App\Support\Settings;
use Illuminate\Console\Command;

/**
 * The last line of defence.
 *
 * A sound can be missed and a browser tab can be closed. If a request has been
 * sitting untouched for half an hour, stop relying on the screen and send a
 * message to a phone.
 *
 * Runs from cron. Half an hour of tolerance means it does not matter whether
 * the host allows a one-minute or a fifteen-minute schedule.
 */
class EscalateWaitingRequests extends Command
{
    protected $signature = 'requests:escalate {--minutes= : Override the wait before escalating}';

    protected $description = 'Message the main store about requests nobody has looked at';

    public function handle(SmsSender $sms, Settings $settings): int
    {
        $minutes = (int) ($this->option('minutes') ?: $settings->get('escalate_after_minutes', 30));

        $stale = StockRequest::withoutBranchScope()
            ->with('fromBranch')
            ->where('status', RequestStatus::Waiting)
            ->whereNull('escalated_at')
            ->where('submitted_at', '<=', now()->subMinutes($minutes))
            ->get();

        if ($stale->isEmpty()) {
            $this->info('Nothing has been waiting too long.');

            return self::SUCCESS;
        }

        $admins = User::query()
            ->active()
            ->whereNotNull('phone')
            ->role([RoleName::SuperAdmin->value, RoleName::MainAdmin->value])
            ->get();

        foreach ($stale as $request) {
            $branch = $request->fromBranch->name;
            $waited = (int) $request->submitted_at->diffInMinutes(now());

            $message = "{$branch} has been waiting {$waited} minutes for stock ({$request->request_number}). "
                .'Open the app to approve it.';

            foreach ($admins as $admin) {
                $sms->send($admin->phone, $message);
            }

            $request->forceFill(['escalated_at' => now()])->saveQuietly();
        }

        $this->info("Sent {$stale->count()} reminder(s) to {$admins->count()} person(s).");

        return self::SUCCESS;
    }
}
