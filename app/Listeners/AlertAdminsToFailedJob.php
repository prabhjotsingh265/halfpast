<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\JobFailedAlert;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Emails the platform admins when a job fails for good: once an hour at
 * most for each kind of job, so a gateway that's down doesn't fill the
 * inbox. A broken mailer is reported, never left to break the worker.
 */
class AlertAdminsToFailedJob
{
    public const QUIET_FOR_MINUTES = 60;

    public function handle(JobFailed $event): void
    {
        $job = $event->job->resolveName();

        $admins = User::query()->platformAdmins()->get();

        if ($admins->isEmpty() || ! Cache::add('job-failed-alert:'.sha1($job), true, now()->addMinutes(self::QUIET_FOR_MINUTES))) {
            return;
        }

        rescue(fn () => Notification::send($admins, new JobFailedAlert($job, $event->job->getQueue(), $event->exception->getMessage())));
    }
}
