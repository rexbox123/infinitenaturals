<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    protected $signature = 'store:create-admin
        {--name= : Full name}
        {--email= : Login email}
        {--password= : Password (omit to be prompted; avoid passing secrets on the command line)}
        {--role=admin : admin, manager or staff}';

    protected $description = 'Create (or promote) an admin dashboard account';

    public function handle(): int
    {
        $name = $this->option('name') ?: text('Name', required: true);
        $email = strtolower($this->option('email') ?: text('Email', required: true));
        $password = $this->option('password') ?: password('Password (min. 12 characters)', required: true);
        $role = $this->option('role');

        $validator = Validator::make(compact('name', 'email', 'password', 'role'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', Password::min(12)],
            'role' => ['required', 'in:admin,manager,staff'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $existed = $user->exists;
        $user->name = $name;
        $user->password = $password;
        $user->role = $role;
        $user->is_active = true;
        $user->email_verified_at ??= now();
        $user->save();

        $this->info(($existed ? 'Updated' : 'Created')." {$role} account for {$email}. Sign in at ".url('/login'));

        return self::SUCCESS;
    }
}
