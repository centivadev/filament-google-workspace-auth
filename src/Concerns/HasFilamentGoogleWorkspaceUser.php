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

    /**
     * Models without an is_active column are always active. Note that property_exists() cannot
     * be used here: Eloquent keeps column values in $attributes, not as declared properties.
     */
    public function isActive(): bool
    {
        if (! array_key_exists('is_active', $this->getAttributes())) {
            return true;
        }

        return (bool) $this->is_active;
    }
}
