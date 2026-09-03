<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

// Validar token CSRF e autenticação
if (!validate_csrf()) {
    json_error('Token CSRF inválido ou expirado. Recarregue a página.', 403);
}

$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

$content = trim((string)input('content', ''));
$fileUrl = null;
$fileType = null;

// Processar upload de imagem/PDF opcional
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $uploadedPath = Upload::file($_FILES['file'], 'posts');
    if ($uploadedPath) {
        $fileUrl = $uploadedPath;
        $fileType = $_FILES['file']['type'];
    } else {
        json_error('Falha no upload do ficheiro. Certifique-se de que é uma imagem, PDF ou vídeo válido (máx. 50MB).');
    }
}

// Validar se o post não está vazio
if ($content === '' && $fileUrl === null) {
    json_error('A publicação tem de conter texto ou um ficheiro anexo.');
}

$db = db();

try {
    $qualities = ['status' => 'done'];
    $isReel = (int)input('is_reel', 0);
    
    if ($fileUrl && strpos($fileType, 'video/') === 0) {
        if (is_ffmpeg_available()) {
            $qualities['status'] = 'processing';
        } else {
            $qualities['original'] = str_replace('\\', '/', $fileUrl);
        }
    }

    $inserted = $db->execute(
        "INSERT INTO posts (user_id, content, file_url, file_type, is_reel, video_qualities) VALUES (?, ?, ?, ?, ?, ?)",
        [$user['id'], $content, $fileUrl, $fileType, $isReel, json_encode($qualities)]
    );

    if (!$inserted) {
        json_error('Erro ao guardar a publicação.');
    }

    $postId = $db->lastInsertId();

    // Se for vídeo e ffmpeg está disponível, disparar processo de transcoding em background
    if ($fileUrl && strpos($fileType, 'video/') === 0 && is_ffmpeg_available()) {
        $scriptPath = escapeshellarg(__DIR__ . '/transcode.php');
        $argPostId = escapeshellarg((string)$postId);
        $argFileUrl = escapeshellarg($fileUrl);
        
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start /B php $scriptPath $argPostId $argFileUrl", "r"));
        } else {
            exec("php $scriptPath $argPostId $argFileUrl > /dev/null 2>&1 &");
        }
    }

    // Obter dados do post acabado de criar para retornar no AJAX
    $post = $db->fetch(
        "SELECT p.*, pr.name, pr.username, pr.avatar_url 
         FROM posts p 
         JOIN profiles pr ON p.user_id = pr.id 
         WHERE p.id = ?",
        [$postId]
    );

    $post['avatar_url'] = get_avatar_url($post['avatar_url'], $post['name']);
    $post['likes_count'] = 0;
    $post['comments_count'] = 0;
    $post['is_liked'] = 0;
    $post['comments'] = [];
    if (!empty($post['video_qualities'])) {
        $post['video_qualities'] = json_decode($post['video_qualities'], true);
    } else {
        $post['video_qualities'] = null;
    }

    set_flash_message('success', 'Publicação criada com sucesso!');
    json_ok(['post' => $post], 'Publicação criada.');

} catch (PDOException $e) {
    json_internal_error('Erro técnico ao registar post na base de dados: ', $e);
}
