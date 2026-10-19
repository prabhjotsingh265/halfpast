<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;

it('asks a new account to confirm its email, in plain words', function () {
    $user = User::factory()->unverified()->create();

    $mail = (new VerifyEmail)->toMail($user);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Confirm your email for Halfpast')
        ->and($mail->actionText)->toBe('Confirm my email')
        ->and($mail->actionUrl)->toContain('/email/verify/'.$user->id.'/')
        ->and($html)->toContain('brand/mark-96.png')
        ->and($html)->toContain('background-color: #EE9433');
});

it('sends a password reset link that says how long it lasts', function () {
    $user = User::factory()->create(['email' => 'maya@example.com']);

    $mail = (new ResetPassword('a-token'))->toMail($user);

    expect($mail->subject)->toBe('Reset your Halfpast password')
        ->and($mail->actionUrl)->toBe(url('/reset-password/a-token?email=maya%40example.com'))
        ->and(implode(' ', $mail->outroLines))->toContain('works for 60 minutes');
});
