-- 001 — Tabelas de segurança, faturação e documentos privados
-- Auditorias CJ-01 (pedidos de pagamento), CJ-07 (ficheiros privados),
-- CJ-16 (tokens "remember me" por dispositivo).

-- Tokens "remember me" por dispositivo: seletor público + hash do validador
CREATE TABLE IF NOT EXISTS auth_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  selector CHAR(18) NOT NULL UNIQUE,
  validator_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_auth_tokens_user FOREIGN KEY (user_id) REFERENCES profiles(id) ON DELETE CASCADE,
  INDEX idx_auth_tokens_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pedidos de pagamento criados no servidor (sem auto-ativação pelo cliente)
CREATE TABLE IF NOT EXISTS payment_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  plan_key VARCHAR(20) NOT NULL,
  plan_name VARCHAR(60) NOT NULL,
  months SMALLINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency VARCHAR(8) NOT NULL DEFAULT 'AOA',
  status ENUM('pending','paid','cancelled','expired') NOT NULL DEFAULT 'pending',
  gateway VARCHAR(40) DEFAULT NULL,
  gateway_ref VARCHAR(190) DEFAULT NULL,
  provider_tx_id VARCHAR(190) DEFAULT NULL,
  idempotency_key VARCHAR(64) DEFAULT NULL,
  notes VARCHAR(500) DEFAULT NULL,
  paid_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_orders_user FOREIGN KEY (user_id) REFERENCES profiles(id) ON DELETE CASCADE,
  UNIQUE KEY uq_payment_orders_idem (idempotency_key),
  INDEX idx_payment_orders_user (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Documentos privados (recibos/fotos de despesas) para servir via /api/files
CREATE TABLE IF NOT EXISTS documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NULL,
  owner_id INT UNSIGNED NOT NULL,
  expense_id INT UNSIGNED NULL,
  kind ENUM('receipt','expense_photo','other') NOT NULL DEFAULT 'other',
  file_path VARCHAR(500) NOT NULL,
  original_name VARCHAR(255) DEFAULT NULL,
  mime VARCHAR(127) DEFAULT NULL,
  size INT UNSIGNED DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_documents_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_documents_expense FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE,
  CONSTRAINT fk_documents_owner FOREIGN KEY (owner_id) REFERENCES profiles(id) ON DELETE CASCADE,
  INDEX idx_documents_expense (expense_id),
  INDEX idx_documents_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Auditoria de acessos a documentos privados
CREATE TABLE IF NOT EXISTS document_downloads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_doc_downloads_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  INDEX idx_doc_downloads_document (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
