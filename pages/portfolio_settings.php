<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/middleware.php';

// Rota estritamente autenticada
middleware_require_auth();

$user = current_user();
$title = 'Configuração do Portfólio Público — Constrói Já';
require_once __DIR__ . '/../templates/header.php';

// Decode JSON do portfólio
$portfolio = [];
if (!empty($user['portfolio_data'])) {
    $portfolio = json_decode($user['portfolio_data'], true) ?? [];
}

$pTitle      = $portfolio['title'] ?? '';
$pDesc       = $portfolio['description'] ?? '';
$pExp        = $portfolio['experience'] ?? '';
$pSkillsList = $portfolio['skills'] ?? [];
$pSkillsStr  = implode(', ', $pSkillsList);
?>

<div style="max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px;">
    
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h2>Portfólio Público de Construção</h2>
            <p style="color:var(--text-secondary); font-size:14px;">Personalize a sua montra profissional para atrair potenciais clientes ou parceiros em Angola.</p>
        </div>
        <a href="/portfolio/<?php echo sanitize($user['username'] ?? ''); ?>" target="_blank" class="btn btn-secondary" style="font-size:13px; padding: 8px 16px;">
            <i data-lucide="external-link" style="width:16px; height:16px;"></i>
            Ver Portfólio
        </a>
    </div>

    <div class="card">
        <form id="portfolio-settings-form" onsubmit="event.preventDefault(); savePortfolioSettings();">
            
            <!-- Secção: Dados de Apresentação -->
            <div style="margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 20px;">
                <h4 style="margin-bottom: 12px; color: var(--accent-primary); display:flex; align-items:center; gap:8px;">
                    <i data-lucide="briefcase"></i>
                    Apresentação Geral
                </h4>
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="portfolio_title" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Título Profissional</label>
                    <input type="text" id="portfolio_title" class="form-control" placeholder="Ex: Engenheiro Civil Sénior / Empreiteiro Geral" value="<?php echo sanitize($pTitle); ?>" style="width:100%;">
                    <small style="color:var(--text-muted);">Um título curto que resume a sua especialidade civil.</small>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="portfolio_description" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Apresentação Curta (Sobre Mim)</label>
                    <textarea id="portfolio_description" class="form-control" placeholder="Descreva sucintamente a sua abordagem a obras, valores e percurso profissional..." style="min-height:120px; line-height:1.6;"><?php echo sanitize($pDesc); ?></textarea>
                </div>
            </div>

            <!-- Secção: Experiência e Especialidades -->
            <div style="margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 20px;">
                <h4 style="margin-bottom: 12px; color: var(--accent-secondary); display:flex; align-items:center; gap:8px;">
                    <i data-lucide="hammer"></i>
                    Experiência & Competências
                </h4>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="portfolio_experience" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Anos de Experiência / Destaques</label>
                    <input type="text" id="portfolio_experience" class="form-control" placeholder="Ex: 12 anos em Gestão de Obras Industriais e Residenciais" value="<?php echo sanitize($pExp); ?>" style="width:100%;">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="portfolio_skills" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Competências Técnicas (Competências civis separadas por vírgula)</label>
                    <input type="text" id="portfolio_skills" class="form-control" placeholder="Ex: Betão Armado, Orçamentação, AutoCAD, Fiscalização, Alvenaria" value="<?php echo sanitize($pSkillsStr); ?>" style="width:100%;">
                    <small style="color:var(--text-muted);">Estes termos vão aparecer como etiquetas (tags) premium no seu perfil público.</small>
                </div>
            </div>

            <!-- Botões -->
            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button type="submit" id="save-portfolio-btn" class="btn btn-primary" style="padding: 10px 24px;">
                    Guardar Configurações
                    <i data-lucide="save" style="width:16px; height:16px;"></i>
                </button>
            </div>
            
        </form>
    </div>

</div>

<script nonce="<?php echo Security::getNonce(); ?>">
async function savePortfolioSettings() {
    const btn = document.getElementById('save-portfolio-btn');
    
    // Obter campos existentes do profile do user para não sobreescrever com vazio
    const name = "<?php echo sanitize($user['name']); ?>";
    const username = "<?php echo sanitize($user['username']); ?>";
    const bio = "<?php echo sanitize($user['bio'] ?? ''); ?>";
    const location = "<?php echo sanitize($user['location'] ?? ''); ?>";
    const whatsapp = "<?php echo sanitize($user['whatsapp'] ?? ''); ?>";
    const website = "<?php echo sanitize($user['website'] ?? ''); ?>";
    const language = "<?php echo sanitize($user['language'] ?? 'pt'); ?>";
    const currency = "<?php echo sanitize($user['currency'] ?? 'AOA'); ?>";
    
    // Obter campos específicos do portfólio
    const portfolio_title = document.getElementById('portfolio_title').value.trim();
    const portfolio_description = document.getElementById('portfolio_description').value.trim();
    const portfolio_experience = document.getElementById('portfolio_experience').value.trim();
    const portfolio_skills = document.getElementById('portfolio_skills').value.trim();

    App.setLoading(btn, true);

    try {
        await App.post('/api/profile/update', {
            name,
            username,
            bio,
            location,
            whatsapp,
            website,
            language,
            currency,
            portfolio_title,
            portfolio_description,
            portfolio_experience,
            portfolio_skills
        });
        
        App.showToast('Configurações do portfólio salvas com sucesso!', 'success');
    } catch (e) {
        App.showToast(e.message || 'Erro ao guardar configurações.', 'danger');
    } finally {
        App.setLoading(btn, false);
    }
}
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
