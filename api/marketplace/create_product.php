<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

if (!validate_csrf()) {
    json_error('Token CSRF inválido ou expirado.', 403);
}

$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

$name     = trim((string)input('name', ''));
$category = trim((string)input('category', 'construcao'));
$partner  = trim((string)input('partner', ''));
$whatsapp = trim((string)input('whatsapp', ''));
$price    = (float)input('price', 0.00);
$unit     = trim((string)input('unit', 'un'));
$desc     = trim((string)input('desc', ''));
$imageUrl = trim((string)input('image_url', ''));

if ($name === '') {
    json_error('O nome do material ou produto é obrigatório.');
}

if ($partner === '') {
    $partner = $user['name'] . ' (Fornecedor)';
}

if ($whatsapp === '') {
    json_error('O número de WhatsApp para contacto é obrigatório.');
}

if ($price <= 0.00) {
    json_error('O preço unitário deve ser superior a zero.');
}

if (!in_array($category, ['construcao', 'acabamentos', 'pintura', 'outros'], true)) {
    $category = 'outros';
}

// Processar upload de imagem se enviado via ficheiro
$imagePath = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploaded = Upload::file($_FILES['image'], 'products');
    if ($uploaded) {
        $imagePath = $uploaded;
    }
}

// Fallback para URL de imagem se não houve upload
if (!$imagePath) {
    if ($imageUrl !== '' && (strpos($imageUrl, 'http://') === 0 || strpos($imageUrl, 'https://') === 0)) {
        $imagePath = $imageUrl;
    } else {
        $imagePath = 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=300&auto=format&fit=crop&q=60';
    }
}

$db = db();

try {
    // Garantir que a tabela marketplace_products existe
    $db->execute("
        CREATE TABLE IF NOT EXISTS marketplace_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(50) NOT NULL DEFAULT 'outros',
            partner VARCHAR(255) NOT NULL,
            whatsapp VARCHAR(50) NOT NULL,
            price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            unit VARCHAR(50) NOT NULL DEFAULT 'un',
            discount_pct INT NOT NULL DEFAULT 0,
            coupon VARCHAR(50) DEFAULT NULL,
            image VARCHAR(500) DEFAULT NULL,
            `desc` TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES profiles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $inserted = $db->execute(
        "INSERT INTO marketplace_products (user_id, name, category, partner, whatsapp, price, unit, image, `desc`) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$user['id'], $name, $category, $partner, $whatsapp, $price, $unit, $imagePath, $desc]
    );

    if (!$inserted) {
        json_error('Erro ao guardar o anúncio do produto.');
    }

    set_flash_message('success', 'Material anunciado no Marketplace com sucesso!');
    json_ok([], 'Anúncio publicado com sucesso!');

} catch (PDOException $e) {
    json_error('Erro técnico ao registar o anúncio: ' . $e->getMessage(), 500);
}
