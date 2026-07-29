<?php

namespace App\Services\Relations;

use App\Models\Mute;
use App\Models\User;
use Exception;

class MuteService
{
    private User $muterData;
    private User $mutedData;

    public function __construct(int|User $muter, int|User $muted)
    {
        $this->muterData = $muter instanceof User ? $muter : User::activeById($muter)->first();
        $this->mutedData = $muted instanceof User ? $muted : User::activeById($muted)->first();

        if(! $this->muterData || ! $this->mutedData) {
            throw new Exception('Muter or muted user not found.');
        }
    }

    public function isMuted(): bool
    {
        return Mute::where('muter_id', $this->muterData->id)
            ->where('muted_id', $this->mutedData->id)
            ->exists();
    }

    public function mute(): void
    {
        Mute::firstOrCreate([
            'muter_id' => $this->muterData->id,
            'muted_id' => $this->mutedData->id
        ]);
    }

    public function unmute(): void
    {
        Mute::where('muter_id', $this->muterData->id)
            ->where('muted_id', $this->mutedData->id)
            ->delete();
    }
}
