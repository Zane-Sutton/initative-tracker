<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\View;

final class HomeController
{
    /**
     * Index method retrieves counts of records from different tables and renders the 'home' view with the results.
     *
     * @return void
     */
    public function index(): void
    {
        $dbOk = true;
        $counts = [];
        $error = null;

        try {
            $pdo = Database::connection();
            foreach (['characters', 'monsters', 'encounters'] as $table) {
                $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            }
        } catch (\Throwable $e) {
            $dbOk = false;
            $error = $e->getMessage();
        }

        View::render('home', [
            'dbOk' => $dbOk,
            'counts' => $counts,
            'error' => $error,
        ]);
    }
}
