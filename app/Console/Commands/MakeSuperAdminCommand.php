<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class MakeSuperAdminCommand extends Command
{
    protected $signature = 'trippilot:make-super-admin {email : The user email address}';

    protected $description = 'Promote a user to super admin';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->components->error('No user found with that email address.');

            return self::FAILURE;
        }

        $user->update(['role' => UserRole::SuperAdmin]);

        $this->components->info("{$user->name} ({$user->email}) is now a super admin.");

        return self::SUCCESS;
    }
}
