<?php

namespace App\Console\Commands;

use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetAllUserPasswords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:reset-passwords';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset all user passwords to 11223344';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Resetting user passwords...');

        $users = User::all();
        $bar = $this->output->createProgressBar(count($users));

        $bar->start();

        foreach ($users as $user) {
            $user->password = Hash::make('11223344');
            $user->save();
            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->info('All user passwords have been reset to 11223344.');
    }
}
