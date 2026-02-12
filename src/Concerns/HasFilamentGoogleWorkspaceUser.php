<?php

namespace CentivaDev\FilamentGoogleWorkspaceAuth\Concerns;

trait HasFilamentGoogleWorkspaceUser
{
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url ?? null;
    }

    public function isBanned(): bool
    {
        return ! empty($this->banned_at);
    }
}
