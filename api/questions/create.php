<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

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

$title = trim((string)input('title', ''));
$content = trim((string)input('content', ''));
$tagsInput = input('tags', []);

if ($title === '' || $content === '') {
    json_error('O título e o conteúdo são obrigatórios.');
}

// Tratar as tags (devem ser guardadas em JSON como array)
$tags = [];
if (is_array($tagsInput)) {
    foreach ($tagsInput as $t) {
        $tags[] = sanitize(trim((string)$t));
    }
} elseif (is_string($tagsInput)) {
    $parts = explode(',', $tagsInput);
    foreach ($parts as $p) {
        $trimmed = trim($p);
        if ($trimmed !== '') {
            $tags[] = sanitize($trimmed);
        }
    }
}

$tagsJson = json_encode($tags);
$db = db();

try {
    $db->query(
        "INSERT INTO questions (user_id, title, content, tags) VALUES (?, ?, ?, ?)",
        [$user['id'], $title, $content, $tagsJson]
    );
    
    $questionId = (int)$db->fetch("SELECT LAST_INSERT_ID() as id")['id'];
    
    json_ok(['question_id' => $questionId], 'Pergunta publicada com sucesso!');
} catch (PDOException $e) {
    json_internal_error('Erro técnico ao publicar a pergunta: ', $e);
}
