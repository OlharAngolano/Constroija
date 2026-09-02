<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

if (!validate_csrf()) {
    json_error('Token CSRF inválido.', 403);
}

$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

$projectId = (int)input('project_id', 0);
$name      = trim((string)input('name', ''));
$price     = (float)input('price', 0.00);
$quantity  = (float)input('quantity', 1.00);
$unit      = trim((string)input('unit', 'un'));
$phase     = trim((string)input('phase', ''));
$pDate     = input('purchase_date') ?: date('Y-m-d');
$type      = trim((string)input('type', 'material')); // 'material','labor','equipment','service','other'

$pLocation   = trim((string)input('purchase_location', ''));
$supplier    = trim((string)input('supplier', ''));
$youtubeLink = trim((string)input('youtube_link', ''));
$notes       = trim((string)input('notes', ''));

if ($projectId <= 0) {
    json_error('ID de projeto inválido.');
}

if ($name === '') {
    json_error('O nome do material ou serviço é obrigatório.');
}

if ($phase === '') {
    json_error('A fase do projeto associada é obrigatória.');
}

$db = db();

try {
    // 1. Validar privilégio de escrita (apenas proprietários ou gestores)
    $manager = $db->fetch(
        "SELECT role FROM project_managers WHERE project_id = ? AND user_id = ?",
        [$projectId, $user['id']]
    );

    if (!$manager || !in_array($manager['role'], ['owner', 'manager'])) {
        json_error('Permissão negada. Apenas gestores de obra podem lançar despesas.', 403);
    }

    $photoUrl = null;
    $receiptUrl = null;

    // 2. Processar upload de foto do produto
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photoUrl = Upload::file($_FILES['photo'], 'expenses/photos');
    }

    // 3. Processar upload do recibo de compra (PDF / Imagem)
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $receiptUrl = Upload::file($_FILES['receipt'], 'expenses/receipts');
    }

    // 4. Inserir despesa
    $inserted = $db->execute(
        "INSERT INTO expenses (
            project_id, user_id, name, price, quantity, unit, phase, 
            purchase_date, purchase_location, supplier, photo_url, receipt_url, youtube_link, type, notes
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $projectId, $user['id'], $name, $price, $quantity, $unit, $phase,
            $pDate, $pLocation, $supplier, $photoUrl, $receiptUrl, $youtubeLink, $type, $notes
        ]
    );

    if (!$inserted) {
        json_error('Erro ao guardar os dados da despesa.');
    }

    set_flash_message('success', "Despesa \"{$name}\" lançada com sucesso!");
    json_ok([], 'Despesa lançada com sucesso.');

} catch (PDOException $e) {
    json_error('Erro técnico ao registar despesa.', 500);
}
