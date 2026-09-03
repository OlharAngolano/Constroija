<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/middleware.php';

// (CJ-07) Serviço de ficheiros PRIVADOS com autorização por projeto.
// Recibos e fotos de despesas nunca são servidos por URL direto; passam
// sempre por aqui, com autenticação, verificação de pertença ao projeto,
// Content-Disposition, nosniff e registo de auditoria.

$user = current_user();
if (!$user) {
    json_error('Não autorizado. Sessão expirada.', 401);
}

$documentId = (int)input('id', 0);
if ($documentId <= 0) {
    json_error('ID de documento inválido.', 400);
}

$isAdmin = (int)($user['is_admin'] ?? 0) === 1;
$db = db();

try {
    // Autorização: membro da equipa do projeto OU administrador
    $document = null;
    if ($isAdmin) {
        $document = $db->fetch("SELECT * FROM documents WHERE id = ?", [$documentId]);
    } else {
        $document = $db->fetch(
            "SELECT d.* FROM documents d
             JOIN project_managers pm ON pm.project_id = d.project_id AND pm.user_id = ?
             WHERE d.id = ?",
            [$user['id'], $documentId]
        );
    }

    if (!$document) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Documento não encontrado.', 'code' => 404]);
        exit;
    }

    // Resolver caminho físico — apenas dentro das zonas permitidas
    $filePath = (string)$document['file_path'];
    $allowedPrefixes = ['uploads/expenses/', 'storage/private/'];
    $inAllowed = false;
    foreach ($allowedPrefixes as $prefix) {
        if (strpos($filePath, $prefix) === 0) {
            $inAllowed = true;
            break;
        }
    }
    if (!$inAllowed) {
        log_internal_error(new RuntimeException('documento com caminho fora das zonas permitidas: ' . $filePath), 'ficheiro privado');
        json_error('Documento inválido.', 400);
    }

    $absolute = realpath(ROOT_DIR . '/' . $filePath);
    if ($absolute === false || !is_file($absolute) || !is_readable($absolute)) {
        log_internal_error(new RuntimeException('ficheiro em falta: ' . $filePath), 'ficheiro privado');
        json_error('Documento indisponível no servidor.', 404);
    }

    // Auditoria de acesso
    try {
        $db->execute(
            "INSERT INTO document_downloads (document_id, user_id, ip_address) VALUES (?, ?, ?)",
            [$documentId, $user['id'], $_SERVER['REMOTE_ADDR'] ?? null]
        );
    } catch (PDOException $e) {
        // auditoria não bloqueia a entrega
    }

    // Tipo MIME real e headers de proteção
    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
        $detected = (new finfo(FILEINFO_MIME_TYPE))->file($absolute);
        if ($detected) {
            $mime = $detected;
        }
    }
    $originalName = basename((string)($document['original_name'] ?: $filePath));
    $originalName = preg_replace('/[^\w\.\-]+/', '_', $originalName) ?: 'ficheiro';
    $download = (int)input('download', 0) === 1;

    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, must-revalidate');
    header('Pragma: no-cache');
    header(
        'Content-Disposition: ' . ($download ? 'attachment' : 'inline')
        . '; filename="' . $originalName . '"'
    );
    header('Content-Length: ' . (string)filesize($absolute));

    // Sem compressão para binários
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }

    $handle = fopen($absolute, 'rb');
    if ($handle === false) {
        json_error('Falha ao ler o documento.', 500);
    }
    while (!feof($handle)) {
        echo fread($handle, 8192);
    }
    fclose($handle);
    exit;
} catch (PDOException $e) {
    json_internal_error('servir documento privado', $e);
}
