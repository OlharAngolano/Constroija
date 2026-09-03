<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

if (!validate_csrf()) {
    json_error('Token CSRF inválido.', 403);
}

$db = db();
$action = trim((string)input('action', 'save'));

// Decode do portfólio existente
$portfolio = [];
if (!empty($user['portfolio_data'])) {
    $portfolio = json_decode($user['portfolio_data'], true) ?? [];
}

try {
    if ($action === 'delete') {
        // Apagar as fotos do disco se existirem e limpar as chaves no JSON
        if (!empty($portfolio['before_image']) && file_exists(ROOT_DIR . '/' . $portfolio['before_image'])) {
            @unlink(ROOT_DIR . '/' . $portfolio['before_image']);
        }
        if (!empty($portfolio['after_image']) && file_exists(ROOT_DIR . '/' . $portfolio['after_image'])) {
            @unlink(ROOT_DIR . '/' . $portfolio['after_image']);
        }

        unset($portfolio['before_image']);
        unset($portfolio['after_image']);
        unset($portfolio['before_after_title']);
        unset($portfolio['before_after_description']);

        $portfolioJson = json_encode($portfolio);

        $db->query(
            "UPDATE profiles SET portfolio_data = ? WHERE id = ?",
            [$portfolioJson, $user['id']]
        );

        // Atualizar sessão
        $_SESSION['user']['portfolio_data'] = $portfolioJson;

        json_ok([], 'Widget "Antes e Depois" removido com sucesso!');
    }

    // Carregar novos dados de texto
    $title = trim((string)input('before_after_title', ''));
    $description = trim((string)input('before_after_description', ''));

    // Processar uploads
    $beforeUploadedPath = null;
    $afterUploadedPath = null;

    if (isset($_FILES['before_image']) && $_FILES['before_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $beforeUploadedPath = Upload::file($_FILES['before_image'], 'portfolio');
        if (!$beforeUploadedPath) {
            json_error('Erro ao processar o upload da imagem "Antes". Formato inválido ou ficheiro demasiado grande.');
        }
        
        // Apagar imagem antiga se existir
        if (!empty($portfolio['before_image']) && file_exists(ROOT_DIR . '/' . $portfolio['before_image'])) {
            @unlink(ROOT_DIR . '/' . $portfolio['before_image']);
        }
        $portfolio['before_image'] = $beforeUploadedPath;
    }

    if (isset($_FILES['after_image']) && $_FILES['after_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $afterUploadedPath = Upload::file($_FILES['after_image'], 'portfolio');
        if (!$afterUploadedPath) {
            json_error('Erro ao processar o upload da imagem "Depois". Formato inválido ou ficheiro demasiado grande.');
        }

        // Apagar imagem antiga se existir
        if (!empty($portfolio['after_image']) && file_exists(ROOT_DIR . '/' . $portfolio['after_image'])) {
            @unlink(ROOT_DIR . '/' . $portfolio['after_image']);
        }
        $portfolio['after_image'] = $afterUploadedPath;
    }

    // Atualizar chaves de metadados
    $portfolio['before_after_title'] = $title;
    $portfolio['before_after_description'] = $description;

    $portfolioJson = json_encode($portfolio);

    // Atualizar base de dados
    $db->query(
        "UPDATE profiles SET portfolio_data = ? WHERE id = ?",
        [$portfolioJson, $user['id']]
    );

    // Sincronizar dados da sessão
    $_SESSION['user']['portfolio_data'] = $portfolioJson;

    json_ok([
        'before_image' => $portfolio['before_image'] ?? null,
        'after_image' => $portfolio['after_image'] ?? null,
        'before_after_title' => $portfolio['before_after_title'],
        'before_after_description' => $portfolio['before_after_description']
    ], 'Portfólio interativo atualizado com sucesso!');

} catch (PDOException $e) {
    json_internal_error('Erro na base de dados ao atualizar portfólio: ', $e);
}
