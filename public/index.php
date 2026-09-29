<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Controllers\CharacterController;
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
