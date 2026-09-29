<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Importer;
use App\Repositories\CharacterRepository;
use App\Repositories\MonsterRepository;
use App\View;

use function App\abort;
use function App\flash;
use function App\redirect;
use function App\verify_csrf;

final class ImportController
{
    private const MAX_BYTES = 2_000_000;

    private Importer $importer;

    public function __construct()
    {
        $this->importer = new Importer(new CharacterRepository(), new MonsterRepository());
    }

    public function form(): void
    {
        $this->render('', false, []);
    }

    public function run(): void
    {
        verify_csrf();

        $update = !empty($_POST['update_existing']);
        [$json, $errors] = $this->readInput();

        if ($errors === []) {
            if (trim($json) === '') {
                $errors[] = 'Paste some JSON or choose a file.';
            } elseif (strlen($json) > self::MAX_BYTES) {
                $errors[] = 'The input is too large (limit 2 MB).';
            }
        }

        if ($errors === []) {
            $result = $this->importer->import($json, $update);
            $errors = $result['errors'];
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->render($json, $update, $errors);

            return;
        }

        $c = $result['characters'];
        $m = $result['monsters'];
        flash(sprintf(
            'Import complete. Characters: %d created, %d updated, %d skipped. Monsters: %d created, %d updated, %d skipped.',
            $c['created'], $c['updated'], $c['skipped'],
            $m['created'], $m['updated'], $m['skipped']
        ));
        redirect('/import');
    }

    /** Serve the bundled sample document (used by the "Load sample" button). */
    public function sample(): void
    {
        $file = dirname(__DIR__, 2) . '/samples/import-sample.json';

        if (!is_readable($file)) {
            abort(404, 'Sample file not found.');
        }

        header('Content-Type: application/json; charset=utf-8');
        readfile($file);
    }

    /**
     * An uploaded file wins over pasted text.
     *
     * @return array{0:string,1:list<string>} [json, errors]
     */
    private function readInput(): array
    {
        $file = $_FILES['file'] ?? null;

        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
                return ['', ['The file could not be uploaded (error code ' . (int) $file['error'] . ').']];
            }

            return [(string) file_get_contents($file['tmp_name']), []];
        }

        $pasted = $_POST['json'] ?? '';

        return [is_string($pasted) ? $pasted : '', []];
    }

    /** @param list<string> $errors */
    private function render(string $json, bool $update, array $errors): void
    {
        View::render('import/form', ['json' => $json, 'update' => $update, 'errors' => $errors]);
    }
}
