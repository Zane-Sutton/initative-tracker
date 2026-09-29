<?php

declare(strict_types=1);

namespace App\Repositories;

final class CharacterRepository extends Repository
{
    protected function table(): string
    {
        return 'characters';
    }

    protected function columns(): array
    {
        return [
            'name', 'player_name', 'class', 'level', 'armor_class',
            'max_hp', 'initiative_bonus', 'passive_perception', 'notes',
        ];
    }
}
