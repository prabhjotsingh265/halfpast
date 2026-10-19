<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Demo\DemoLogins;
use App\Http\Controllers\OnboardingController;
use App\Http\Responses\LoginResponse;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponse::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureEmails();
    }

    /**
     * The sign-up and password emails, in Halfpast's words rather than the
     * framework's.
     */
    private function configureEmails(): void
    {
        VerifyEmail::toMailUsing(fn (object $notifiable, string $url) => (new MailMessage)
            ->subject('Confirm your email for Halfpast')
            ->greeting('One click to go')
            ->line('Confirm this is your email address, and you can set up your booking page.')
            ->action('Confirm my email', $url)
            ->line("If you didn't sign up for Halfpast, ignore this email and nothing will happen.")
            ->salutation('Halfpast'));

        ResetPassword::toMailUsing(fn (object $notifiable, string $token) => (new MailMessage)
            ->subject('Reset your Halfpast password')
            ->greeting('Reset your password')
            ->line('Someone, hopefully you, asked to reset the password for this account.')
            ->action('Choose a new password', url(route('password.reset', ['token' => $token, 'email' => $notifiable instanceof User ? $notifiable->email : ''], false)))
            ->line('The link works for '.config('auth.passwords.users.expire').' minutes. If it wasn\'t you, ignore this email: your password stays as it is.')
            ->salutation('Halfpast'));
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
            'demos' => DemoLogins::available(),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(function (Request $request) {
            // Keep the business name typed on the home page for the setup step.
            $name = $request->query('business');

            if (is_string($name) && trim($name) !== '') {
                $request->session()->put(OnboardingController::SUGGESTED_NAME, Str::limit(trim(strip_tags($name)), 80, ''));
            }

            return Inertia::render('auth/Register', [
                'passwordRules' => Password::defaults()->toPasswordRulesString(),
            ]);
        });

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
