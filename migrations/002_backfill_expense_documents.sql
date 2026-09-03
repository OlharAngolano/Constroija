-- 002 — Indexar recibos/fotos de despesas existentes na tabela documents
-- para que passem a ser servidos apenas via /api/files (CJ-07).
-- Depois de aplicar, mova fisicamente os ficheiros antigos de
-- uploads/expenses/ para storage/private/expenses/ e atualize file_path
-- com o comando SQL fornecido em bin/README (ou repita os UPDATEs abaixo
-- com o novo prefixo).

INSERT IGNORE INTO documents (project_id, owner_id, expense_id, kind, file_path, original_name, mime, size, created_at)
SELECT e.project_id, e.user_id, e.id, 'receipt', e.receipt_url,
       SUBSTRING_INDEX(e.receipt_url, '/', -1), 'application/octet-stream', 0, e.created_at
FROM expenses e
WHERE e.receipt_url IS NOT NULL AND e.receipt_url <> ''
  AND NOT EXISTS (SELECT 1 FROM documents d WHERE d.expense_id = e.id AND d.kind = 'receipt');

INSERT IGNORE INTO documents (project_id, owner_id, expense_id, kind, file_path, original_name, mime, size, created_at)
SELECT e.project_id, e.user_id, e.id, 'expense_photo', e.photo_url,
       SUBSTRING_INDEX(e.photo_url, '/', -1), 'application/octet-stream', 0, e.created_at
FROM expenses e
WHERE e.photo_url IS NOT NULL AND e.photo_url <> ''
  AND NOT EXISTS (SELECT 1 FROM documents d WHERE d.expense_id = e.id AND d.kind = 'expense_photo');
