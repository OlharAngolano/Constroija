<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Exige autenticação de utilizador
middleware_require_auth();

$user = current_user();
$db = db();

// Auto-migração da tabela 'vendor_stores' e 'marketplace_products'
try {
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

    try {
        $db->query("SELECT vendor_store_id FROM marketplace_products LIMIT 1");
    } catch (PDOException $eCol) {
        $db->execute("ALTER TABLE marketplace_products ADD COLUMN vendor_store_id INT DEFAULT NULL");
        $db->execute("ALTER TABLE marketplace_products ADD COLUMN stock_status VARCHAR(20) NOT NULL DEFAULT 'in_stock'");
    }
} catch (PDOException $e) {
    // Tabela pronta
}

// Obter ou auto-inicializar dados da loja do vendedor
$store = $db->fetch("SELECT * FROM vendor_stores WHERE user_id = ?", [$user['id']]);
if (!$store) {
    $defaultStoreName = $user['name'] . ' (Fornecedor)';
    $defaultWhatsapp  = '244923972131';
    $db->execute(
        "INSERT INTO vendor_stores (user_id, store_name, category, whatsapp, location, description, logo_url, banner_url)
         VALUES (?, ?, 'construcao', ?, 'Luanda, Angola', 'Fornecedor parceiro de materiais de construção civil.', ?, ?)",
        [
            $user['id'],
            $defaultStoreName,
            $defaultWhatsapp,
            'https://images.unsplash.com/photo-1581094288338-2314dddb7eed?w=150&auto=format&fit=crop&q=60',
            'https://images.unsplash.com/photo-1541888946425-d0fbb186a5b3?w=1200&auto=format&fit=crop&q=80'
        ]
    );
    $store = $db->fetch("SELECT * FROM vendor_stores WHERE user_id = ?", [$user['id']]);
}

// Carregar catálogo de produtos da loja do vendedor
$products = $db->fetchAll(
    "SELECT * FROM marketplace_products WHERE user_id = ? OR vendor_store_id = ? ORDER BY id DESC",
    [$user['id'], $store['id']]
);

$title = 'Painel do Vendedor — Gestão da Loja';
require_once __DIR__ . '/../../templates/header.php';
?>

<style nonce="<?php echo Security::getNonce(); ?>">
    .store-banner-header {
        position: relative;
        height: 180px;
        border-radius: var(--radius-lg);
        overflow: hidden;
        background-size: cover;
        background-position: center;
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-md);
        margin-bottom: 24px;
    }

    .store-banner-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(10, 15, 30, 0.95) 0%, rgba(10, 15, 30, 0.4) 100%);
        padding: 20px 24px;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
    }

    .store-logo-img {
        width: 72px;
        height: 72px;
        border-radius: 16px;
        object-fit: cover;
        border: 2px solid white;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    }

    .store-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .tab-btn-group {
        display: flex;
        gap: 10px;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 12px;
        margin-bottom: 24px;
    }

    .tab-btn {
        background: transparent;
        border: none;
        color: var(--text-secondary);
        font-weight: 700;
        font-size: 14px;
        padding: 8px 16px;
        border-radius: var(--radius-md);
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
    }

    .tab-btn.active {
        background: rgba(16, 185, 129, 0.1);
        color: var(--accent-success);
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    @media (max-width: 768px) {
        .store-banner-header {
            height: auto;
        }
        .store-banner-overlay {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div style="max-width: 1050px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px;" class="slideUp">

    <!-- HERO / BANNER DA LOJA DO VENDEDOR -->
    <div class="store-banner-header" style="background-image: url('<?php echo sanitize($store['banner_url'] ?: 'https://images.unsplash.com/photo-1541888946425-d0fbb186a5b3?w=1200&auto=format&fit=crop&q=80'); ?>');">
        <div class="store-banner-overlay">
            <div style="display:flex; align-items:center; gap:16px;">
                <img src="<?php echo sanitize($store['logo_url'] ?: 'https://images.unsplash.com/photo-1581094288338-2314dddb7eed?w=150&auto=format&fit=crop&q=60'); ?>" class="store-logo-img" alt="<?php echo sanitize($store['store_name']); ?>">
                <div>
                    <span style="font-size:10px; background:rgba(16, 185, 129, 0.2); border:1px solid rgba(16, 185, 129, 0.4); color:#10b981; padding:2px 10px; border-radius:50px; text-transform:uppercase; font-weight:800;">
                        <i data-lucide="check-circle2" style="width:12px; height:12px; display:inline;"></i> Loja Verificada B2B
                    </span>
                    <h2 style="margin:4px 0 0 0; font-family:'Outfit', sans-serif; font-weight:800; font-size:24px; color:white;">
                        <?php echo sanitize($store['store_name']); ?>
                    </h2>
                    <small style="color:rgba(255,255,255,0.8); font-size:13px; display:flex; align-items:center; gap:6px;">
                        <i data-lucide="map-pin" style="width:13px; height:13px;"></i> <?php echo sanitize($store['location']); ?> • WhatsApp: <?php echo sanitize($store['whatsapp']); ?>
                    </small>
                </div>
            </div>

            <div style="display:flex; gap:10px;">
                <a href="/marketplace" class="btn btn-secondary" style="font-size:12px; background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.2); color:white;">
                    <i data-lucide="shopping-bag"></i>
                    Ver no Marketplace
                </a>
            </div>
        </div>
    </div>

    <!-- CARDS DE ESTATÍSTICAS DA LOJA -->
    <div class="store-stats-grid">
        <div class="card" style="display:flex; align-items:center; gap:14px; padding:16px;">
            <div style="background:rgba(16,185,129,0.1); color:#10b981; padding:12px; border-radius:12px;">
                <i data-lucide="package" style="width:22px; height:22px;"></i>
            </div>
            <div>
                <small style="color:var(--text-muted); font-size:11px; text-transform:uppercase; font-weight:700;">Produtos no Catálogo</small>
                <h3 style="margin:2px 0 0 0; font-size:20px; font-weight:800;"><?php echo count($products); ?></h3>
            </div>
        </div>

        <div class="card" style="display:flex; align-items:center; gap:14px; padding:16px;">
            <div style="background:rgba(34,197,94,0.1); color:#22c55e; padding:12px; border-radius:12px;">
                <i data-lucide="message-square" style="width:22px; height:22px;"></i>
            </div>
            <div>
                <small style="color:var(--text-muted); font-size:11px; text-transform:uppercase; font-weight:700;">WhatsApp Ativo</small>
                <h3 style="margin:2px 0 0 0; font-size:16px; font-weight:700; color:var(--accent-success);"><?php echo sanitize($store['whatsapp']); ?></h3>
            </div>
        </div>

        <div class="card" style="display:flex; align-items:center; gap:14px; padding:16px;">
            <div style="background:rgba(251,191,36,0.1); color:#fbbf24; padding:12px; border-radius:12px;">
                <i data-lucide="shield-check" style="width:22px; height:22px;"></i>
            </div>
            <div>
                <small style="color:var(--text-muted); font-size:11px; text-transform:uppercase; font-weight:700;">Estado do Vendedor</small>
                <h3 style="margin:2px 0 0 0; font-size:15px; font-weight:700; color:#fbbf24;">Parceiro Aprovado</h3>
            </div>
        </div>
    </div>

    <!-- ABAS DE GESTÃO DA LOJA -->
    <div class="tab-btn-group">
        <button class="tab-btn active" id="tab-btn-products" onclick="switchVendorTab('products')">
            <i data-lucide="box"></i>
            Catálogo de Produtos (<?php echo count($products); ?>)
        </button>
        <button class="tab-btn" id="tab-btn-settings" onclick="switchVendorTab('settings')">
            <i data-lucide="sliders"></i>
            Perfil e Contactos da Loja
        </button>
    </div>

    <!-- SEÇÃO 1: CATÁLOGO DE PRODUTOS DA LOJA -->
    <div id="section-products-list">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="font-family:'Outfit', sans-serif; font-size:18px; font-weight:800; margin:0;">
                Produtos Anunciados na Loja
            </h3>
            <button onclick="openVendorProductModal()" class="btn btn-primary" style="background:#10b981; border-color:#10b981; font-weight:700; font-size:13px; display:flex; align-items:center; gap:6px;">
                <i data-lucide="plus-circle" style="width:16px; height:16px;"></i>
                Adicionar Novo Produto
            </button>
        </div>

        <div class="card" style="padding:0; overflow:hidden;">
            <?php if (empty($products)): ?>
                <div style="text-align:center; padding:50px 20px; color:var(--text-muted);">
                    <i data-lucide="package-open" style="width:48px; height:48px; margin-bottom:12px; stroke-width:1.5;"></i>
                    <p style="margin:0; font-size:15px; font-weight:600;">Nenhum produto cadastrado na sua loja de momento.</p>
                    <p style="margin:6px 0 0 0; font-size:13px;">Clique em "Adicionar Novo Produto" para colocar os seus materiais à venda.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="responsive-table" style="width:100%; border-collapse:collapse; text-align:left; font-size:13.5px;">
                        <thead>
                            <tr style="background:var(--bg-secondary); border-bottom:1px solid var(--border-color); color:var(--text-muted);">
                                <th style="padding:14px;">Produto</th>
                                <th style="padding:14px;">Categoria</th>
                                <th style="padding:14px;">Preço Unitário</th>
                                <th style="padding:14px;">Desconto VIP</th>
                                <th style="padding:14px;">Stock</th>
                                <th style="padding:14px; text-align:right;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                                <tr style="border-bottom:1px solid var(--border-color);">
                                    <td data-label="Produto" style="padding:14px; font-weight:700;">
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <img src="<?php echo sanitize($p['image']); ?>" style="width:42px; height:42px; border-radius:8px; object-fit:cover; border:1px solid var(--border-color);">
                                            <div>
                                                <div style="color:var(--text-primary); font-size:14px;"><?php echo sanitize($p['name']); ?></div>
                                                <small style="color:var(--text-muted); font-weight:normal;"><?php echo sanitize($p['unit']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Categoria" style="padding:14px;">
                                        <span class="badge" style="background:var(--bg-secondary); border:1px solid var(--border-color); color:var(--text-secondary); text-transform:uppercase; font-size:10px;">
                                            <?php echo sanitize($p['category']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Preço Unitário" style="padding:14px; font-weight:800; color:var(--text-primary);">
                                        <?php echo format_currency($p['price']); ?>
                                    </td>
                                    <td data-label="Desconto VIP" style="padding:14px;">
                                        <?php if (!empty($p['discount_pct']) && $p['discount_pct'] > 0): ?>
                                            <span style="color:var(--accent-success); font-weight:700; font-size:12px;">-<?php echo $p['discount_pct']; ?>%</span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted); font-size:12px;">Nenhum</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Stock" style="padding:14px;">
                                        <?php if (($p['stock_status'] ?? 'in_stock') === 'in_stock'): ?>
                                            <span class="badge" style="background:rgba(34,197,94,0.1); color:#22c55e; border:1px solid #22c55e; font-size:10.5px;">Disponível</span>
                                        <?php else: ?>
                                            <span class="badge" style="background:rgba(239,68,68,0.1); color:#ef4444; border:1px solid #ef4444; font-size:10.5px;">Esgotado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Ações" style="padding:14px; text-align:right;">
                                        <div style="display:flex; justify-content:flex-end; gap:8px;">
                                            <button class="btn btn-secondary" style="font-size:12px; padding:6px 10px;" onclick="openVendorProductModal(<?php echo sanitize(json_encode($p)); ?>)">
                                                <i data-lucide="edit-3" style="width:14px; height:14px;"></i> Editar
                                            </button>
                                            <button class="btn btn-primary" style="font-size:12px; padding:6px 10px; background:rgba(239,68,68,0.1); color:#ef4444; border-color:rgba(239,68,68,0.2);" onclick="deleteVendorStoreProduct(<?php echo $p['id']; ?>)">
                                                <i data-lucide="trash-2" style="width:14px; height:14px;"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SEÇÃO 2: PERFIL E CONFIGURAÇÕES DA LOJA -->
    <div id="section-settings" style="display:none;">
        <div class="card" style="padding:24px;">
            <h3 style="font-family:'Outfit', sans-serif; font-size:18px; font-weight:800; margin-bottom:20px; display:flex; align-items:center; gap:8px;">
                <i data-lucide="store" style="color:var(--accent-primary);"></i>
                Definições do Perfil da Empresa / Loja
            </h3>

            <form id="vendor-store-form" onsubmit="event.preventDefault(); submitVendorStore();" enctype="multipart/form-data">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                    <div class="form-group">
                        <label for="store-name" style="display:block; font-size:12px; margin-bottom:6px;">Nome Comercial da Empresa / Loja *</label>
                        <input type="text" id="store-name" class="form-control" value="<?php echo sanitize($store['store_name']); ?>" required style="width:100%;">
                    </div>

                    <div class="form-group">
                        <label for="store-whatsapp" style="display:block; font-size:12px; margin-bottom:6px;">WhatsApp Oficial para Receção de Pedidos *</label>
                        <input type="text" id="store-whatsapp" class="form-control" value="<?php echo sanitize($store['whatsapp']); ?>" required style="width:100%;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                    <div class="form-group">
                        <label for="store-location" style="display:block; font-size:12px; margin-bottom:6px;">Localização / Cidade e Província *</label>
                        <input type="text" id="store-location" class="form-control" value="<?php echo sanitize($store['location']); ?>" required style="width:100%;">
                    </div>

                    <div class="form-group">
                        <label for="store-category" style="display:block; font-size:12px; margin-bottom:6px;">Categoria Principal da Loja *</label>
                        <select id="store-category" class="form-control" style="width:100%; background:var(--bg-secondary); height:38px;">
                            <option value="construcao" <?php echo $store['category'] === 'construcao' ? 'selected' : ''; ?>>Construção Estrutural</option>
                            <option value="acabamentos" <?php echo $store['category'] === 'acabamentos' ? 'selected' : ''; ?>>Acabamentos & Argamassas</option>
                            <option value="pintura" <?php echo $store['category'] === 'pintura' ? 'selected' : ''; ?>>Pintura & Impermeabilização</option>
                            <option value="outros" <?php echo $store['category'] === 'outros' ? 'selected' : ''; ?>>Equipamentos & Ferragens</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                    <div class="form-group">
                        <label for="store-logo-file" style="display:block; font-size:12px; margin-bottom:6px;">Logótipo da Empresa (Upload)</label>
                        <input type="file" id="store-logo-file" class="form-control" accept="image/*" style="width:100%; font-size:11px;">
                        <input type="url" id="store-logo-url" class="form-control" placeholder="Ou cola a URL do Logótipo" value="<?php echo sanitize($store['logo_url']); ?>" style="width:100%; margin-top:6px;">
                    </div>

                    <div class="form-group">
                        <label for="store-banner-file" style="display:block; font-size:12px; margin-bottom:6px;">Banner / Foto de Capa (Upload)</label>
                        <input type="file" id="store-banner-file" class="form-control" accept="image/*" style="width:100%; font-size:11px;">
                        <input type="url" id="store-banner-url" class="form-control" placeholder="Ou cola a URL da Capa" value="<?php echo sanitize($store['banner_url']); ?>" style="width:100%; margin-top:6px;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label for="store-description" style="display:block; font-size:12px; margin-bottom:6px;">Descrição da Loja / Apresentação Comercial</label>
                    <textarea id="store-description" class="form-control" rows="3" style="width:100%; font-size:13px;"><?php echo sanitize($store['description']); ?></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end;">
                    <button type="submit" id="save-store-btn" class="btn btn-primary" style="background:#10b981; border-color:#10b981; font-weight:700; padding:10px 24px;">
                        Guardar Perfil da Loja
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- MODAL: ADICIONAR / EDITAR PRODUTO DA LOJA -->
<div class="modal" id="vendor-product-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:2300; align-items:center; justify-content:center; backdrop-filter:blur(6px); padding:20px;">
    <div class="card slideUp" style="width:100%; max-width:540px; padding:24px; position:relative; margin:auto;">
        <button onclick="closeVendorProductModal()" style="position:absolute; top:16px; right:16px; color:var(--text-secondary); background:none; border:0; cursor:pointer;"><i data-lucide="x"></i></button>

        <h3 id="vp-modal-title" style="margin-bottom:20px; font-family:'Outfit', sans-serif; font-weight:800; font-size:20px; display:flex; align-items:center; gap:8px;">
            <i data-lucide="box" style="color:var(--accent-primary);"></i>
            Adicionar Produto à Loja
        </h3>

        <form id="vendor-product-form" onsubmit="event.preventDefault(); submitVendorProduct();" enctype="multipart/form-data">
            <input type="hidden" id="vp-id">

            <div class="form-group" style="margin-bottom:14px;">
                <label for="vp-name" style="display:block; font-size:12px; margin-bottom:6px;">Nome do Produto / Material *</label>
                <input type="text" id="vp-name" class="form-control" required style="width:100%;">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group">
                    <label for="vp-price" style="display:block; font-size:12px; margin-bottom:6px;">Preço Unitário (Kz) *</label>
                    <input type="number" step="0.01" id="vp-price" class="form-control" required style="width:100%;">
                </div>

                <div class="form-group">
                    <label for="vp-unit" style="display:block; font-size:12px; margin-bottom:6px;">Unidade (ex: saco, un, m3) *</label>
                    <input type="text" id="vp-unit" class="form-control" value="un" required style="width:100%;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group">
                    <label for="vp-category" style="display:block; font-size:12px; margin-bottom:6px;">Categoria *</label>
                    <select id="vp-category" class="form-control" style="width:100%; background:var(--bg-secondary); height:38px;">
                        <option value="construcao">Construção Estrutural</option>
                        <option value="acabamentos">Acabamentos & Argamassas</option>
                        <option value="pintura">Pintura & Impermeabilização</option>
                        <option value="outros">Eletricidade & Ferramentas</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="vp-discount" style="display:block; font-size:12px; margin-bottom:6px;">Desconto VIP (%)</label>
                    <input type="number" id="vp-discount" class="form-control" value="0" style="width:100%;">
                </div>

                <div class="form-group">
                    <label for="vp-stock" style="display:block; font-size:12px; margin-bottom:6px;">Estado do Stock *</label>
                    <select id="vp-stock" class="form-control" style="width:100%; background:var(--bg-secondary); height:38px;">
                        <option value="in_stock">Em Stock</option>
                        <option value="out_of_stock">Esgotado</option>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group">
                    <label for="vp-image-file" style="display:block; font-size:12px; margin-bottom:6px;">Foto do Produto (Upload)</label>
                    <input type="file" id="vp-image-file" class="form-control" accept="image/*" style="width:100%; font-size:11px;">
                </div>

                <div class="form-group">
                    <label for="vp-image-url" style="display:block; font-size:12px; margin-bottom:6px;">Ou URL de Imagem</label>
                    <input type="url" id="vp-image-url" class="form-control" placeholder="https://..." style="width:100%;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label for="vp-desc" style="display:block; font-size:12px; margin-bottom:6px;">Descrição Detalhada do Produto</label>
                <textarea id="vp-desc" class="form-control" rows="2" style="width:100%; font-size:13px;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="closeVendorProductModal()">Cancelar</button>
                <button type="submit" id="vp-save-btn" class="btn btn-primary" style="background:#10b981; border-color:#10b981; font-weight:700;">Guardar Produto</button>
            </div>
        </form>
    </div>
</div>

<script nonce="<?php echo Security::getNonce(); ?>">
    function switchVendorTab(tab) {
        const btnProducts = document.getElementById('tab-btn-products');
        const btnSettings = document.getElementById('tab-btn-settings');
        const secProducts = document.getElementById('section-products-list');
        const secSettings = document.getElementById('section-settings');

        if (tab === 'products') {
            btnProducts.classList.add('active');
            btnSettings.classList.remove('active');
            secProducts.style.display = 'block';
            secSettings.style.display = 'none';
        } else {
            btnSettings.classList.add('active');
            btnProducts.classList.remove('active');
            secSettings.style.display = 'block';
            secProducts.style.display = 'none';
        }
    }

    function openVendorProductModal(item = null) {
        const modal = document.getElementById('vendor-product-modal');
        const title = document.getElementById('vp-modal-title');

        if (item) {
            title.innerHTML = '<i data-lucide="edit-3" style="color:var(--accent-primary);"></i> Editar Produto da Loja';
            document.getElementById('vp-id').value = item.id;
            document.getElementById('vp-name').value = item.name || '';
            document.getElementById('vp-price').value = item.price || 0;
            document.getElementById('vp-unit').value = item.unit || 'un';
            document.getElementById('vp-category').value = item.category || 'construcao';
            document.getElementById('vp-discount').value = item.discount_pct || 0;
            document.getElementById('vp-stock').value = item.stock_status || 'in_stock';
            document.getElementById('vp-image-url').value = item.image || '';
            document.getElementById('vp-desc').value = item.desc || '';
        } else {
            title.innerHTML = '<i data-lucide="plus-circle" style="color:var(--accent-success);"></i> Adicionar Produto à Loja';
            document.getElementById('vp-id').value = '';
            document.getElementById('vp-name').value = '';
            document.getElementById('vp-price').value = '';
            document.getElementById('vp-unit').value = 'un';
            document.getElementById('vp-category').value = 'construcao';
            document.getElementById('vp-discount').value = '0';
            document.getElementById('vp-stock').value = 'in_stock';
            document.getElementById('vp-image-url').value = '';
            document.getElementById('vp-desc').value = '';
        }

        modal.style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeVendorProductModal() {
        document.getElementById('vendor-product-modal').style.display = 'none';
    }

    async function submitVendorProduct() {
        const btn = document.getElementById('vp-save-btn');
        const id = document.getElementById('vp-id').value;
        const name = document.getElementById('vp-name').value.trim();
        const price = document.getElementById('vp-price').value;
        const unit = document.getElementById('vp-unit').value.trim();
        const category = document.getElementById('vp-category').value;
        const discountPct = document.getElementById('vp-discount').value;
        const stockStatus = document.getElementById('vp-stock').value;
        const imageUrl = document.getElementById('vp-image-url').value.trim();
        const desc = document.getElementById('vp-desc').value.trim();
        const fileInput = document.getElementById('vp-image-file');

        if (!name || !price) {
            App.showToast('Preencha os campos obrigatórios.', 'warning');
            return;
        }

        App.setLoading(btn, true);

        const formData = new FormData();
        formData.append('id', id);
        formData.append('name', name);
        formData.append('price', price);
        formData.append('unit', unit);
        formData.append('category', category);
        formData.append('discount_pct', discountPct);
        formData.append('stock_status', stockStatus);
        formData.append('image_url', imageUrl);
        formData.append('desc', desc);

        if (fileInput.files.length > 0) {
            formData.append('image', fileInput.files[0]);
        }

        try {
            const res = await App.post('/api/vendor/product/save', formData);
            App.showToast(res.message || 'Produto guardado com sucesso!', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } catch (e) {
            App.showToast(e.message || 'Erro ao guardar produto.', 'danger');
            App.setLoading(btn, false);
        }
    }

    async function deleteVendorStoreProduct(id) {
        if (!confirm('Tem a certeza que deseja eliminar este produto da sua loja?')) {
            return;
        }
        try {
            await App.post('/api/vendor/product/delete', { id });
            App.showToast('Produto eliminado da loja!', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } catch (e) {
            App.showToast(e.message || 'Erro ao eliminar produto.', 'danger');
        }
    }

    async function submitVendorStore() {
        const btn = document.getElementById('save-store-btn');
        const storeName = document.getElementById('store-name').value.trim();
        const whatsapp = document.getElementById('store-whatsapp').value.trim();
        const location = document.getElementById('store-location').value.trim();
        const category = document.getElementById('store-category').value;
        const description = document.getElementById('store-description').value.trim();
        const logoUrl = document.getElementById('store-logo-url').value.trim();
        const bannerUrl = document.getElementById('store-banner-url').value.trim();
        const logoFile = document.getElementById('store-logo-file');
        const bannerFile = document.getElementById('store-banner-file');

        if (!storeName || !whatsapp) {
            App.showToast('O nome comercial e o número de WhatsApp são obrigatórios.', 'warning');
            return;
        }

        App.setLoading(btn, true);

        const formData = new FormData();
        formData.append('store_name', storeName);
        formData.append('whatsapp', whatsapp);
        formData.append('location', location);
        formData.append('category', category);
        formData.append('description', description);
        formData.append('logo_url', logoUrl);
        formData.append('banner_url', bannerUrl);

        if (logoFile.files.length > 0) formData.append('logo', logoFile.files[0]);
        if (bannerFile.files.length > 0) formData.append('banner', bannerFile.files[0]);

        try {
            const res = await App.post('/api/vendor/store/save', formData);
            App.showToast(res.message || 'Perfil da loja atualizado com sucesso!', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } catch (e) {
            App.showToast(e.message || 'Erro ao guardar dados da loja.', 'danger');
            App.setLoading(btn, false);
        }
    }
</script>

<?php
require_once __DIR__ . '/../../templates/footer.php';
?>
