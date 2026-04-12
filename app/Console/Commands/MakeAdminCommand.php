<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class MakeAdminCommand extends Command
{
    protected $signature = 'make:admin';

    protected $description = 'Create an admin user (role = 0) for phone-based login';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Full name'));
        $phone = trim((string) $this->ask('10-digit phone number (login username)'));
        $password = (string) $this->ask('Password (minimum 8 characters)');


        $validator = Validator::make([
            'name' => $name,
            'phone' => $phone,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'integer', 'digits:10', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'phone' => (int) $phone,
            'email' => "ledger@admin.com",
            'password' => $password,
            'role' => 0,
        ]);

        $this->info("Admin created successfully. Phone username: {$user->phone}");

        return self::SUCCESS;
    }
}
