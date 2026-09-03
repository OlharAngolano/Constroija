<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

// Rota estritamente autenticada
middleware_require_auth();

$user = current_user();
$projectId = (int)input('id', 0);
$db = db();

// 1. Procurar o projeto e o cargo do utilizador para validação
try {
    $project = $db->fetch(
        "SELECT p.*, COALESCE(pm.role, 'owner') AS role, pr.name AS owner_name, pr.whatsapp AS owner_phone, pr.email AS owner_email, pr.username AS owner_username
         FROM projects p 
         LEFT JOIN project_managers pm ON p.id = pm.project_id AND pm.user_id = ?
         JOIN profiles pr ON p.user_id = pr.id
         WHERE p.id = ? AND (p.user_id = ? OR pm.user_id = ?)",
        [$user['id'], $projectId, $user['id'], $user['id']]
    );
} catch (PDOException $e) {
    $project = null;
}

if (!$project) {
    set_flash_message('danger', 'Acesso negado ou projeto de obra não encontrado.');
    redirect('/projects');
}

// 2. Garantir a existência de um link público ativo e persistente de longa duração (365 dias)
$shareToken = '';
try {
    $publicLink = $db->fetch(
        "SELECT token FROM public_links WHERE project_id = ? AND expires_at >= ? ORDER BY expires_at DESC LIMIT 1",
        [$projectId, date('Y-m-d H:i:s', strtotime('+30 days'))]
    );

    if (!$publicLink) {
        // Gerar um token persistente válido por 365 dias para a placa de obra física
        $token = bin2hex(random_bytes(16));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+365 days'));
        $db->query(
            "INSERT INTO public_links (project_id, token, expires_at) VALUES (?, ?, ?)",
            [$projectId, $token, $expiresAt]
        );
        $shareToken = $token;
    } else {
        $shareToken = $publicLink['token'];
    }
} catch (PDOException $e) {
    page_error('Erro ao gerar as credenciais de partilha para a placa de obra: ', $e);
}

$publicReportUrl = APP_URL . '/report?token=' . $shareToken;
// (CJ-17) QR gerado LOCALMENTE no navegador (qrcode.bundle.js): o token do
// relatório nunca é enviado para serviços externos.
$nonce = Security::getNonce();

$title = 'Placa de Obra Oficial: ' . sanitize($project['title']);
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    
    <!-- Outfit & Inter Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.344.0/dist/umd/lucide.min.js" nonce="$nonce"></script>

    <style>
        :root {
            --bg-page: #f8fafc;
            --text-main: #0f172a;
            --text-secondary: #475569;
            --border-primary: #cbd5e1;
            --accent-orange: #f97316;
            --font-outfit: 'Outfit', 'Inter', sans-serif;
            --font-inter: 'Inter', sans-serif;
        }

        /* Suporte para Tema Escuro no browser do construtor antes da impressão */
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-page: #0f172a;
                --text-main: #f8fafc;
                --text-secondary: #94a3b8;
                --border-primary: #334155;
            }
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-page);
            color: var(--text-main);
            font-family: var(--font-inter);
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            min-height: 100vh;
        }

        /* Barra superior para impressão (oculta no PDF/impressora) */
        .print-control-bar {
            width: 100%;
            max-width: 800px;
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 16px 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #ffffff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .btn-print {
            background-color: var(--accent-orange);
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 700;
            font-family: var(--font-outfit);
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-print:hover {
            background-color: #ea580c;
            transform: translateY(-1px);
        }

        /* Estrutura Premium da Placa A4 (Sempre branca com bordas para a impressora) */
        .signboard-a4 {
            width: 100%;
            max-width: 800px;
            min-height: 1050px; /* Proporção aproximada do A4 */
            background-color: #ffffff !important;
            color: #0f172a !important;
            border: 12px double #0f172a;
            border-radius: 0;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: relative;
        }

        /* Branding Superior */
        .signboard-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 4px solid #0f172a;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }

        .logo-box {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-box h2 {
            font-family: var(--font-outfit);
            font-weight: 800;
            font-size: 26px;
            letter-spacing: 0.5px;
        }

        .official-tag {
            border: 2px solid #0f172a;
            padding: 4px 12px;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Conteúdo Principal */
        .signboard-body {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .main-title-box {
            text-align: center;
            padding: 10px 0;
        }

        .main-title-box h1 {
            font-family: var(--font-outfit);
            font-size: 36px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a !important;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .info-card {
            border: 2.5px solid #0f172a;
            padding: 16px 20px;
        }

        .info-card label {
            display: block;
            font-family: var(--font-outfit);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #475569 !important;
            margin-bottom: 6px;
        }

        .info-card p {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a !important;
        }

        /* Bloco Dual Lateral: Detalhes e QR Code */
        .qr-section-box {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 30px;
            border: 3px solid #0f172a;
            padding: 30px;
            align-items: center;
        }

        .qr-instructions h3 {
            font-family: var(--font-outfit);
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 12px;
            text-transform: uppercase;
        }

        .qr-instructions p {
            font-size: 14px;
            line-height: 1.6;
            color: #334155;
            font-weight: 500;
        }

        .qr-code-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .qr-image {
            width: 200px;
            height: 200px;
            border: 2px solid #0f172a;
            padding: 8px;
            background: #ffffff;
        }

        .qr-caption {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
            color: #475569;
        }

        /* Rodapé de Ficha Técnica */
        .signboard-footer {
            border-top: 3px solid #0f172a;
            padding-top: 20px;
            margin-top: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            font-weight: 600;
            color: #475569 !important;
        }

        /* Otimizações Especiais de Impressão */
        @media print {
            body {
                background-color: #ffffff !important;
                color: #000000 !important;
                padding: 0 !important;
            }
            .print-control-bar {
                display: none !important;
            }
            .signboard-a4 {
                box-shadow: none !important;
                border: 10px double #000000 !important;
                width: 100% !important;
                max-width: 100% !important;
                height: 100% !important;
                min-height: 100% !important;
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                padding: 30px !important;
            }
            .info-card {
                border-color: #000000 !important;
            }
            .qr-section-box {
                border-color: #000000 !important;
            }
            .qr-image {
                border-color: #000000 !important;
            }
        }
    </style>
</head>
<body>

    <!-- Barra Superior de Controlo (Visível apenas no ecrã) -->
    <div class="print-control-bar">
        <div style="display:flex; flex-direction:column; gap:4px;">
            <h4 style="font-family:var(--font-outfit); font-weight:700;">Placa de Obra Gerada!</h4>
            <p style="font-size:12px; color:#cbd5e1;">Imprima em tamanho A4 para afixar à entrada da sua obra física.</p>
        </div>
        <div style="display:flex; gap:12px;">
            <a href="/projects/detail?id=<?php echo $projectId; ?>" style="color:#ffffff; text-decoration:none; font-size:13px; font-weight:600; display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,0.06); padding:10px 16px; border-radius:6px; border:1px solid rgba(255,255,255,0.1);">
                <i data-lucide="arrow-left" style="width:16px; height:16px;"></i>
                Voltar
            </a>
            <button id="btn-print-signboard" type="button" class="btn-print">
                <i data-lucide="printer" style="width:16px; height:16px;"></i>
                Imprimir Placa (A4)
            </button>
        </div>
    </div>

    <!-- PLACA DE OBRA - FORMATO IMPRESSÃO A4 -->
    <div class="signboard-a4">
        
        <!-- Cabeçalho -->
        <div class="signboard-header">
            <div class="logo-box">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m2 10 10-8 10 8v11a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z"/>
                    <path d="M9 22V12h6v10"/>
                </svg>
                <h2>CONSTRÓI JÁ</h2>
            </div>
            <div class="official-tag">
                Ficha Técnica de Obra
            </div>
        </div>

        <!-- Conteúdo Principal -->
        <div class="signboard-body">
            
            <div class="main-title-box">
                <h1>Ficha Técnica de Obra</h1>
            </div>

            <!-- Grelha de Dados -->
            <div class="info-grid">
                
                <div class="info-card">
                    <label>Título do Projeto / Obra</label>
                    <p><?php echo sanitize($project['title']); ?></p>
                </div>

                <div class="info-card">
                    <label>Localização Física</label>
                    <p><?php echo sanitize($project['location'] ?: 'Não especificada'); ?></p>
                </div>

                <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 20px;">
                    <div class="info-card">
                        <label>Proprietário / Construtor</label>
                        <p><?php echo sanitize($project['owner_name']); ?></p>
                    </div>
                    <div class="info-card">
                        <label>Estado Atual da Construção</label>
                        <p>
                            <?php 
                                $status = $project['status'];
                                echo $status === 'planning' ? 'Planeamento' : ($status === 'active' ? 'Em Execução' : ($status === 'paused' ? 'Pausada' : 'Concluída'));
                            ?>
                        </p>
                    </div>
                </div>

                <?php if ($project['owner_phone'] || $project['owner_email']): ?>
                    <div class="info-card">
                        <label>Contactos para Informações</label>
                        <p style="font-size: 16px; font-weight: 600;">
                            <?php if ($project['owner_phone']): ?>
                                <span style="margin-right: 20px;">Telefone: <?php echo sanitize($project['owner_phone']); ?></span>
                            <?php endif; ?>
                            <?php if ($project['owner_email']): ?>
                                <span>E-mail: <?php echo sanitize($project['owner_email']); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Bloco de QR Code e Acompanhamento -->
            <div class="qr-section-box">
                <div class="qr-instructions">
                    <h3>Acompanhe Esta Obra Online</h3>
                    <p>
                        Aponte a câmara do seu telemóvel para o QR Code ao lado para aceder ao nosso portal seguro Constrói Já.
                    </p>
                    <p style="margin-top: 10px;">
                        Poderá visualizar as fotos de progresso atualizadas, o cronograma físico da construção, os principais materiais utilizados e enviar uma mensagem direta ou contactar o construtor!
                    </p>
                </div>
                <div class="qr-code-wrapper">
                    <div id="qrcode-holder" class="qr-image" aria-label="QR Code de Progresso da Obra"></div>
                    <span class="qr-caption">Aponte a Câmara</span>
                </div>
            </div>

        </div>

        <!-- Rodapé -->
        <div class="signboard-footer">
            <span>© <?php echo date('Y'); ?> Constrói Já — Gestão de Obras Inteligente.</span>
            <span>Identificador da Obra: #<?php echo $projectId; ?></span>
        </div>

    </div>

    <!-- Biblioteca QR local (MIT — ver assets/js/qrcode.LICENSE.txt) -->
    <script src="<?php echo APP_URL; ?>/assets/js/qrcode.bundle.js" nonce="<?php echo $nonce; ?>"></script>

    <script nonce="<?php echo $nonce; ?>">
        // (CJ-17) QR gerado localmente; o URL com token não sai do navegador
        document.addEventListener('DOMContentLoaded', function () {
            var holder = document.getElementById('qrcode-holder');
            var url = <?php echo json_encode($publicReportUrl); ?>;
            if (holder && typeof QRCode !== 'undefined') {
                new QRCode(holder, {
                    text: url,
                    width: 182,
                    height: 182,
                    colorDark: '#0f172a',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
            if (window.lucide) {
                window.lucide.createIcons();
            }
            var printBtn = document.getElementById('btn-print-signboard');
            if (printBtn) {
                printBtn.addEventListener('click', function () { window.print(); });
            }
        });
    </script>
</body>
</html>
