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

$productId   = (int)input('id', 0);
$name        = trim((string)input('name', ''));
$category    = trim((string)input('category', 'construcao'));
$price       = (float)input('price', 0.00);
$unit        = trim((string)input('unit', 'un'));
$discountPct = (int)input('discount_pct', 0);
$desc        = trim((string)input('desc', ''));
$stockStatus = trim((string)input('stock_status', 'in_stock'));
$imageUrl    = trim((string)input('image_url', ''));

if ($name === '') {
    json_error('O nome do material/produto é obrigatório.');
}

if ($price <= 0.00) {
    json_error('O preço unitário deve ser superior a zero.');
}

if (!in_array($category, ['construcao', 'acabamentos', 'pintura', 'outros'], true)) {
    $category = 'outros';
}

if (!in_array($stockStatus, ['in_stock', 'out_of_stock'], true)) {
    $stockStatus = 'in_stock';
}

// Processar upload de imagem
$imagePath = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploaded = Upload::file($_FILES['image'], 'products');
    if ($uploaded) {
        $imagePath = $uploaded;
    }
}
if (!$imagePath && $imageUrl !== '') {
    $imagePath = $imageUrl;
}

$db = db();

try {
    // 1. Obter ou auto-criar loja do vendedor para o utilizador atual
    $store = $db->fetch("SELECT id, store_name, whatsapp FROM vendor_stores WHERE user_id = ?", [$user['id']]);
    
    if (!$store) {
        // Auto-criar loja básica
        $storeName = $user['name'] . ' (Fornecedor)';
        $whatsapp  = '244923972131';
        $db->execute("
            INSERT INTO vendor_stores (user_id, store_name, category, whatsapp, location)
            VALUES (?, ?, 'construcao', ?, 'Luanda, Angola')
        ", [$user['id'], $storeName, $whatsapp]);

        $store = $db->fetch("SELECT id, store_name, whatsapp FROM vendor_stores WHERE user_id = ?", [$user['id']]);
    }

    $storeId = (int)$store['id'];
    $partnerName = $store['store_name'];
    $whatsapp = $store['whatsapp'];

    // 2. Garantir coluna vendor_store_id e stock_status na tabela marketplace_products
    try {
        $db->query("SELECT vendor_store_id FROM marketplace_products LIMIT 1");
    } catch (PDOException $eCol) {
        $db->execute("ALTER TABLE marketplace_products ADD COLUMN vendor_store_id INT DEFAULT NULL");
        $db->execute("ALTER TABLE marketplace_products ADD COLUMN stock_status VARCHAR(20) NOT NULL DEFAULT 'in_stock'");
    }

    if ($productId > 0) {
        // Atualizar produto existente (Verificar propriedade)
        $existing = $db->fetch("SELECT id, image, user_id FROM marketplace_products WHERE id = ?", [$productId]);
        if (!$existing) {
            json_error('Produto não encontrado.', 404);
        }

        $isOwner = (int)$existing['user_id'] === (int)$user['id'];
        $isAdmin = (int)($user['is_admin'] ?? 0) === 1;

        if (!$isOwner && !$isAdmin) {
            json_error('Permissão negada. Apenas o proprietário pode alterar este produto.', 403);
        }

        $finalImage = $imagePath ?: ($existing['image'] ?: 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=300&auto=format&fit=crop&q=60');

        $db->execute(
            "UPDATE marketplace_products 
             SET name = ?, category = ?, partner = ?, whatsapp = ?, price = ?, unit = ?, discount_pct = ?, `desc` = ?, stock_status = ?, image = ?, vendor_store_id = ?
             WHERE id = ?",
            [$name, $category, $partnerName, $whatsapp, $price, $unit, $discountPct, $desc, $stockStatus, $finalImage, $storeId, $productId]
        );

        set_flash_message('success', 'Produto da loja atualizado com sucesso!');
        json_ok([], 'Produto atualizado.');

    } else {
        // Criar novo produto
        $finalImage = $imagePath ?: 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=300&auto=format&fit=crop&q=60';

        $db->execute(
            "INSERT INTO marketplace_products (user_id, vendor_store_id, name, category, partner, whatsapp, price, unit, discount_pct, `desc`, stock_status, image)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$user['id'], $storeId, $name, $category, $partnerName, $whatsapp, $price, $unit, $discountPct, $desc, $stockStatus, $finalImage]
        );

        set_flash_message('success', 'Novo produto adicionado à sua loja com sucesso!');
        json_ok([], 'Produto adicionado.');
    }

} catch (PDOException $e) {
    json_error('Erro técnico ao guardar produto: ' . $e->getMessage(), 500);
}
