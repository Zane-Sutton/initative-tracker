<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Controllers\CharacterController;
use App\Controllers\EncounterController;
use App\Controllers\HomeController;
use App\Controllers\ImportController;
use App\Controllers\MonsterController;
use App\Database;
use App\Router;

use function App\abort;

$config = require dirname(__DIR__) . '/config/config.php';

if ($config['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

session_start();
Database::configure($config['db']);

$router = new Router();

$router->get('/', [new HomeController(), 'index']);

// Characters. Static paths (/new) are registered before {id} routes.
$characters = new CharacterController();
$router->get('/characters', [$characters, 'index']);
$router->get('/characters/new', [$characters, 'create']);
$router->post('/characters', [$characters, 'store']);
$router->get('/characters/{id}/edit', [$characters, 'edit']);
$router->post('/characters/{id}', [$characters, 'update']);
$router->post('/characters/{id}/delete', [$characters, 'destroy']);

// Monsters
$monsters = new MonsterController();
$router->get('/monsters', [$monsters, 'index']);
$router->get('/monsters/new', [$monsters, 'create']);
$router->post('/monsters', [$monsters, 'store']);
$router->get('/monsters/{id}', [$monsters, 'show']);
$router->get('/monsters/{id}/edit', [$monsters, 'edit']);
$router->post('/monsters/{id}', [$monsters, 'update']);
$router->post('/monsters/{id}/delete', [$monsters, 'destroy']);

// Encounters
$encounters = new EncounterController();
$router->get('/encounters', [$encounters, 'index']);
$router->get('/encounters/new', [$encounters, 'create']);
$router->post('/encounters', [$encounters, 'store']);
$router->get('/encounters/{id}', [$encounters, 'show']);
$router->get('/encounters/{id}/edit', [$encounters, 'edit']);
$router->post('/encounters/{id}', [$encounters, 'update']);
$router->post('/encounters/{id}/delete', [$encounters, 'destroy']);

// Encounter Combat & Participant Operations
$router->post('/encounters/{id}/start', [$encounters, 'start']);
$router->post('/encounters/{id}/next', [$encounters, 'nextTurn']);
$router->post('/encounters/{id}/prev', [$encounters, 'prevTurn']);
$router->post('/encounters/{id}/reset', [$encounters, 'reset']);
$router->post('/encounters/{id}/finish', [$encounters, 'finish']);
$router->post('/encounters/{id}/roll-initiative', [$encounters, 'rollAllInitiative']);
$router->post('/encounters/{id}/sort', [$encounters, 'sortTurnOrder']);
$router->post('/encounters/{id}/participants/character', [$encounters, 'addCharacter']);
$router->post('/encounters/{id}/participants/monster', [$encounters, 'addMonster']);
$router->post('/encounters/{id}/participants/custom', [$encounters, 'addCustom']);
$router->post('/encounters/{id}/participants/{participantId}', [$encounters, 'updateParticipant']);
$router->post('/encounters/{id}/participants/{participantId}/hp', [$encounters, 'adjustHp']);
$router->post('/encounters/{id}/participants/{participantId}/roll-initiative', [$encounters, 'rollParticipantInitiative']);
$router->post('/encounters/{id}/participants/{participantId}/condition', [$encounters, 'toggleCondition']);
$router->post('/encounters/{id}/participants/{participantId}/toggle-active', [$encounters, 'toggleActive']);
$router->post('/encounters/{id}/participants/{participantId}/delete', [$encounters, 'deleteParticipant']);

// Live Dice Roller API
$router->get('/api/dice', [$encounters, 'rollDice']);
$router->post('/api/dice', [$encounters, 'rollDice']);

// JSON import
$import = new ImportController();
$router->get('/import', [$import, 'form']);
$router->post('/import', [$import, 'run']);
$router->get('/import/sample', [$import, 'sample']);

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (Throwable $e) {
    error_log((string) $e);

    // Discard any half-rendered template output before showing the error page.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    abort(500, $config['debug'] ? $e->getMessage() : 'Something went wrong.');
}
