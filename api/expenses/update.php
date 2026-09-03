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

$expenseId = (int)input('expense_id', 0);
if ($expenseId <= 0) {
    $expenseId = (int)input('id', 0);
}

$name      = trim((string)input('name', ''));
$price     = (float)input('price', 0.00);
$quantity  = (float)input('quantity', 1.00);
$unit      = trim((string)input('unit', 'un'));
$phase     = trim((string)input('phase', ''));
$pDate     = input('purchase_date') ?: date('Y-m-d');
$type      = trim((string)input('type', 'material'));

$pLocation   = trim((string)input('purchase_location', ''));
$supplier    = trim((string)input('supplier', ''));
$youtubeLink = trim((string)input('youtube_link', ''));
$notes       = trim((string)input('notes', ''));

if ($expenseId <= 0) {
    json_error('ID de despesa inválido.');
}

if ($name === '') {
    json_error('O nome do material ou serviço é obrigatório.');
}

if ($phase === '') {
    json_error('A fase da obra é obrigatória.');
}

$db = db();

try {
    // Obter dados atuais da despesa
    $expense = $db->fetch("SELECT * FROM expenses WHERE id = ? AND deleted_at IS NULL", [$expenseId]);
    if (!$expense) {
        json_error('Despesa não encontrada.', 404);
    }

    $projectId = (int)$expense['project_id'];

    // 1. Validar cargo na equipa (apenas donos ou gestores)
    $manager = $db->fetch(
        "SELECT role FROM project_managers WHERE project_id = ? AND user_id = ?",
        [$projectId, $user['id']]
    );

    if (!$manager || !in_array($manager['role'], ['owner', 'manager'])) {
        json_error('Permissão negada. Apenas gestores podem modificar lançamentos.', 403);
    }

    $photoUrl = $expense['photo_url'];
    $receiptUrl = $expense['receipt_url'];
    $newPhoto = null;
    $newReceipt = null;

    // Processar novos uploads se enviados (os ficheiros antigos e as respetivas
    // linhas em documents só são removidos DEPOIS da atualização com sucesso)
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploaded = Upload::file($_FILES['photo'], 'expenses/photos');
        if ($uploaded) {
            $newPhoto = $uploaded;
        }
    }

    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $uploaded = Upload::file($_FILES['receipt'], 'expenses/receipts');
        if ($uploaded) {
            $newReceipt = $uploaded;
        }
    }

    // 2. Construir Histórico de Edições para Auditoria em JSON
    $history = json_decode($expense['edit_history'] ?? '[]', true);
    if (!is_array($history)) {
        $history = [];
    }

    $history[] = [
        'name'              => $expense['name'],
        'price'             => (float)$expense['price'],
        'quantity'          => (float)$expense['quantity'],
        'unit'              => $expense['unit'],
        'phase'             => $expense['phase'],
        'purchase_date'     => $expense['purchase_date'],
        'purchase_location' => $expense['purchase_location'],
        'supplier'          => $expense['supplier'],
        'type'              => $expense['type'],
        'notes'             => $expense['notes'],
        'edited_by'         => (int)$user['id'],
        'edited_by_name'    => $user['name'],
        'edited_at'         => date('Y-m-d H:i:s')
    ];
    $historyJson = json_encode($history, JSON_UNESCAPED_UNICODE);

    // 3. Efetuar atualização na Base de Dados
    $db->execute(
        "UPDATE expenses SET 
            name = ?, 
            price = ?, 
            quantity = ?, 
            unit = ?, 
            phase = ?, 
            purchase_date = ?, 
            purchase_location = ?, 
            supplier = ?, 
            photo_url = ?, 
            receipt_url = ?, 
            youtube_link = ?, 
            type = ?, 
            notes = ?, 
            edit_history = ? 
         WHERE id = ?",
        [
            $name, $price, $quantity, $unit, $phase, 
            $pDate, $pLocation, $supplier, $photoUrl, $receiptUrl, $youtubeLink, 
            $type, $notes, $historyJson, $expenseId
        ]
    );

    // (CJ-07) Atualizar registos de documentos privados e limpar os antigos.
    // Se a tabela `documents` (migração 001/CJ-07) ainda não existir, mantemos
    // apenas os caminhos legados em photo_url/receipt_url.
    $pendingFileDeletes = [];
    $documentsAvailable = table_available('documents');

    if ($newPhoto && $documentsAvailable) {
        $oldDoc = $db->fetch(
            "SELECT id, file_path FROM documents WHERE expense_id = ? AND kind = 'expense_photo' ORDER BY id DESC LIMIT 1",
            [$expenseId]
        );
        if ($oldDoc) {
            $db->execute("DELETE FROM documents WHERE id = ?", [$oldDoc['id']]);
            if ($oldDoc['file_path'] && file_exists(ROOT_DIR . '/' . $oldDoc['file_path'])) {
                $pendingFileDeletes[] = ROOT_DIR . '/' . $oldDoc['file_path'];
            }
        }
        $db->execute(
            "INSERT INTO documents (project_id, owner_id, expense_id, kind, file_path, original_name) VALUES (?, ?, ?, 'expense_photo', ?, ?)",
            [$projectId, (int)$user['id'], $expenseId, $newPhoto, basename($newPhoto)]
        );
        $photoUrl = $newPhoto;
    }

    if ($newReceipt && $documentsAvailable) {
        $oldDoc = $db->fetch(
            "SELECT id, file_path FROM documents WHERE expense_id = ? AND kind = 'receipt' ORDER BY id DESC LIMIT 1",
            [$expenseId]
        );
        if ($oldDoc) {
            $db->execute("DELETE FROM documents WHERE id = ?", [$oldDoc['id']]);
            if ($oldDoc['file_path'] && file_exists(ROOT_DIR . '/' . $oldDoc['file_path'])) {
                $pendingFileDeletes[] = ROOT_DIR . '/' . $oldDoc['file_path'];
            }
        }
        $db->execute(
            "INSERT INTO documents (project_id, owner_id, expense_id, kind, file_path, original_name) VALUES (?, ?, ?, 'receipt', ?, ?)",
            [$projectId, (int)$user['id'], $expenseId, $newReceipt, basename($newReceipt)]
        );
        $receiptUrl = $newReceipt;
    }

    // Guardar os novos caminhos na linha da despesa
    if ($newPhoto || $newReceipt) {
        $db->execute(
            "UPDATE expenses SET photo_url = ?, receipt_url = ? WHERE id = ?",
            [$newPhoto ?: $photoUrl, $newReceipt ?: $receiptUrl, $expenseId]
        );
    }

    foreach ($pendingFileDeletes as $oldFile) {
        @unlink($oldFile);
    }

    set_flash_message('success', 'Lançamento financeiro atualizado com sucesso!');
    json_ok([], 'Despesa atualizada.');

} catch (PDOException $e) {
    json_internal_error('atualizar despesa', $e);
}
