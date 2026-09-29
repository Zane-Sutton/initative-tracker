<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Controllers\HomeController;
use App\Database;
use App\Router;

$config = require dirname(__DIR__) . '/config/config.php';

if ($config['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

Database::configure($config['db']);

$router = new Router();
$router->get('/', [new HomeController(), 'index']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
