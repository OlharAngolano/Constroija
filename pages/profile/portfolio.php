<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

$username = $_GET['username'] ?? '';
$db = db();

try {
    // Obter dados do perfil
    $profile = $db->fetch("SELECT * FROM profiles WHERE username = ?", [$username]);
    
    if (!$profile) {
        http_response_code(404);
        require_once __DIR__ . '/../404.php';
        exit;
    }

    // Projetos públicos pertencentes a este utilizador
    $publicProjects = $db->fetchAll(
        "SELECT * FROM projects WHERE user_id = ? AND is_public = 1 ORDER BY created_at DESC",
        [$profile['id']]
    );

    // Decode do portfólio
    $portfolio = [];
    if (!empty($profile['portfolio_data'])) {
        $portfolio = json_decode($profile['portfolio_data'], true) ?? [];
    }

    $pTitle = $portfolio['title'] ?? 'Construtor / Especialista Civil';
    $pDesc  = $portfolio['description'] ?? 'Sem descrição de portfólio configurada.';
    $pExp   = $portfolio['experience'] ?? '';
    $pSkills = $portfolio['skills'] ?? [];

} catch (PDOException $e) {
    die("Erro ao carregar o portfólio público: " . $e->getMessage());
}

$title = "Portfólio de " . sanitize($profile['name']) . " — Constrói Já";
require_once __DIR__ . '/../../templates/header.php';
?>

<div style="max-width: 1100px; margin: 0 auto; display: flex; flex-direction: column; gap: 32px;">

    <!-- Capa / Jumbotron Premium do Portfólio -->
    <div style="position: relative; background: linear-gradient(135deg, rgba(249,115,22,0.12) 0%, rgba(59,130,246,0.12) 100%); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 40px; display: flex; flex-direction: column; gap: 20px; text-align: center; align-items: center; backdrop-filter: blur(10px); box-shadow: var(--shadow-md);">
        
        <img src="<?php echo get_avatar_url($profile['avatar_url'] ?? null, $profile['name']); ?>" 
             alt="<?php echo sanitize($profile['name']); ?>" 
             style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent-primary); box-shadow: var(--shadow-md);">
        
        <div>
            <h1 style="font-weight: 800; font-size: 28px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <?php echo sanitize($profile['name']); ?>
                <?php if ((int)($profile['is_verified'] ?? 0) === 1): ?>
                    <i data-lucide="verified" style="color: var(--accent-secondary); width: 22px; height: 22px; fill: rgba(59,130,246,0.2);"></i>
                <?php endif; ?>
            </h1>
            <p style="color: var(--accent-primary); font-weight: 600; font-size: 16px; margin-top: 4px;"><?php echo sanitize($pTitle); ?></p>
            <?php if (!empty($pExp)): ?>
                <span style="font-size: 13px; color: var(--text-muted); font-weight: 500; display: block; margin-top: 2px;">
                    <i data-lucide="award" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 2px;"></i>
                    <?php echo sanitize($pExp); ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if (!empty($pSkills)): ?>
            <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; max-width: 700px;">
                <?php foreach ($pSkills as $skill): ?>
                    <span class="badge" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 4px 12px; border-radius: 20px; font-size: 12px;">
                        <?php echo sanitize($skill); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Contacto Rápido WhatsApp / Profile -->
        <div style="display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; margin-top: 10px;">
            <?php if (!empty($profile['whatsapp'])): ?>
                <?php 
                $waText = urlencode("Olá " . $profile['name'] . "! Vi o seu portfólio profissional no Constrói Já e gostaria de obter informações sobre os seus serviços de construção.");
                $waLink = "https://wa.me/" . preg_replace('/\D/', '', $profile['whatsapp']) . "?text=" . $waText;
                ?>
                <a href="<?php echo $waLink; ?>" target="_blank" class="btn btn-primary" style="background: #22c55e; border-color: #22c55e; padding: 10px 20px; font-size: 13px;">
                    <i data-lucide="phone" style="width:16px; height:16px;"></i>
                    Solicitar Orçamento
                </a>
            <?php endif; ?>
            <a href="/profile/<?php echo sanitize($profile['username'] ?? ''); ?>" class="btn btn-secondary" style="padding: 10px 20px; font-size: 13px;">
                <i data-lucide="user" style="width:16px; height:16px;"></i>
                Ver Perfil Completo
            </a>
        </div>
    </div>

    <!-- Secção: Sobre o Profissional / Empresa -->
    <div class="card" style="display: flex; flex-direction: column; gap: 16px;">
        <h3 style="display: flex; align-items: center; gap: 8px; color: var(--accent-secondary); font-size: 18px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
            <i data-lucide="info"></i>
            Apresentação Profissional
        </h3>
        <p style="color: var(--text-primary); font-size: 15px; line-height: 1.8; white-space: pre-line;">
            <?php echo sanitize($pDesc); ?>
        </p>
    </div>

    <!-- Secção: Galeria de Projetos de Construção Realizados / Ativos -->
    <div style="display: flex; flex-direction: column; gap: 16px;">
        <h2 style="display: flex; align-items: center; gap: 8px; font-size: 20px; font-weight: 800; margin-left: 4px;">
            <i data-lucide="layers" style="color: var(--accent-primary);"></i>
            Portfólio de Obras & Empreendimentos (<?php echo count($publicProjects); ?>)
        </h2>

        <?php if (empty($publicProjects)): ?>
            <div class="card" style="text-align: center; padding: 50px 20px; color: var(--text-muted);">
                <i data-lucide="construction" style="width: 48px; height: 48px; stroke-width: 1; margin: 0 auto 12px; color: var(--text-muted);"></i>
                <p>Nenhuma obra pública foi destacada no portfólio deste profissional de momento.</p>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px;">
                <?php foreach ($publicProjects as $project): ?>
                    <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; height: 100%; transition: transform 0.2s; border-color: rgba(255,255,255,0.05);">
                        <!-- Cover Image with Gradient Overlay -->
                        <div style="height: 180px; background: <?php echo !empty($project['cover_image_url']) ? "url('" . APP_URL . '/' . $project['cover_image_url'] . "') center/cover no-repeat" : "linear-gradient(135deg, rgba(26,34,53,0.8) 0%, rgba(10,15,30,0.9) 100%)"; ?>; position: relative;">
                            
                            <!-- Status Badge -->
                            <?php 
                            $statusColors = [
                                'planning' => 'rgba(59,130,246,0.15)',
                                'active' => 'rgba(249,115,22,0.15)',
                                'paused' => 'rgba(239,68,68,0.15)',
                                'completed' => 'rgba(34,197,94,0.15)'
                            ];
                            $statusTextColors = [
                                'planning' => '#3b82f6',
                                'active' => '#f97316',
                                'paused' => '#ef4444',
                                'completed' => '#22c55e'
                            ];
                            $statusLabels = [
                                'planning' => 'Planeamento',
                                'active' => 'Em Obra',
                                'paused' => 'Pausada',
                                'completed' => 'Concluída'
                            ];
                            $status = $project['status'] ?? 'planning';
                            ?>
                            <span class="badge" style="position: absolute; top: 12px; right: 12px; background: <?php echo $statusColors[$status] ?? 'var(--border-color)'; ?>; color: <?php echo $statusTextColors[$status] ?? 'var(--text-muted)'; ?>; border: 1px solid <?php echo $statusTextColors[$status] ?? 'var(--border-color)'; ?>; font-size:11px; padding:4px 10px; border-radius:var(--radius-sm);">
                                <?php echo $statusLabels[$status] ?? $status; ?>
                            </span>
                        </div>

                        <!-- Card Body -->
                        <div style="padding: 24px; display: flex; flex-direction: column; gap: 14px; flex-grow: 1;">
                            <h3 style="font-size: 18px; font-weight: 800; color: var(--text-primary);">
                                <?php echo sanitize($project['title']); ?>
                            </h3>
                            
                            <?php if (!empty($project['description'])): ?>
                                <p style="font-size: 14px; color: var(--text-muted); line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; min-height: 60px;">
                                    <?php echo sanitize($project['description']); ?>
                                </p>
                            <?php else: ?>
                                <p style="font-size: 14px; color: var(--text-muted); font-style: italic; min-height: 60px;">Sem descrição disponível.</p>
                            <?php endif; ?>

                            <!-- Informações de orçamento e local na base do card -->
                            <div style="display: flex; flex-direction: column; gap: 8px; border-top: 1px solid var(--border-color); padding-top: 16px; font-size: 13px; color: var(--text-secondary); margin-top: auto;">
                                <?php if (!empty($project['location'])): ?>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <i data-lucide="map-pin" style="width: 15px; height: 15px; color: var(--accent-primary);"></i>
                                        <span><?php echo sanitize($project['location']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <i data-lucide="dollar-sign" style="width: 15px; height: 15px; color: var(--accent-secondary);"></i>
                                    <span>Orçamento Previsto: <?php echo format_currency((float)$project['budget'], $profile['currency'] ?? 'AOA'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../../templates/footer.php';
?>
