<?php
declare(strict_types=1);

class Upload {
    private static array $allowedTypes = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/gif'       => 'gif',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
        'text/csv'        => 'csv',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-excel' => 'xls',
        'video/mp4'       => 'mp4',
        'video/webm'      => 'webm',
        'video/ogg'       => 'ogg',
        'video/quicktime' => 'mov',
        'video/x-matroska' => 'mkv'
    ];

    /**
     * Efetua o upload de um ficheiro de forma extremamente segura
     * 
     * @param array $file O elemento do $_FILES (ex: $_FILES['foto'])
     * @param string $subDir Subpasta dentro de /uploads/ (ex: 'avatars', 'projects', 'receipts')
     * @return string|null Retorna o caminho relativo do ficheiro salvo (ex: 'uploads/avatars/ab12...png') ou null se falhar
     */
    public static function file(array $file, string $subDir = ''): ?string {
        // 1. Validar se o upload correu sem erros na estrutura
        if (!isset($file['error']) || is_array($file['error'])) {
            return null;
        }

        // 2. Verificar códigos de erro padrão do PHP
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        // 3. Limitar tamanho máximo de upload (50MB para suportar vídeos)
        if ($file['size'] > 50 * 1024 * 1024) {
            return null;
        }

        // 4. Detetar tipo MIME real através do Fileinfo (ignora cabeçalhos do cliente)
        if (!class_exists('finfo')) {
            error_log("Upload Error: php_fileinfo extension is not enabled in PHP settings.");
            return null;
        }
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!array_key_exists($mimeType, self::$allowedTypes)) {
            return null;
        }

        $extension = self::$allowedTypes[$mimeType];

        // 5. Gerar nome de ficheiro único e aleatório (32 chars hex + ext)
        $randomName = bin2hex(random_bytes(16)) . '.' . $extension;

        // 6. Preparar diretório de destino
        $targetDir = UPLOAD_DIR;
        if (!empty($subDir)) {
            $targetDir .= '/' . trim($subDir, '/');
        }

        // Criar pasta de destino se não existir
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                error_log("Upload Error: Failed to create target directory " . $targetDir);
                return null;
            }
        }

        $destination = $targetDir . '/' . $randomName;

        // 7. Mover ficheiro temporário para a localização final
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // 8. Comprimir imagem se aplicável (redimensionar e otimizar)
            $compressResult = self::compressImage($destination, $mimeType);
            if ($compressResult !== null) {
                // Atualizar nome do ficheiro caso a extensão tenha mudado (ex: png -> webp)
                $randomName = basename($compressResult);
                $destination = $compressResult;
            }

            // Retorna o caminho relativo a partir do web root (ex: uploads/receipts/23f...pdf)
            $relativePath = 'uploads/';
            if (!empty($subDir)) {
                $relativePath .= trim($subDir, '/') . '/';
            }
            $relativePath .= $randomName;
            
            return $relativePath;
        }

        return null;
    }
    /**
     * Comprime e otimiza uma imagem enviada pelo utilizador
     * 
     * - Redimensiona se a largura exceder 1920px (mantém proporção)
     * - Converte para WebP (se suportado) ou JPEG com qualidade de 70%
     * - Remove o ficheiro original caso o formato tenha sido alterado
     * 
     * @param string $filePath Caminho absoluto do ficheiro de imagem
     * @param string $mimeType Tipo MIME real detetado pelo Fileinfo
     * @return string|null Novo caminho absoluto do ficheiro comprimido, ou null se não for imagem
     */
    private static function compressImage(string $filePath, string $mimeType): ?string {
        // Tipos de imagem suportados para compressão via GD
        $imageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if (!in_array($mimeType, $imageTypes, true)) {
            return null; // Não é imagem — ignorar compressão
        }

        // Verificar se a extensão GD está disponível
        if (!extension_loaded('gd')) {
            error_log("Upload Warning: extensão GD não disponível. Compressão de imagem ignorada.");
            return null;
        }

        // Carregar a imagem original conforme o tipo MIME
        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($filePath),
            'image/png'  => @imagecreatefrompng($filePath),
            'image/webp' => @imagecreatefromwebp($filePath),
            'image/gif'  => @imagecreatefromgif($filePath),
            default      => false,
        };

        if ($image === false) {
            error_log("Upload Warning: falha ao carregar imagem para compressão: " . $filePath);
            return null;
        }

        $originalWidth  = imagesx($image);
        $originalHeight = imagesy($image);
        $maxWidth = 1920;

        // Redimensionar se a largura exceder o máximo permitido (manter proporção)
        if ($originalWidth > $maxWidth) {
            $ratio = $maxWidth / $originalWidth;
            $newWidth  = $maxWidth;
            $newHeight = (int)round($originalHeight * $ratio);

            $resized = imagecreatetruecolor($newWidth, $newHeight);

            // Preservar transparência para PNG e WebP
            if (in_array($mimeType, ['image/png', 'image/webp'], true)) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
            }

            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
            imagedestroy($image);
            $image = $resized;
        }

        // Determinar formato de saída: preferir WebP se suportado pelo GD
        $supportsWebp = function_exists('imagewebp');
        $pathInfo = pathinfo($filePath);
        $basePath = $pathInfo['dirname'] . '/' . $pathInfo['filename'];
        $originalPath = $filePath;

        if ($supportsWebp) {
            // Guardar como WebP com qualidade 70%
            $newPath = $basePath . '.webp';
            $saved = imagewebp($image, $newPath, 70);
        } else {
            // Guardar como JPEG com qualidade 70%
            $newPath = $basePath . '.jpg';
            $saved = imagejpeg($image, $newPath, 70);
        }

        imagedestroy($image);

        if (!$saved) {
            error_log("Upload Warning: falha ao guardar imagem comprimida: " . $newPath);
            return null;
        }

        // Eliminar ficheiro original se o formato/caminho mudou
        if (realpath($originalPath) !== realpath($newPath) && file_exists($originalPath)) {
            unlink($originalPath);
        }

        return $newPath;
    }
}
