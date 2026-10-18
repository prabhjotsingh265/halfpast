<?php

use App\Listeners\AlertAdminsToFailedJob;
use App\Models\User;
use App\Notifications\JobFailedAlert;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->create();
    $this->admin->forceFill(['is_admin' => true])->save();
});

/**
 * A job of this kind running out of tries on the default queue, told to the
 * listener alone (Horizon listens too, and wants a real Redis job).
 */
function failJob(string $name): void
{
    $job = Mockery::mock(Job::class);
    $job->allows(['resolveName' => $name, 'getQueue' => 'default']);

    app(AlertAdminsToFailedJob::class)->handle(new JobFailed('redis', $job, new RuntimeException("The gateway was down.\n#0 /app/vendor/...")));
}

it('emails the platform admins when a job fails for good', function () {
    $owner = User::factory()->create();
    $demo = User::factory()->create();
    $demo->forceFill(['is_admin' => true, 'is_demo' => true])->save();

    failJob('App\\Jobs\\SendRefund');

    Notification::assertSentTo($this->admin, JobFailedAlert::class, fn (JobFailedAlert $alert) => $alert->job === 'App\\Jobs\\SendRefund' && $alert->queue === 'default');
    Notification::assertNotSentTo([$owner, $demo], JobFailedAlert::class);
});

it('says which job, what went wrong, and where to try it again', function () {
    $mail = (new JobFailedAlert('App\\Jobs\\SendRefund', 'default', "The gateway was down.\n#0 /app/vendor/..."))->toMail($this->admin);

    expect($mail->subject)->toBe('A background job failed: SendRefund')
        ->and($mail->introLines)->toContain('"The gateway was down."')
        ->and(implode(' ', $mail->introLines))->not->toContain('#0')
        ->and($mail->actionUrl)->toBe(url('/admin/failed-jobs'));
});

it('sends one email an hour for each kind of job', function () {
    failJob('App\\Jobs\\SendRefund');
    failJob('App\\Jobs\\SendRefund');
    failJob('App\\Jobs\\FinishPayment');
    Notification::assertSentTimes(JobFailedAlert::class, 2);

    $this->travel(61)->minutes();
    failJob('App\\Jobs\\SendRefund');
    Notification::assertSentTimes(JobFailedAlert::class, 3);
});

it('hears about a job that really throws on the queue', function () {
    try {
        dispatch(function () {
            throw new RuntimeException('Mail server refused the connection.');
        });
        $this->fail('The job should have thrown.');
    } catch (RuntimeException) {
        // The sync queue hands the error back to whoever dispatched it.
    }

    Notification::assertSentTo($this->admin, JobFailedAlert::class, fn (JobFailedAlert $alert) => $alert->error === 'Mail server refused the connection.');
});

it('reports a broken mailer instead of breaking the worker', function () {
    Exceptions::fake();
    Notification::shouldReceive('send')->andThrow(new RuntimeException('SMTP is down.'));

    failJob('App\\Jobs\\SendRefund');

    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'SMTP is down.');
});
