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

$name = trim((string)input('name', ''));
$category = trim((string)input('category', ''));
$logo = trim((string)input('logo', ''));
$discount = trim((string)input('discount', ''));
$coupon = trim((string)input('coupon', ''));
$desc = trim((string)input('desc', ''));
$whatsapp = trim((string)input('whatsapp', ''));

if ($name === '' || $category === '' || $logo === '' || $discount === '' || $coupon === '' || $desc === '' || $whatsapp === '') {
    json_error('Por favor, preencha todos os campos obrigatórios.');
}

if (!in_array($category, ['construcao', 'acabamentos', 'pintura', 'outros'])) {
    json_error('Categoria inválida selecionada.');
}

$db = db();

try {
    $db->execute(
        "INSERT INTO partners (name, logo, category, `desc`, discount, coupon, whatsapp) VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$name, $logo, $category, $desc, $discount, $coupon, $whatsapp]
    );

    json_ok([], 'Parceiro B2B adicionado com sucesso!');

} catch (PDOException $e) {
    json_internal_error('Erro técnico ao salvar parceiro B2B: ', $e);
}
