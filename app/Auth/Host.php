<?php

namespace App\Auth;

use App\Models\Game;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The shared screen (e.g. a TV) that created and runs a room.
 *
 * Hosts don't have accounts; they are identified by the host token cookie
 * that was set when the room was created.
 */
final readonly class Host implements Authenticatable
{
    public function __construct(public Game $game) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return $this->game->id;
    }

    /**
     * Presence channel member id. Prefixed so it can never collide with a player's.
     */
    public function getAuthIdentifierForBroadcasting(): string
    {
        return "host:{$this->game->id}";
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        //
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
