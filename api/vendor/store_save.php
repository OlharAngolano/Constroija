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

$storeName   = trim((string)input('store_name', ''));
$category    = trim((string)input('category', 'construcao'));
$whatsapp    = trim((string)input('whatsapp', ''));
$location    = trim((string)input('location', 'Luanda, Angola'));
$description = trim((string)input('description', ''));
$logoUrl     = trim((string)input('logo_url', ''));
$bannerUrl   = trim((string)input('banner_url', ''));

if ($storeName === '') {
    json_error('O nome da empresa ou loja é obrigatório.');
}

if ($whatsapp === '') {
    json_error('O número de WhatsApp da loja é obrigatório.');
}

// Upload de Logótipo
$logoPath = null;
if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $uploaded = Upload::file($_FILES['logo'], 'vendors');
    if ($uploaded) {
        $logoPath = $uploaded;
    }
}
if (!$logoPath && $logoUrl !== '') {
    $logoPath = $logoUrl;
}

// Upload de Banner
$bannerPath = null;
if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
    $uploadedBanner = Upload::file($_FILES['banner'], 'vendors');
    if ($uploadedBanner) {
        $bannerPath = $uploadedBanner;
    }
}
if (!$bannerPath && $bannerUrl !== '') {
    $bannerPath = $bannerUrl;
}

$db = db();

try {
    // Garantir tabela vendor_stores
    $db->execute("
        CREATE TABLE IF NOT EXISTS vendor_stores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            store_name VARCHAR(255) NOT NULL,
            logo_url VARCHAR(500) DEFAULT NULL,
            banner_url VARCHAR(500) DEFAULT NULL,
            category VARCHAR(50) NOT NULL DEFAULT 'construcao',
            description TEXT DEFAULT NULL,
            whatsapp VARCHAR(50) NOT NULL,
            location VARCHAR(255) DEFAULT 'Luanda, Angola',
            is_verified TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES profiles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $existing = $db->fetch("SELECT id, logo_url, banner_url FROM vendor_stores WHERE user_id = ?", [$user['id']]);

    if ($existing) {
        $finalLogo = $logoPath ?: ($existing['logo_url'] ?: 'https://images.unsplash.com/photo-1581094288338-2314dddb7eed?w=150&auto=format&fit=crop&q=60');
        $finalBanner = $bannerPath ?: ($existing['banner_url'] ?: 'https://images.unsplash.com/photo-1541888946425-d0fbb186a5b3?w=1200&auto=format&fit=crop&q=80');

        $db->execute(
            "UPDATE vendor_stores 
             SET store_name = ?, category = ?, whatsapp = ?, location = ?, description = ?, logo_url = ?, banner_url = ?
             WHERE user_id = ?",
            [$storeName, $category, $whatsapp, $location, $description, $finalLogo, $finalBanner, $user['id']]
        );
    } else {
        $finalLogo = $logoPath ?: 'https://images.unsplash.com/photo-1581094288338-2314dddb7eed?w=150&auto=format&fit=crop&q=60';
        $finalBanner = $bannerPath ?: 'https://images.unsplash.com/photo-1541888946425-d0fbb186a5b3?w=1200&auto=format&fit=crop&q=80';

        $db->execute(
            "INSERT INTO vendor_stores (user_id, store_name, category, whatsapp, location, description, logo_url, banner_url)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$user['id'], $storeName, $category, $whatsapp, $location, $description, $finalLogo, $finalBanner]
        );
    }

    set_flash_message('success', 'Perfil da loja atualizado com sucesso!');
    json_ok([], 'Loja atualizada.');

} catch (PDOException $e) {
    json_error('Erro ao guardar perfil da loja: ' . $e->getMessage(), 500);
}
