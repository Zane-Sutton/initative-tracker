<?php

declare(strict_types=1);

namespace App;

/** Validation rules and defaults shared by the web forms and the JSON importer. */
final class Rules
{
    public const CHARACTER_DEFAULTS = [
        'level' => 1,
        'armor_class' => 10,
        'max_hp' => 1,
        'initiative_bonus' => 0,
    ];

    public const MONSTER_DEFAULTS = [
        'armor_class' => 10,
        'hp_average' => 1,
        'initiative_bonus' => 0,
    ];

    /** @param array<string,mixed> $input */
    public static function character(array $input): Validator
    {
        return (new Validator($input))
            ->string('name', 'Name', 100, required: true)
            ->string('player_name', 'Player', 100)
            ->string('class', 'Class', 100)
            ->int('level', 'Level', 1, 20)
            ->int('armor_class', 'Armor class', 0, 40)
            ->int('max_hp', 'Max HP', 1, 9999)
            ->int('initiative_bonus', 'Initiative bonus', -20, 30)
            ->int('passive_perception', 'Passive perception', 0, 60, required: false)
            ->string('notes', 'Notes', 65535);
    }

    /** @param array<string,mixed> $input */
    public static function monster(array $input): Validator
    {
        return (new Validator($input))
            ->string('name', 'Name', 100, required: true)
            ->string('size', 'Size', 20)
            ->string('creature_type', 'Type', 50)
            ->string('challenge_rating', 'Challenge rating', 10)
            ->int('armor_class', 'Armor class', 0, 40)
            ->int('hp_average', 'Average HP', 1, 9999)
            ->string('hp_formula', 'HP formula', 50)
            ->int('initiative_bonus', 'Initiative bonus', -20, 30)
            ->string('speed', 'Speed', 100)
            ->string('source', 'Source', 100)
            ->string('actions', 'Actions', 65535)
            ->json('stats', 'Stats');
    }
}
