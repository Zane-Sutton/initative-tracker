CREATE TABLE campaigns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Player characters (templates; live state lives on encounter_participants)
CREATE TABLE characters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NULL,
    name VARCHAR(100) NOT NULL,
    player_name VARCHAR(100) NULL,
    class VARCHAR(100) NULL,
    level TINYINT UNSIGNED NOT NULL DEFAULT 1,
    armor_class SMALLINT NOT NULL DEFAULT 10,
    max_hp SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    initiative_bonus SMALLINT NOT NULL DEFAULT 0,
    passive_perception TINYINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_characters_campaign FOREIGN KEY (campaign_id)
        REFERENCES campaigns (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reusable monster stat blocks
CREATE TABLE monsters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    size VARCHAR(20) NULL,
    creature_type VARCHAR(50) NULL,
    challenge_rating VARCHAR(10) NULL,       -- text so "1/8", "1/4" work
    armor_class SMALLINT NOT NULL DEFAULT 10,
    hp_average SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    hp_formula VARCHAR(50) NULL,             -- e.g. "2d6+2"
    initiative_bonus SMALLINT NOT NULL DEFAULT 0,
    speed VARCHAR(100) NULL,
    stats JSON NULL,                         -- ability scores, saves, skills, etc.
    actions TEXT NULL,
    source VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_monsters_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE encounters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    status ENUM('planned', 'active', 'finished') NOT NULL DEFAULT 'planned',
    current_round SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    current_turn_order INT NULL,             -- turn_order of whoever is acting
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_encounters_campaign FOREIGN KEY (campaign_id)
        REFERENCES campaigns (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Live combatants: one row per creature in an encounter
CREATE TABLE encounter_participants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    encounter_id INT UNSIGNED NOT NULL,
    character_id INT UNSIGNED NULL,
    monster_id INT UNSIGNED NULL,
    display_name VARCHAR(100) NOT NULL,      -- e.g. "Goblin 2"
    initiative SMALLINT NULL,                -- rolled total
    initiative_bonus SMALLINT NOT NULL DEFAULT 0,  -- kept for tie-breaking
    turn_order INT NULL,                     -- resolved position (1 = first)
    armor_class SMALLINT NOT NULL DEFAULT 10,
    max_hp SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    current_hp SMALLINT NOT NULL DEFAULT 1,
    temp_hp SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1, -- 0 = removed/dead but kept for the record
    notes TEXT NULL,
    CONSTRAINT fk_participants_encounter FOREIGN KEY (encounter_id)
        REFERENCES encounters (id) ON DELETE CASCADE,
    CONSTRAINT fk_participants_character FOREIGN KEY (character_id)
        REFERENCES characters (id) ON DELETE SET NULL,
    CONSTRAINT fk_participants_monster FOREIGN KEY (monster_id)
        REFERENCES monsters (id) ON DELETE SET NULL,
    INDEX idx_participants_order (encounter_id, turn_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE conditions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE participant_conditions (
    participant_id INT UNSIGNED NOT NULL,
    condition_id INT UNSIGNED NOT NULL,
    applied_round SMALLINT UNSIGNED NULL,
    PRIMARY KEY (participant_id, condition_id),
    CONSTRAINT fk_pc_participant FOREIGN KEY (participant_id)
        REFERENCES encounter_participants (id) ON DELETE CASCADE,
    CONSTRAINT fk_pc_condition FOREIGN KEY (condition_id)
        REFERENCES conditions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
