<?php
declare(strict_types=1);

/**
 * Constrói Já — Executor de migrações (USO EXCLUSIVO DE OPERADOR/CLI)
 *
 * As migrações vivem em migrations/*.sql e são aplicadas por ordem alfabética,
 * registadas na tabela schema_migrations. Nenhum DDL é executado em pedidos
 * web (auditoria CJ-09).
 *
 * Uso:
 *   php bin/migrate.php            # aplica migrações pendentes
 *   php bin/migrate.php --dry-run  # mostra o que seria aplicado
 *   php bin/migrate.php --status   # lista migrações aplicadas/pendentes
 *
 * Requisitos: configurar .env (DB_*) antes de executar. A conta SQL usada aqui
 * DEVE ter privilégios de DDL; a conta da aplicação NÃO deve ter.
 */

require_once dirname(__DIR__) . '/config/config.php';

$dryRun = in_array('--dry-run', $argv, true);
$statusOnly = in_array('--status', $argv, true);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso negado: este script só pode ser executado na linha de comandos.' . PHP_EOL);
}

$db = db();
$migrationsDir = dirname(__DIR__) . '/migrations';

try {
    // Tabela de registo
    $db->execute(
        "CREATE TABLE IF NOT EXISTS schema_migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            checksum CHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
} catch (PDOException $e) {
    fwrite(STDERR, 'Falha ao criar schema_migrations: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$files = glob($migrationsDir . '/*.{sql,php}', GLOB_BRACE);
sort($files);

$applied = [];
foreach ($db->fetchAll("SELECT filename, checksum FROM schema_migrations") as $row) {
    $applied[$row['filename']] = $row['checksum'];
}

$pending = 0;
foreach ($files as $file) {
    $name = basename($file);
    $checksum = hash_file('sha256', $file);

    if ($statusOnly || $dryRun) {
        if (isset($applied[$name])) {
            if ($applied[$name] !== $checksum) {
                echo "AVISO: {$name} foi modificada depois de aplicada (checksum diferente)." . PHP_EOL;
            } else {
                echo "[ok]     {$name}" . PHP_EOL;
            }
        } else {
            echo "[pendente] {$name}" . PHP_EOL;
            $pending++;
        }
        continue;
    }

    if (isset($applied[$name])) {
        if ($applied[$name] !== $checksum) {
            fwrite(STDERR, "AVISO: {$name} foi modificada depois de aplicada. Execute manualmente se necessário." . PHP_EOL);
        }
        continue;
    }

    echo "Aplicando {$name} ... ";
    try {
        $db->beginTransaction();

        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
            // Migração PHP: o ficheiro devolve uma callable que recebe a Database.
            // Permite verificações condicionais (ex.: ADD COLUMN apenas se faltar).
            $migration = require $file;
            if (!is_callable($migration)) {
                throw new RuntimeException("Migração {$name} não devolve uma callable.");
            }
            $migration($db);
        } else {
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Falha ao ler ' . $file);
            }
            // Dividir por instruções (sem ';' dentro de strings nos nossos ficheiros)
            foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
                // remover comentários de linha
                $clean = preg_replace('/^\s*--.*$/m', '', $statement);
                $clean = trim($clean);
                if ($clean === '') {
                    continue;
                }
                $db->getConnection()->exec($clean);
            }
        }

        $db->execute(
            "INSERT INTO schema_migrations (filename, checksum) VALUES (?, ?)",
            [$name, $checksum]
        );
        $db->commit();
        echo "ok." . PHP_EOL;
    } catch (Throwable $e) {
        if ($db->getConnection()->inTransaction()) {
            $db->rollBack();
        }
        fwrite(STDERR, 'FALHOU: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

if ($statusOnly || $dryRun) {
    echo ($pending === 0 ? 'Sem migrações pendentes.' : $pending . ' migração(ões) pendente(s).') . PHP_EOL;
} else {
    echo 'Migrações concluídas.' . PHP_EOL;
}
