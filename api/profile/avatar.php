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

// Para uploads multipart/form-data o CSRF pode vir via POST normal ou header
if (!validate_csrf()) {
    json_error('Token CSRF inválido ou expirado.', 403);
}

if (!isset($_FILES['avatar'])) {
    json_error('Nenhum ficheiro enviado.');
}

$db = db();

try {
    $uploadedPath = Upload::file($_FILES['avatar'], 'avatars');
    
    if (!$uploadedPath) {
        json_error('Falha ao processar o upload do avatar. Verifique o tamanho (máx 10MB) e formato (JPG, PNG, WEBP, GIF).');
    }

    // Guardar o caminho antigo para opcionalmente apagar depois
    $oldAvatar = $user['avatar_url'];

    // Atualizar base de dados
    $db->query(
        "UPDATE profiles SET avatar_url = ? WHERE id = ?",
        [$uploadedPath, $user['id']]
    );

    // Atualizar a sessão
    $updatedUser = $db->fetch("SELECT * FROM profiles WHERE id = ?", [$user['id']]);
    $_SESSION['user'] = $updatedUser;

    // Se existia avatar antigo e não era padrão, podemos tentar apagar
    if ($oldAvatar && strpos($oldAvatar, 'uploads/avatars/') === 0) {
        $oldFile = ROOT_DIR . '/' . $oldAvatar;
        if (file_exists($oldFile)) {
            @unlink($oldFile);
        }
    }

    $avatarFullUrl = get_avatar_url($uploadedPath, $updatedUser['name']);

    json_ok([
        'avatar_url' => $avatarFullUrl,
        'relative_path' => $uploadedPath
    ], 'Foto de perfil atualizada com sucesso!');

} catch (Exception $e) {
    json_error('Erro ao guardar a foto de perfil: ' . $e->getMessage(), 500);
}
