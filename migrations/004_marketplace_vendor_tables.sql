-- 004 — Tabelas de parceiros, marketplace e lojas de vendedor
-- (criadas antigamente em runtime pelas páginas — CJ-09)

CREATE TABLE IF NOT EXISTS partners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  logo VARCHAR(255) NOT NULL,
  category VARCHAR(50) NOT NULL,
  `desc` TEXT NOT NULL,
  discount VARCHAR(100) NOT NULL,
  coupon VARCHAR(50) NOT NULL,
  whatsapp VARCHAR(50) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vendor_stores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  store_name VARCHAR(255) NOT NULL,
  logo_url VARCHAR(500) DEFAULT NULL,
  banner_url VARCHAR(500) DEFAULT NULL,
  category VARCHAR(50) NOT NULL DEFAULT 'construcao',
  description TEXT DEFAULT NULL,
  whatsapp VARCHAR(50) NOT NULL,
  location VARCHAR(255) DEFAULT 'Luanda, Angola',
  is_verified TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_vendor_stores_user FOREIGN KEY (user_id) REFERENCES profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketplace_products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  vendor_store_id INT UNSIGNED DEFAULT NULL,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(50) NOT NULL DEFAULT 'outros',
  partner VARCHAR(255) NOT NULL,
  whatsapp VARCHAR(50) NOT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  unit VARCHAR(50) NOT NULL DEFAULT 'un',
  discount_pct INT NOT NULL DEFAULT 0,
  coupon VARCHAR(50) DEFAULT NULL,
  image VARCHAR(500) DEFAULT NULL,
  `desc` TEXT DEFAULT NULL,
  stock_status VARCHAR(20) NOT NULL DEFAULT 'in_stock',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_marketplace_products_user FOREIGN KEY (user_id) REFERENCES profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Conteúdo inicial de parceiros (apenas se a tabela estiver vazia)
INSERT INTO partners (name, logo, category, `desc`, discount, coupon, whatsapp)
SELECT * FROM (
  SELECT 'Sika Angola' AS name, 'https://images.unsplash.com/photo-1581094288338-2314dddb7eed?w=150&auto=format&fit=crop&q=60' AS logo, 'acabamentos' AS category, 'Líder em impermeabilização, adjuvantes de betão, colagens elásticas e selagens no mercado angolano.' AS `desc`, '15% de Desconto' AS discount, 'SIKAVIP15' AS coupon, '244923000001' AS whatsapp
  UNION ALL SELECT 'Cimento Secil Lobito', 'https://images.unsplash.com/photo-1590069261209-f8e9b8642343?w=150&auto=format&fit=crop&q=60', 'construcao', 'Cimento de altíssima qualidade produzido localmente. Ideal para betão estrutural, rebocos e alvenaria.', '10% de Desconto', 'SECILVIP10', '244923000002'
  UNION ALL SELECT 'Tintas CIN Angola', 'https://images.unsplash.com/photo-1562259949-e8e7689d7828?w=150&auto=format&fit=crop&q=60', 'pintura', 'Toda a gama de tintas decorativas e industriais premium com catálogo completo de cores para o seu projeto.', '20% de Desconto', 'CINVIP20', '244923000003'
  UNION ALL SELECT 'Bazar Civil de Angola', 'https://images.unsplash.com/photo-1534224039826-c7a0eda0e6b3?w=150&auto=format&fit=crop&q=60', 'outros', 'Larga gama de ferragens, cerâmicas, sanitários e ferramentas manuais/elétricas para construção civil.', '5% de Desconto extra', 'BAZARVIP05', '244923000004'
  UNION ALL SELECT 'ElecAngola Equipamentos', 'https://images.unsplash.com/photo-1558346490-a72e53ae2d4f?w=150&auto=format&fit=crop&q=60', 'outros', 'Cabos elétricos, disjuntores, iluminação LED e quadros elétricos certificados para obras residenciais.', '12% de Desconto', 'ELECVIP12', '244923000005'
  UNION ALL SELECT 'Mapei Angola', 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=300&auto=format&fit=crop&q=60', 'acabamentos', 'Adesivos químicos premium, argamassas especiais e produtos para assentamento de ladrilhos e pedras.', '15% de Desconto', 'MAPEIVIP15', '244923000006'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM partners LIMIT 1);

-- Parceiros legados (criados por versões antigas em runtime) sem whatsapp:
-- preencher com o contacto genérico do programa de parceiros (CJ-09/CJ-16).
UPDATE partners SET whatsapp = '244923000000' WHERE whatsapp IS NULL OR whatsapp = '';
