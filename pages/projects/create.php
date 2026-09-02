<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Rota estritamente autenticada
middleware_require_auth();

$user = current_user();
$title = 'Iniciar Nova Obra — Constrói Já';
require_once __DIR__ . '/../../templates/header.php';
?>

<div style="max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px;">
    
    <!-- Retorno / Navegação -->
    <div>
        <a href="/projects" style="display:inline-flex; align-items:center; gap:6px; font-size:14px; color:var(--text-secondary);">
            <i data-lucide="arrow-left" style="width:16px; height:16px;"></i>
            Voltar às Minhas Obras
        </a>
    </div>

    <div>
        <h2>Iniciar Novo Projeto de Obra</h2>
        <p style="color:var(--text-secondary); font-size:14px;">Preencha os dados abaixo para criar um painel financeiro e timeline física para a sua construção civil.</p>
    </div>

    <div class="card">
        <form id="create-project-form" onsubmit="event.preventDefault(); submitCreateProject();">
            
            <!-- upload da imagem de capa -->
            <div class="form-group" style="margin-bottom: 24px;">
                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:var(--text-primary);">Imagem de Capa (Opcional)</label>
                <div class="upload-zone" id="project-upload-zone" data-preview-id="project-upload-preview" style="padding: 30px 20px; text-align:center; border: 1.5px dashed var(--border-color); border-radius: var(--radius-md); cursor:pointer; background: rgba(255,255,255,0.01);">
                    <input type="file" id="project-cover" name="cover" accept="image/*" style="display:none;">
                    <i data-lucide="image" style="width:36px; height:36px; color:var(--text-muted); margin-bottom:10px;"></i>
                    <p style="font-size:13px; color:var(--text-secondary);">Arraste ou clique para carregar foto da obra (JPG, PNG, WEBP — Máx 10MB)</p>
                    <div id="project-upload-preview" style="margin-top:12px;"></div>
                </div>
            </div>

            <!-- Dados Básicos -->
            <div style="display:grid; grid-template-columns: 1fr; gap:16px; margin-bottom:20px;">
                <div class="form-group">
                    <label for="project-title" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Nome / Título da Obra *</label>
                    <input type="text" id="project-title" class="form-control" placeholder="Ex: Moradia T4 no Talatona - Lote B" required style="width:100%;">
                </div>
                
                <div class="form-group">
                    <label for="project-description" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Descrição Sumária</label>
                    <textarea id="project-description" class="form-control" placeholder="Descreva brevemente os objetivos da obra, área total ou especificidades..." style="min-height:90px;"></textarea>
                </div>
            </div>

            <!-- Localização -->
            <div style="display:grid; grid-template-columns: 1fr; gap:20px; margin-bottom:20px;">
                <div class="form-group">
                    <label for="project-location" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Localização (Província / Município) *</label>
                    <input type="text" id="project-location" class="form-control" placeholder="Ex: Luanda, Talatona" required style="width:100%;">
                </div>
            </div>

            <!-- Prazos -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                <div class="form-group">
                    <label for="project-start-date" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Data de Início Estimada</label>
                    <input type="date" id="project-start-date" class="form-control" style="width:100%;">
                </div>

                <div class="form-group">
                    <label for="project-end-date" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Data de Conclusão Estimada</label>
                    <input type="date" id="project-end-date" class="form-control" style="width:100%;">
                </div>
            </div>

            <!-- Fases Físicas Customizáveis -->
            <div class="form-group" style="margin-bottom: 24px;">
                <label for="project-phases" style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">Fases Físicas de Construção (separadas por vírgula)</label>
                <input type="text" id="project-phases" class="form-control" placeholder="Ex: Terraplenagem, Fundação, Estrutura, Alvenaria, Acabamentos" value="Fundação, Estrutura, Alvenaria, Cobertura, Acabamentos" style="width:100%;">
                <small style="color:var(--text-muted);">Defina o fluxo da obra. Cada despesa introduzida será associada a uma destas fases.</small>
            </div>

            <!-- Visibilidade -->
            <div class="form-group" style="margin-bottom: 24px; display:flex; align-items:center; gap:10px;">
                <input type="checkbox" id="project-is-public" style="width: 18px; height: 18px; cursor:pointer;">
                <label for="project-is-public" style="font-size:13px; font-weight:600; cursor:pointer; color:var(--text-primary);">
                    Tornar este projeto público no meu feed social e portfólio
                </label>
            </div>

            <!-- Botões de Submissão -->
            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button type="submit" id="create-project-submit" class="btn btn-primary" style="padding: 12px 30px;">
                    Criar Obra
                    <i data-lucide="arrow-right" style="width:16px; height:16px;"></i>
                </button>
            </div>

        </form>
    </div>

</div>

<script nonce="<?php echo Security::getNonce(); ?>">
async function submitCreateProject() {
    const btn = document.getElementById('create-project-submit');
    const title = document.getElementById('project-title').value.trim();
    const description = document.getElementById('project-description').value.trim();
    const budget = 0;
    const location = document.getElementById('project-location').value.trim();
    const start_date = document.getElementById('project-start-date').value;
    const end_date = document.getElementById('project-end-date').value;
    const phases = document.getElementById('project-phases').value.trim();
    const is_public = document.getElementById('project-is-public').checked ? 1 : 0;
    const coverInput = document.getElementById('project-cover');

    if (!title || !location) {
        App.showToast('Por favor, preencha todos os campos obrigatórios (*).', 'warning');
        return;
    }

    App.setLoading(btn, true);

    const formData = new FormData();
    formData.append('title', title);
    formData.append('description', description);
    formData.append('budget', budget);
    formData.append('location', location);
    formData.append('start_date', start_date);
    formData.append('end_date', end_date);
    formData.append('phases', phases);
    formData.append('is_public', is_public);
    
    if (coverInput.files.length > 0) {
        formData.append('cover', coverInput.files[0]);
    }

    try {
        const response = await App.upload('/api/projects/create', formData);
        App.showToast('Obra criada com sucesso!', 'success');
        
        // Redirecionar para os detalhes da obra criada
        setTimeout(() => {
            window.location.href = '/projects/detail?id=' + response.data.project_id;
        }, 1000);
    } catch (e) {
        App.showToast(e.message || 'Erro ao criar obra.', 'danger');
        App.setLoading(btn, false);
    }
}
</script>

<?php
require_once __DIR__ . '/../../templates/footer.php';
?>
