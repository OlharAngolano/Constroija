<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Rota estritamente autenticada
middleware_require_auth();

$user = current_user();

// Decode JSON do portfólio para pré-carregar os campos
$portfolio = [];
if (!empty($user['portfolio_data'])) {
    $portfolio = json_decode($user['portfolio_data'], true) ?? [];
}

$pTitle      = $portfolio['title'] ?? '';
$pDesc       = $portfolio['description'] ?? '';
$pExp        = $portfolio['experience'] ?? '';
$pSkillsList = $portfolio['skills'] ?? [];
$pSkillsStr  = implode(', ', $pSkillsList);

$title = 'Definições da Conta — Constrói Já';
require_once __DIR__ . '/../../templates/header.php';
?>

<div style="max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px;">

    <div>
        <h2>Definições de Perfil e Conta</h2>
        <p style="color:var(--text-secondary); font-size:14px;">Gira os teus dados profissionais, imagem de exibição e preferências regionais.</p>
    </div>

    <div style="display: grid; grid-template-columns: 1fr; gap: 24px;">
        
        <!-- Bloco 1: Upload de Avatar (Destaque Premium) -->
        <div class="card" style="display: flex; align-items: center; gap: 24px; flex-wrap: wrap;">
            <div style="position: relative;">
                <img id="avatar-preview" src="<?php echo get_avatar_url($user['avatar_url'] ?? null, $user['name']); ?>" 
                     alt="<?php echo sanitize($user['name']); ?>" 
                     style="width: 110px; height: 110px; border-radius: var(--radius-md); object-fit: cover; border: 2px solid var(--border-color);">
                <div id="avatar-loading" style="position: absolute; inset: 0; background: rgba(10,15,30,0.85); display: none; align-items: center; justify-content: center; border-radius: var(--radius-md);">
                    <i data-lucide="loader-2" class="spin" style="color: var(--accent-primary); width: 24px; height: 24px;"></i>
                </div>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 8px; flex-grow: 1;">
                <h4 style="font-weight: 700;">Foto de Perfil</h4>
                <p style="color: var(--text-muted); font-size: 13px;">Formatos aceites: JPG, PNG, WEBP ou GIF. Tamanho máximo recomendado: 10MB.</p>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <label for="avatar-file-input" class="btn btn-secondary" style="font-size: 13px; cursor: pointer; padding: 8px 16px;">
                        <i data-lucide="upload" style="width: 16px; height: 16px;"></i>
                        Escolher Foto
                    </label>
                    <input type="file" id="avatar-file-input" accept="image/*" style="display: none;" data-jsaction="uploadAvatar">
                </div>
            </div>
        </div>

        <!-- Bloco 2: Formulário Geral de Dados -->
        <div class="card">
            <form id="settings-form" data-jsaction="saveSettings" data-jsprevent="1" style="display: flex; flex-direction: column; gap: 20px;">
                
                <h4 style="color: var(--accent-primary); display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                    <i data-lucide="user"></i>
                    Informações Gerais
                </h4>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label for="name" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Nome Completo</label>
                        <input type="text" id="name" class="form-control" placeholder="Seu nome" value="<?php echo sanitize($user['name']); ?>" required style="width: 100%;">
                    </div>
                    <div class="form-group">
                        <label for="username" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Username único</label>
                        <input type="text" id="username" class="form-control" placeholder="ex: joao.silva" value="<?php echo sanitize($user['username'] ?? ''); ?>" required style="width: 100%;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="bio" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Mini Biografia</label>
                    <textarea id="bio" class="form-control" placeholder="Uma breve descrição sobre si ou a sua empresa..." style="min-height:80px; width: 100%;"><?php echo sanitize($user['bio'] ?? ''); ?></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label for="location" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Localização (Província, Município)</label>
                        <input type="text" id="location" class="form-control" placeholder="Ex: Luanda, Talatona" value="<?php echo sanitize($user['location'] ?? ''); ?>" style="width: 100%;">
                    </div>
                    <div class="form-group">
                        <label for="whatsapp" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Contacto WhatsApp</label>
                        <input type="text" id="whatsapp" class="form-control" placeholder="Ex: +244923000000" value="<?php echo sanitize($user['whatsapp'] ?? ''); ?>" style="width: 100%;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="website" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Website / Portfólio Externo</label>
                    <input type="url" id="website" class="form-control" placeholder="Ex: https://minhaempresa.com" value="<?php echo sanitize($user['website'] ?? ''); ?>" style="width: 100%;">
                </div>

                <h4 style="color: var(--accent-secondary); display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-top: 16px;">
                    <i data-lucide="globe"></i>
                    Preferências do Sistema
                </h4>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label for="currency" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Moeda Principal de Trabalho</label>
                        <select id="currency" class="form-control" style="width: 100%;">
                            <option value="AOA" <?php echo ($user['currency'] ?? 'AOA') === 'AOA' ? 'selected' : ''; ?>>Kwanza (AOA)</option>
                            <option value="USD" <?php echo ($user['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>Dólar Americano (USD)</option>
                            <option value="EUR" <?php echo ($user['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="language" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Idioma de Preferência</label>
                        <select id="language" class="form-control" style="width: 100%;">
                            <option value="pt" <?php echo ($user['language'] ?? 'pt') === 'pt' ? 'selected' : ''; ?>>Português (Angola)</option>
                        </select>
                    </div>
                </div>

                <!-- Botão de submissão -->
                <div style="display: flex; justify-content: flex-end; margin-top: 12px;">
                    <button type="submit" id="save-btn" class="btn btn-primary" style="padding: 10px 24px;">
                        Salvar Alterações
                        <i data-lucide="save" style="width: 16px; height: 16px;"></i>
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<script nonce="<?php echo Security::getNonce(); ?>">
async function saveSettings() {
    const btn = document.getElementById('save-btn');
    
    const name = document.getElementById('name').value.trim();
    const username = document.getElementById('username').value.trim();
    const bio = document.getElementById('bio').value.trim();
    const location = document.getElementById('location').value.trim();
    const whatsapp = document.getElementById('whatsapp').value.trim();
    const website = document.getElementById('website').value.trim();
    const currency = document.getElementById('currency').value;
    const language = document.getElementById('language').value;

    // Manter dados anteriores do portfolio intactos se existirem
    const portfolio_title = "<?php echo sanitize($pTitle); ?>";
    const portfolio_description = `<?php echo addslashes($pDesc); ?>`;
    const portfolio_experience = "<?php echo sanitize($pExp); ?>";
    const portfolio_skills = "<?php echo sanitize($pSkillsStr); ?>";

    App.setLoading(btn, true);

    try {
        const res = await App.post('/api/profile/update', {
            name,
            username,
            bio,
            location,
            whatsapp,
            website,
            currency,
            language,
            portfolio_title,
            portfolio_description,
            portfolio_experience,
            portfolio_skills
        });
        
        if (res.success) {
            App.showToast('Definições atualizadas com sucesso!', 'success');
            // Atualizar o nome na sidebar se alterou
            setTimeout(() => location.reload(), 1000);
        } else {
            App.showToast(res.error || 'Erro ao atualizar dados.', 'danger');
        }
    } catch (e) {
        App.showToast(e.message || 'Erro ao conectar ao servidor.', 'danger');
    } finally {
        App.setLoading(btn, false);
    }
}

async function uploadAvatar() {
    const fileInput = document.getElementById('avatar-file-input');
    const preview = document.getElementById('avatar-preview');
    const loader = document.getElementById('avatar-loading');
    
    if (!fileInput || fileInput.files.length === 0) return;
    
    const file = fileInput.files[0];
    const formData = new FormData();
    formData.append('avatar', file);
    
    // Injetar token CSRF para upload multipart
    formData.append('_token', window.APP.csrfToken);

    if (loader) loader.style.display = 'flex';

    try {
        const response = await fetch('/api/profile/avatar', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const res = await response.json();
        
        if (res.success) {
            preview.src = res.data.avatar_url;
            App.showToast('Foto de perfil atualizada!', 'success');
            // Se existirem avatares no cabeçalho/mobile header, atualiza-os também
            document.querySelectorAll('img.avatar').forEach(img => {
                img.src = res.data.avatar_url;
            });
        } else {
            App.showToast(res.error || 'Erro ao processar imagem.', 'danger');
        }
    } catch (e) {
        App.showToast('Falha na comunicação de upload.', 'danger');
    } finally {
        if (loader) loader.style.display = 'none';
        fileInput.value = ''; // Limpar input
    }
}
</script>

<?php
require_once __DIR__ . '/../../templates/footer.php';
?>
