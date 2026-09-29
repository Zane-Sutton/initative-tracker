<?php

declare(strict_types=1);

namespace App\Repositories;

final class MonsterRepository extends Repository
{
    protected function table(): string
    {
        return 'monsters';
    }

    protected function columns(): array
    {
        return [
            'name', 'size', 'creature_type', 'challenge_rating', 'armor_class',
            'hp_average', 'hp_formula', 'initiative_bonus', 'speed',
            'stats', 'actions', 'source',
        ];
    }
}
