<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateEditor extends Command
{
    protected $signature = 'news:create-editor {email : Email do editor} {--name=Editor : Nome exibido}';

    protected $description = 'Create an editor account with a one-time generated password';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::where('email', $email)->exists()) {
            $this->components->error('Email inválido ou já cadastrado.');

            return self::FAILURE;
        }

        $password = Str::password(24);
        User::create([
            'name' => trim((string) $this->option('name')) ?: 'Editor',
            'email' => $email,
            'password' => $password,
        ])->forceFill(['is_editor' => true])->save();

        $this->components->info("Editor criado: {$email}");
        $this->line("Senha inicial (exibida uma vez): {$password}");

        return self::SUCCESS;
    }
}
