<?php
/**
 * Migração 003 — Colunas que antigamente eram adicionadas em runtime (CJ-09).
 *
 * Esta migração só adiciona a coluna quando ela não existe, pelo que pode ser
 * executada tanto em bases novas como nas que já vêm de versões anteriores.
 */

return function ($db): void {
    /** @var Database $db */

    $columnExists = static function (string $table, string $column) use ($db): bool {
        try {
            $db->query("SELECT `{$column}` FROM `{$table}` LIMIT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    };

    $tableExists = static function (string $table) use ($db): bool {
        try {
            $db->query("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    };

    // profiles.last_activity_at (atividade online)
    if ($tableExists('profiles') && !$columnExists('profiles', 'last_activity_at')) {
        $db->execute("ALTER TABLE profiles ADD COLUMN last_activity_at TIMESTAMP NULL DEFAULT NULL");
    }

    // posts.is_reel e posts.video_qualities (feed/reels)
    if ($tableExists('posts') && !$columnExists('posts', 'is_reel')) {
        $db->execute("ALTER TABLE posts ADD COLUMN is_reel TINYINT DEFAULT 0");
    }
    if ($tableExists('posts') && !$columnExists('posts', 'video_qualities')) {
        $db->execute("ALTER TABLE posts ADD COLUMN video_qualities TEXT NULL DEFAULT NULL");
    }

    // partners.whatsapp
    if ($tableExists('partners') && !$columnExists('partners', 'whatsapp')) {
        $db->execute("ALTER TABLE partners ADD COLUMN whatsapp VARCHAR(50) DEFAULT NULL");
    }

    // marketplace_products: colunas da loja de vendedor
    if ($tableExists('marketplace_products')) {
        if (!$columnExists('marketplace_products', 'vendor_store_id')) {
            $db->execute("ALTER TABLE marketplace_products ADD COLUMN vendor_store_id INT DEFAULT NULL");
        }
        if (!$columnExists('marketplace_products', 'stock_status')) {
            $db->execute("ALTER TABLE marketplace_products ADD COLUMN stock_status VARCHAR(20) NOT NULL DEFAULT 'in_stock'");
        }
        if (!$columnExists('marketplace_products', 'discount_pct')) {
            $db->execute("ALTER TABLE marketplace_products ADD COLUMN discount_pct INT NOT NULL DEFAULT 0");
        }
        if (!$columnExists('marketplace_products', 'coupon')) {
            $db->execute("ALTER TABLE marketplace_products ADD COLUMN coupon VARCHAR(50) DEFAULT NULL");
        }
    }
};
