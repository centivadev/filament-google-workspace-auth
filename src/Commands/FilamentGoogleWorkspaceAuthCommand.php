<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Commands;

use Illuminate\Console\Command;

class FilamentGoogleWorkspaceAuthCommand extends Command
{
    public $signature = 'filament-google-workspace-auth';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
