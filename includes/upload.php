<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Upload — revisão de segurança (auditorias CJ-07 e CJ-08).
 *
 * - Listas MIME por CONTEXTO (avatar/capa não aceita PDF/CSV/vídeo, etc.)
 * - Limites de tamanho distintos por contexto (10 MB imagens/docs, 50 MB vídeo)
 * - Validação de dimensões/pixeis para imagens
 * - Conteúdo financeiro (despesas: fotos e recibos) guardado FORA do web root,
 *   em storage/private, e servido apenas via /api/files com autorização
 */
class Upload {
    /** Último erro descritivo (para mensagens ao utilizador) */
    public static ?string $lastError = null;

    /** Tipos MIME permitidos por contexto */
    private static array $contexts = [
        'image' => [
            'mimes' => [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'gif',
            ],
            'maxBytes' => 10 * 1024 * 1024,      // 10 MB
            'maxWidth' => 6000,
            'maxHeight'=> 6000,
        ],
        'receipt' => [
            'mimes' => [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'gif',
                'application/pdf' => 'pdf',
            ],
            'maxBytes' => 10 * 1024 * 1024,      // 10 MB
            'maxWidth' => 6000,
            'maxHeight'=> 6000,
        ],
        'video' => [
            'mimes' => [
                'video/mp4'  => 'mp4',
                'video/webm' => 'webm',
                'video/ogg'  => 'ogg',
                'video/quicktime' => 'mov',
                'video/x-matroska' => 'mkv',
            ],
            'maxBytes' => 50 * 1024 * 1024,      // 50 MB
        ],
        'document' => [
            'mimes' => [
                'application/pdf'  => 'pdf',
                'text/csv'         => 'csv',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'application/vnd.ms-excel' => 'xls',
            ],
            'maxBytes' => 10 * 1024 * 1024,
        ],
    ];

    /** Contextos permitidos por destino (subpasta) */
    private static array $destinations = [
        'avatars'           => ['image', 'public'],
        'projects'          => ['image', 'public'],
        'portfolio'         => ['image', 'public'],
        'posts'             => ['image', 'video', 'public'],
        'products'          => ['image', 'public'],
        'vendors'           => ['image', 'public'],
        'expenses/photos'   => ['image', 'private'],   // CJ-07: privado
        'expenses/receipts' => ['receipt', 'private'], // CJ-07: privado
        ''                  => ['image', 'video', 'document', 'public'], // retrocompatibilidade
    ];

    /**
     * Efetua o upload de um ficheiro com validação por contexto.
     *
     * @param array  $file      Elemento de $_FILES
     * @param string $subDir    Subpasta de destino ('avatars', 'expenses/receipts', ...)
     * @return string|null      Caminho relativo guardado, ou null em caso de falha
     */
    public static function file(array $file, string $subDir = ''): ?string {
        self::$lastError = null;
        $subDir = trim($subDir, '/');

        // 1. Definir política para o destino
        $policy = self::$destinations[$subDir] ?? self::$destinations[''];
        $privacy = in_array('private', $policy, true) ? 'private' : 'public';
        $allowedContexts = array_values(array_filter($policy, fn ($c) => $c !== 'public' && $c !== 'private'));

        // 2. Validar estrutura do upload
        if (!isset($file['error']) || is_array($file['error'])) {
            self::$lastError = 'Estrutura de upload inválida.';
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            self::$lastError = 'O upload falhou no navegador (código ' . (int)$file['error'] . ').';
            return null;
        }

        // 3. Detetar tipo MIME real (Fileinfo) e escolher o contexto certo
        if (!class_exists('finfo')) {
            self::$lastError = 'Extensão php_fileinfo indisponível no servidor.';
            error_log('Upload Error: php_fileinfo extension is not enabled.');
            return null;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if ($mimeType === false || $mimeType === '') {
            self::$lastError = 'Não foi possível identificar o tipo do ficheiro.';
            return null;
        }

        // Encontrar um contexto permitido que aceite este MIME
        $context = null;
        foreach ($allowedContexts as $ctxName) {
            if (isset(self::$contexts[$ctxName]['mimes'][$mimeType])) {
                $context = self::$contexts[$ctxName];
                break;
            }
        }
        if ($context === null) {
            self::$lastError = 'Formato de ficheiro não permitido para este destino.';
            error_log('Upload Error: MIME ' . $mimeType . ' rejeitado para destino "' . ($subDir ?: 'default') . '".');
            return null;
        }

        // 4. Limite de tamanho por contexto
        $maxBytes = $context['maxBytes'];
        if ((int)$file['size'] > $maxBytes) {
            self::$lastError = 'Ficheiro demasiado grande (máximo ' . round($maxBytes / 1024 / 1024) . ' MB).';
            return null;
        }

        // 5. Para imagens: validar dimensões (anti bomba-de-descompressão / abuso)
        if (isset($context['maxWidth'])) {
            $size = @getimagesize($file['tmp_name']);
            if ($size === false) {
                self::$lastError = 'Ficheiro de imagem inválido ou corrompido.';
                return null;
            }
            [$width, $height] = $size;
            if ($width > $context['maxWidth'] || $height > $context['maxHeight']) {
                self::$lastError = 'Imagem demasiado grande (máximo '
                    . $context['maxWidth'] . 'x' . $context['maxHeight'] . ' px).';
                return null;
            }
        }

        $extension = $context['mimes'][$mimeType];

        // 6. Nome aleatório
        $randomName = bin2hex(random_bytes(16)) . '.' . $extension;

        // 7. Diretório de destino (público em uploads/, privado em storage/private)
        $isImageContext = isset($context['maxWidth']);
        $baseDir = $privacy === 'private' ? PRIVATE_UPLOAD_DIR : UPLOAD_DIR;
        $targetDir = $baseDir;
        if (!empty($subDir)) {
            $targetDir .= '/' . $subDir;
        }
        if (!is_dir($targetDir)) {
            $mode = $privacy === 'private' ? 0750 : 0755;
            if (!@mkdir($targetDir, $mode, true) && !is_dir($targetDir)) {
                self::$lastError = 'Falha ao criar a pasta de destino no servidor.';
                error_log('Upload Error: Failed to create target directory ' . $targetDir);
                return null;
            }
        }

        $destination = $targetDir . '/' . $randomName;

        // 8. Mover ficheiro
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            self::$lastError = 'Falha ao guardar o ficheiro no servidor.';
            return null;
        }
        @chmod($destination, $privacy === 'private' ? 0640 : 0644);

        // 9. Comprimir/otimizar imagem se aplicável
        if ($isImageContext) {
            $compressed = self::compressImage($destination, $mimeType);
            if ($compressed !== null && $compressed !== $destination) {
                $destination = $compressed;
                $randomName = basename($compressed);
            }
        }

        // 10. Caminho relativo (para a base de dados)
        $prefix = $privacy === 'private' ? 'storage/private' : 'uploads';
        $relativePath = $prefix;
        if (!empty($subDir)) {
            $relativePath .= '/' . $subDir . '/';
        } else {
            $relativePath .= '/';
        }
        $relativePath .= $randomName;

        return $relativePath;
    }

    /**
     * Devolve true se o caminho relativo corresponde a armazenamento privado
     */
    public static function isPrivatePath(string $relativePath): bool {
        return strpos($relativePath, 'storage/private/') === 0;
    }

    /**
     * Comprime e otimiza uma imagem enviada pelo utilizador.
     * Redimensiona para máx. 1920 px e converte para WebP/JPEG.
     */
    private static function compressImage(string $filePath, string $mimeType): ?string {
        $imageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mimeType, $imageTypes, true)) {
            return null;
        }
        if (!extension_loaded('gd')) {
            return null;
        }

        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($filePath),
            'image/png'  => @imagecreatefrompng($filePath),
            'image/webp' => @imagecreatefromwebp($filePath),
            'image/gif'  => @imagecreatefromgif($filePath),
            default      => false,
        };
        if ($image === false) {
            return null;
        }

        $originalWidth  = imagesx($image);
        $originalHeight = imagesy($image);
        $maxWidth = 1920;
        if ($originalWidth > $maxWidth) {
            $ratio = $maxWidth / $originalWidth;
            $newWidth  = $maxWidth;
            $newHeight = (int)round($originalHeight * $ratio);
            $resized = imagecreatetruecolor($newWidth, $newHeight);
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

        $supportsWebp = function_exists('imagewebp');
        $pathInfo = pathinfo($filePath);
        $basePath = $pathInfo['dirname'] . '/' . $pathInfo['filename'];

        if ($supportsWebp) {
            $newPath = $basePath . '.webp';
            $saved = imagewebp($image, $newPath, 70);
        } else {
            $newPath = $basePath . '.jpg';
            $saved = imagejpeg($image, $newPath, 70);
        }
        imagedestroy($image);

        if (!$saved) {
            return null;
        }
        if (realpath($filePath) !== realpath($newPath) && file_exists($filePath)) {
            @unlink($filePath);
        }
        return $newPath;
    }
}
