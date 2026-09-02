<?php
declare(strict_types=1);

// Permitir execução longa
set_time_limit(600); 

require_once __DIR__ . '/../../includes/helpers.php';

// Obter argumentos CLI
if (PHP_SAPI !== 'cli') {
    die("Apenas execução via CLI permitida.");
}

$postId = (int)($argv[1] ?? 0);
$origPath = $argv[2] ?? '';

if ($postId <= 0 || empty($origPath) || !file_exists(__DIR__ . '/../../' . $origPath)) {
    die("Argumentos inválidos.");
}

$db = db();

try {
    $realPathOrig = realpath(__DIR__ . '/../../' . $origPath);
    $pathInfo = pathinfo($realPathOrig);
    
    // Nomes dos ficheiros de saída
    $dest360 = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_360.mp4';
    $dest720 = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_720.mp4';
    
    $rel360 = str_replace(realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR, '', $dest360);
    $rel720 = str_replace(realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR, '', $dest720);
    
    // Normalizar caminhos para usar barra normal em JSON
    $rel360 = str_replace('\\', '/', $rel360);
    $rel720 = str_replace('\\', '/', $rel720);
    
    $ffmpeg = get_ffmpeg_path() ?? 'ffmpeg';

    // Executar compressão 360p
    $cmd360 = $ffmpeg . " -y -i " . escapeshellarg($realPathOrig) . " -vf \"scale=-2:360\" -vcodec libx264 -crf 28 -acodec aac -b:a 64k -preset superfast " . escapeshellarg($dest360) . " 2>&1";
    exec($cmd360, $out360, $ret360);
    
    // Executar compressão 720p
    $cmd720 = $ffmpeg . " -y -i " . escapeshellarg($realPathOrig) . " -vf \"scale=-2:720\" -vcodec libx264 -crf 23 -acodec aac -b:a 128k -preset superfast " . escapeshellarg($dest720) . " 2>&1";
    exec($cmd720, $out720, $ret720);
    
    $qualities = [];
    if ($ret360 === 0 && file_exists($dest360)) {
        $qualities['360p'] = $rel360;
    }
    if ($ret720 === 0 && file_exists($dest720)) {
        $qualities['720p'] = $rel720;
    }
    
    if (!empty($qualities)) {
        $qualities['status'] = 'done';
        // Atualizar posts
        $defaultUrl = $qualities['720p'] ?? $qualities['360p'] ?? $origPath;
        $db->execute(
            "UPDATE posts SET file_url = ?, video_qualities = ? WHERE id = ?",
            [$defaultUrl, json_encode($qualities), $postId]
        );
        
        // Apagar o ficheiro original para poupar espaço se gerou comprimidos
        if (file_exists($realPathOrig) && $realPathOrig !== realpath(__DIR__ . '/../../' . $defaultUrl)) {
            @unlink($realPathOrig);
        }
    } else {
        // Falhou transcoding, manter o original
        $qualities = [
            'original' => str_replace('\\', '/', $origPath),
            'status' => 'done'
        ];
        $db->execute(
            "UPDATE posts SET video_qualities = ? WHERE id = ?",
            [json_encode($qualities), $postId]
        );
    }
    
} catch (Throwable $e) {
    error_log("Erro no transcode do post {$postId}: " . $e->getMessage());
}
