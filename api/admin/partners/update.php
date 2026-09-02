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
$name = trim((string)input('name', ''));
$category = trim((string)input('category', ''));
$logo = trim((string)input('logo', ''));
$discount = trim((string)input('discount', ''));
$coupon = trim((string)input('coupon', ''));
$desc = trim((string)input('desc', ''));
$whatsapp = trim((string)input('whatsapp', ''));

if ($id <= 0 || $name === '' || $category === '' || $logo === '' || $discount === '' || $coupon === '' || $desc === '' || $whatsapp === '') {
    json_error('Por favor, preencha todos os campos obrigatórios.');
}

if (!in_array($category, ['construcao', 'acabamentos', 'pintura', 'outros'])) {
    json_error('Categoria inválida selecionada.');
}

$db = db();

try {
    // Verificar se existe o parceiro
    $partner = $db->fetch("SELECT id FROM partners WHERE id = ?", [$id]);
    if (!$partner) {
        json_error('Parceiro B2B não encontrado.', 404);
    }

    $db->execute(
        "UPDATE partners SET name = ?, logo = ?, category = ?, `desc` = ?, discount = ?, coupon = ?, whatsapp = ? WHERE id = ?",
        [$name, $logo, $category, $desc, $discount, $coupon, $whatsapp, $id]
    );

    json_ok([], 'Parceiro B2B atualizado com sucesso!');

} catch (PDOException $e) {
    json_error('Erro técnico ao atualizar parceiro B2B: ' . $e->getMessage(), 500);
}
