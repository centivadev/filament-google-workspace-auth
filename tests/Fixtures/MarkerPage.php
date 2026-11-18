<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Tests\Fixtures;

use Filament\Pages\Page;

class MarkerPage extends Page
{
    /**
     * @var array<int, string>
     */
    public static array $calls = [];

    protected static ?string $slug = 'marker';

    protected string $view = 'tests::marker-page';

    public function mark(): void
    {
        static::$calls[] = 'mark';
    }
}
