<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/middleware.php';

// Exige autenticação administrativa
$adminUser = middleware_api_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

if (!validate_csrf()) {
    json_error('Token CSRF inválido.', 403);
}

$id = (int)input('id', 0);

if ($id <= 0) {
    json_error('ID de parceiro inválido.');
}

$db = db();

try {
    // Verificar se existe o parceiro
    $partner = $db->fetch("SELECT id FROM partners WHERE id = ?", [$id]);
    if (!$partner) {
        json_error('Parceiro B2B não encontrado.', 404);
    }

    $db->execute("DELETE FROM partners WHERE id = ?", [$id]);

    json_ok([], 'Parceiro B2B removido com sucesso!');

} catch (PDOException $e) {
    json_error('Erro técnico ao remover parceiro B2B: ' . $e->getMessage(), 500);
}
