<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Exige permissões de administrador
middleware_require_admin();

$user = current_user();
$db = db();

try {
    // Garantir que a tabela partners existe (Auto-migração transparente)
    try {
        $db->query("SELECT 1 FROM partners LIMIT 1");
    } catch (PDOException $ex) {
        $db->execute("
            CREATE TABLE IF NOT EXISTS partners (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                logo VARCHAR(255) NOT NULL,
                category VARCHAR(50) NOT NULL,
                `desc` TEXT NOT NULL,
                discount VARCHAR(100) NOT NULL,
                coupon VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        $defaultPartners = [
            [
                'name' => 'Sika Angola',
                'logo' => 'https://images.unsplash.com/photo-1581094288338-2314dddb7eed?w=150&auto=format&fit=crop&q=60',
                'category' => 'acabamentos',
                'desc' => 'Líder em impermeabilização, adjuvantes de betão, colagens elásticas e selagens no mercado angolano.',
                'discount' => '15% de Desconto',
                'coupon' => 'SIKAVIP15'
            ],
            [
                'name' => 'Cimento Secil Lobito',
                'logo' => 'https://images.unsplash.com/photo-1590069261209-f8e9b8642343?w=150&auto=format&fit=crop&q=60',
                'category' => 'construcao',
                'desc' => 'Cimento de altíssima qualidade produzido localmente. Ideal para betão estrutural, rebocos e alvenaria.',
                'discount' => '10% de Desconto',
                'coupon' => 'SECILVIP10'
            ],
            [
                'name' => 'Tintas CIN Angola',
                'logo' => 'https://images.unsplash.com/photo-1562259949-e8e7689d7828?w=150&auto=format&fit=crop&q=60',
                'category' => 'pintura',
                'desc' => 'Toda a gama de tintas decorativas e industriais premium com catálogo completo de cores para o seu projeto.',
                'discount' => '20% de Desconto',
                'coupon' => 'CINVIP20'
            ]
        ];

        foreach ($defaultPartners as $p) {
            $db->execute(
                "INSERT INTO partners (name, logo, category, `desc`, discount, coupon) VALUES (?, ?, ?, ?, ?, ?)",
                [$p['name'], $p['logo'], $p['category'], $p['desc'], $p['discount'], $p['coupon']]
            );
        }
    }

    // Obter todos os parceiros ordenados pelo ID mais recente
    $partnersList = $db->fetchAll("SELECT * FROM partners ORDER BY id DESC");

} catch (PDOException $e) {
    die("Erro ao carregar parceiros: " . $e->getMessage());
}

$title = 'Gestão de Parceiros B2B — Constrói Já';
require_once __DIR__ . '/../../templates/header.php';
?>
<style nonce="<?php echo Security::getNonce(); ?>">
    /* Grelha celular Excel em ecrã inteiro */
    .excel-table th {
        background: var(--bg-secondary) !important;
        border: 1px solid var(--border-color) !important;
        color: var(--text-muted) !important;
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .excel-table td {
        border: 1px solid var(--border-color) !important;
        font-size: 13px;
        transition: background-color 0.2s;
    }
    
    .excel-table tr:hover td {
        background: var(--bg-primary) !important;
    }

    @media (max-width: 992px) {
        .form-grid-3 {
            grid-template-columns: 1fr 1fr !important;
        }
    }

    @media (max-width: 768px) {
        /* Colapso Responsivo da Tabela para Cartões */
        .excel-table, 
        .excel-table thead, 
        .excel-table tbody, 
        .excel-table th, 
        .excel-table td, 
        .excel-table tr {
            display: block !important;
        }
        
        .excel-table thead {
            display: none !important; /* Esconde o cabeçalho no mobile */
        }
        
        .excel-table tr {
            margin-bottom: 20px;
            border: 1px solid var(--border-color) !important;
            border-radius: var(--radius-lg) !important;
            background: var(--bg-card) !important;
            padding: 16px !important;
            box-shadow: var(--shadow-md) !important;
            transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
        }
        
        .excel-table tr:hover {
            border-color: var(--accent-primary) !important;
            box-shadow: var(--shadow-lg), var(--shadow-glow) !important;
        }
        
        .excel-table td {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            border: 0 !important;
            border-bottom: 1px solid var(--border-color) !important;
            padding: 10px 0 !important;
            text-align: right !important;
            min-height: 44px;
        }
        
        .excel-table td:last-child {
            border-bottom: 0 !important;
            padding-bottom: 0 !important;
            margin-top: 10px;
            justify-content: flex-end !important;
        }
        
        .excel-table td::before {
            content: attr(data-label);
            font-weight: 700;
            color: var(--text-muted);
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            margin-right: 15px;
        }

        .form-grid-3 {
            grid-template-columns: 1fr !important;
            gap: 12px !important;
        }
        .form-grid-2 {
            grid-template-columns: 1fr !important;
            gap: 12px !important;
        }
    }
</style>

<div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px;" class="slideUp">

    <!-- Topo Admin -->
    <div class="admin-header-flex" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <h2 style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="shopping-bag" style="color:var(--accent-primary); width:28px; height:28px;"></i>
                Gestão de Parceiros B2B
            </h2>
            <p style="color:var(--text-secondary); font-size:14px;">Adicione, edite ou remova cupões de desconto e marcas de construção civis parceiras.</p>
        </div>
        
        <!-- Navegação interna Admin -->
        <div class="admin-subnav">
            <a href="/admin" class="btn btn-secondary" style="font-size:13px; padding: 8px 16px;">
                <i data-lucide="bar-chart-3" style="width:16px; height:16px;"></i>
                Geral
            </a>
            <a href="/admin/users" class="btn btn-secondary" style="font-size:13px; padding: 8px 16px;">
                <i data-lucide="users" style="width:16px; height:16px;"></i>
                Utilizadores
            </a>
            <a href="/admin/moderation" class="btn btn-secondary" style="font-size:13px; padding: 8px 16px;">
                <i data-lucide="message-square" style="width:16px; height:16px;"></i>
                Moderação
            </a>
            <a href="/admin/partners" class="btn btn-primary" style="font-size:13px; padding: 8px 16px;">
                <i data-lucide="shopping-bag" style="width:16px; height:16px;"></i>
                Parceiros B2B
            </a>
        </div>
    </div>

    <!-- Barra de Acção -->
    <div style="display:flex; justify-content:flex-end;">
        <button onclick="openCreatePartnerModal();" class="btn btn-primary" style="font-size:13px; padding: 10px 20px;">
            <i data-lucide="plus-circle" style="width:16px; height:16px;"></i>
            Adicionar Parceiro B2B
        </button>
    </div>

    <!-- Tabela de Parceiros -->
    <div class="card" style="display: flex; flex-direction: column; gap: 16px;">
        <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); display:flex; align-items:center; gap:8px; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
            <i data-lucide="award" style="color: var(--accent-primary); width: 18px; height: 18px;"></i>
            Fornecedores e Marcas Ativas (<?php echo count($partnersList); ?>)
        </h3>

        <div style="overflow-x: auto;">
            <table class="responsive-table excel-table" style="width: 100%; border-collapse: collapse; font-size: 14px; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                        <th style="padding: 12px; min-width: 180px;">Marca</th>
                        <th style="padding: 12px;">Categoria</th>
                        <th style="padding: 12px; min-width: 250px;">Descrição</th>
                        <th style="padding: 12px; text-align:center;">Desconto</th>
                        <th style="padding: 12px; text-align:center;">Cupão VIP</th>
                        <th style="padding: 12px; text-align:center;">WhatsApp</th>
                        <th style="padding: 12px; text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($partnersList)): ?>
                        <tr>
                            <td colspan="7" style="padding: 40px; text-align: center; color: var(--text-muted);">
                                Nenhuma marca parceira ativa encontrada.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($partnersList as $partner): ?>
                            <tr style="border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                                <td data-label="Marca" style="padding: 12px; font-weight: 600;">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <img src="<?php echo sanitize($partner['logo']); ?>" style="width:36px; height:36px; border-radius:8px; object-fit:cover; border:1px solid var(--border-color);" onerror="this.src='https://images.unsplash.com/photo-1581094288338-2314dddb7eed?w=80';">
                                        <div>
                                            <span style="color:var(--text-primary); font-weight:700; display:block;"><?php echo sanitize($partner['name']); ?></span>
                                            <small style="color:var(--text-muted); font-size:10px;">ID: #<?php echo $partner['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Categoria" style="padding: 12px;">
                                    <span class="badge" style="font-size:11px; padding: 2px 8px; background:var(--bg-secondary); color:var(--text-secondary);">
                                        <?php 
                                            if ($partner['category'] === 'construcao') echo 'Construção';
                                            elseif ($partner['category'] === 'acabamentos') echo 'Acabamentos';
                                            elseif ($partner['category'] === 'pintura') echo 'Pintura';
                                            else echo 'Outros / Ferragens';
                                        ?>
                                    </span>
                                </td>
                                <td data-label="Descrição" style="padding: 12px; color: var(--text-secondary); font-size:13px; line-height: 1.4;">
                                    <?php echo sanitize($partner['desc']); ?>
                                </td>
                                <td data-label="Desconto" style="padding: 12px; text-align:center; font-weight:700; color:var(--accent-success);">
                                    <?php echo sanitize($partner['discount']); ?>
                                </td>
                                <td data-label="Cupão VIP" style="padding: 12px; text-align:center;">
                                    <code style="font-family: monospace; font-size:13px; font-weight:700; color:#fbbf24; background:rgba(251,191,36,0.1); padding: 4px 8px; border-radius:4px; border:1px dashed rgba(251,191,36,0.2);">
                                        <?php echo sanitize($partner['coupon']); ?>
                                    </code>
                                </td>
                                <td data-label="WhatsApp" style="padding: 12px; text-align:center; color: #22c55e; font-weight: 600;">
                                    <?php echo sanitize($partner['whatsapp'] ?: 'Não definido'); ?>
                                </td>
                                <td data-label="Ações" style="padding: 12px; text-align: right;">
                                    <div style="display:flex; justify-content:flex-end; gap:8px;">
                                        <button onclick="openEditPartnerModal(<?php echo sanitize(json_encode($partner)); ?>);" class="btn btn-secondary" style="font-size:12px; padding:6px 10px;" title="Editar">
                                            <i data-lucide="edit-2" style="width:14px; height:14px; color:var(--accent-secondary);"></i>
                                        </button>
                                        <button onclick="deletePartner(<?php echo $partner['id']; ?>, '<?php echo sanitize(addslashes($partner['name'])); ?>');" class="btn btn-secondary" style="font-size:12px; padding:6px 10px; background:rgba(239,68,68,0.05); border-color:rgba(239,68,68,0.1);" title="Excluir">
                                            <i data-lucide="trash-2" style="width:14px; height:14px; color:var(--accent-danger);"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Criar Parceiro B2B -->
<div id="create-partner-modal" class="modal-overlay">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="plus-circle" style="color:var(--accent-primary);"></i>
                Adicionar Marca Parceira B2B
            </h3>
            <i class="modal-close" data-lucide="x" onclick="App.hideModal('create-partner-modal');"></i>
        </div>
        
        <form id="create-partner-form" onsubmit="event.preventDefault(); submitCreatePartner();">
            <div style="display:grid; grid-template-columns: 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="create-partner-name" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Nome do Fornecedor / Marca *</label>
                    <input type="text" id="create-partner-name" class="form-control" placeholder="Ex: Sika Angola" required style="width:100%;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;" class="form-grid-2">
                <div class="form-group">
                    <label for="create-partner-category" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Categoria Principal *</label>
                    <select id="create-partner-category" class="form-control" style="width:100%; background:var(--bg-secondary); height:38px;">
                        <option value="construcao">Construção Geral</option>
                        <option value="acabamentos">Acabamentos</option>
                        <option value="pintura">Pintura</option>
                        <option value="outros">Ferragens & Outros</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="create-partner-logo" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">URL da Foto / Logótipo *</label>
                    <input type="text" id="create-partner-logo" class="form-control" placeholder="Link HTTP ou caminho local" required style="width:100%;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px; margin-bottom:16px;" class="form-grid-3">
                <div class="form-group">
                    <label for="create-partner-discount" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Rótulo do Desconto *</label>
                    <input type="text" id="create-partner-discount" class="form-control" placeholder="Ex: 15% de Desconto" required style="width:100%;">
                </div>

                <div class="form-group">
                    <label for="create-partner-coupon" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Código de Cupão VIP *</label>
                    <input type="text" id="create-partner-coupon" class="form-control" placeholder="Ex: SIKAVIP15" required style="width:100%; font-family:monospace; text-transform:uppercase;">
                </div>

                <div class="form-group">
                    <label for="create-partner-whatsapp" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">WhatsApp (com DDI) *</label>
                    <input type="text" id="create-partner-whatsapp" class="form-control" placeholder="Ex: 244923000000" required style="width:100%;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="create-partner-desc" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Descrição Comercial e Serviços *</label>
                <textarea id="create-partner-desc" class="form-control" placeholder="Descreva sucintamente o que a empresa faz..." required style="width:100%; min-height:80px;"></textarea>
            </div>

            <div class="modal-footer-buttons" style="display:flex; justify-content:flex-end; gap:12px; border-top:1px solid var(--border-color); padding-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="App.hideModal('create-partner-modal');">Cancelar</button>
                <button type="submit" id="create-partner-submit" class="btn btn-primary" style="padding: 10px 24px;">
                    Registrar Parceiro
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Parceiro B2B -->
<div id="edit-partner-modal" class="modal-overlay">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="edit-3" style="color:var(--accent-secondary);"></i>
                Editar Parceiro B2B
            </h3>
            <i class="modal-close" data-lucide="x" onclick="App.hideModal('edit-partner-modal');"></i>
        </div>
        
        <form id="edit-partner-form" onsubmit="event.preventDefault(); submitEditPartner();">
            <input type="hidden" id="edit-partner-id">
            
            <div style="display:grid; grid-template-columns: 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group">
                    <label for="edit-partner-name" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Nome do Fornecedor / Marca *</label>
                    <input type="text" id="edit-partner-name" class="form-control" required style="width:100%;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;" class="form-grid-2">
                <div class="form-group">
                    <label for="edit-partner-category" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Categoria Principal *</label>
                    <select id="edit-partner-category" class="form-control" style="width:100%; background:var(--bg-secondary); height:38px;">
                        <option value="construcao">Construção Geral</option>
                        <option value="acabamentos">Acabamentos</option>
                        <option value="pintura">Pintura</option>
                        <option value="outros">Ferragens & Outros</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit-partner-logo" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">URL da Foto / Logótipo *</label>
                    <input type="text" id="edit-partner-logo" class="form-control" required style="width:100%;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px; margin-bottom:16px;" class="form-grid-3">
                <div class="form-group">
                    <label for="edit-partner-discount" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Rótulo do Desconto *</label>
                    <input type="text" id="edit-partner-discount" class="form-control" required style="width:100%;">
                </div>

                <div class="form-group">
                    <label for="edit-partner-coupon" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Código de Cupão VIP *</label>
                    <input type="text" id="edit-partner-coupon" class="form-control" required style="width:100%; font-family:monospace; text-transform:uppercase;">
                </div>

                <div class="form-group">
                    <label for="edit-partner-whatsapp" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">WhatsApp (com DDI) *</label>
                    <input type="text" id="edit-partner-whatsapp" class="form-control" required style="width:100%;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="edit-partner-desc" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Descrição Comercial e Serviços *</label>
                <textarea id="edit-partner-desc" class="form-control" required style="width:100%; min-height:80px;"></textarea>
            </div>

            <div class="modal-footer-buttons" style="display:flex; justify-content:flex-end; gap:12px; border-top:1px solid var(--border-color); padding-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="App.hideModal('edit-partner-modal');">Cancelar</button>
                <button type="submit" id="edit-partner-submit" class="btn btn-primary" style="padding: 10px 24px;">
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<script nonce="<?php echo Security::getNonce(); ?>">
function openCreatePartnerModal() {
    document.getElementById('create-partner-form').reset();
    App.showModal('create-partner-modal');
}

function openEditPartnerModal(partner) {
    document.getElementById('edit-partner-id').value = partner.id;
    document.getElementById('edit-partner-name').value = partner.name;
    document.getElementById('edit-partner-category').value = partner.category;
    document.getElementById('edit-partner-logo').value = partner.logo;
    document.getElementById('edit-partner-discount').value = partner.discount;
    document.getElementById('edit-partner-coupon').value = partner.coupon;
    document.getElementById('edit-partner-desc').value = partner.desc;
    document.getElementById('edit-partner-whatsapp').value = partner.whatsapp || '';
    
    App.showModal('edit-partner-modal');
}

async function submitCreatePartner() {
    const btn = document.getElementById('create-partner-submit');
    const name = document.getElementById('create-partner-name').value.trim();
    const category = document.getElementById('create-partner-category').value;
    const logo = document.getElementById('create-partner-logo').value.trim();
    const discount = document.getElementById('create-partner-discount').value.trim();
    const coupon = document.getElementById('create-partner-coupon').value.trim().toUpperCase();
    const desc = document.getElementById('create-partner-desc').value.trim();
    const whatsapp = document.getElementById('create-partner-whatsapp').value.trim();

    App.setLoading(btn, true);

    try {
        await App.post('/api/admin/partners/create', {
            name, category, logo, discount, coupon, desc, whatsapp
        });
        App.showToast('Parceiro B2B registrado com sucesso!', 'success');
        setTimeout(() => window.location.reload(), 1000);
    } catch (e) {
        App.showToast(e.message || 'Erro ao registrar parceiro.', 'danger');
        App.setLoading(btn, false);
    }
}

async function submitEditPartner() {
    const btn = document.getElementById('edit-partner-submit');
    const id = document.getElementById('edit-partner-id').value;
    const name = document.getElementById('edit-partner-name').value.trim();
    const category = document.getElementById('edit-partner-category').value;
    const logo = document.getElementById('edit-partner-logo').value.trim();
    const discount = document.getElementById('edit-partner-discount').value.trim();
    const coupon = document.getElementById('edit-partner-coupon').value.trim().toUpperCase();
    const desc = document.getElementById('edit-partner-desc').value.trim();
    const whatsapp = document.getElementById('edit-partner-whatsapp').value.trim();

    App.setLoading(btn, true);

    try {
        await App.post('/api/admin/partners/update', {
            id, name, category, logo, discount, coupon, desc, whatsapp
        });
        App.showToast('Parceiro B2B atualizado com sucesso!', 'success');
        setTimeout(() => window.location.reload(), 1000);
    } catch (e) {
        App.showToast(e.message || 'Erro ao atualizar parceiro.', 'danger');
        App.setLoading(btn, false);
    }
}

function deletePartner(id, name) {
    App.openConfirm(`Tem a certeza que deseja ELIMINAR definitivamente o parceiro "${name}" e invalidar o seu cupão VIP?`, async () => {
        try {
            await App.post('/api/admin/partners/delete', { id });
            App.showToast('Parceiro removido com sucesso!', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } catch (e) {
            App.showToast(e.message || 'Erro ao remover parceiro.', 'danger');
        }
    });
}
</script>

<?php
require_once __DIR__ . '/../../templates/footer.php';
?>
