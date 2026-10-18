<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('admin:grant {email : Who to let into /admin} {--revoke : Take it away instead}')]
#[Description('Let someone open the platform admin at /admin, or stop them')]
class GrantAdmin extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email');
        $user = is_string($email) ? User::where('email', mb_strtolower($email))->first() : null;

        if ($user === null) {
            $this->error('Nobody has signed up with that email.');

            return self::FAILURE;
        }

        if ($user->is_demo) {
            $this->error('Demo logins are shared, so they can never be admins.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();

        $this->info($this->option('revoke') ? "{$user->email} can no longer open /admin." : "{$user->email} can now open /admin.");

        return self::SUCCESS;
    }
}
