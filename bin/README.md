# bin/ — Ferramentas de operação (CLI)

> **Importante (CJ-09):** este projeto **nunca** executa `CREATE TABLE`/`ALTER TABLE`
> a partir de pedidos web. Toda a evolução do esquema é feita por migrações
> versionadas, aplicadas por um operador via CLI.

## Aplicar as migrações

```bash
# 1. Estado atual (mostra as migrações aplicadas e pendentes)
php bin/migrate.php --status

# 2. Simular o que seria aplicado (sem tocar na base de dados)
php bin/migrate.php --dry-run

# 3. Aplicar as migrações pendentes, por ordem numérica (001, 002, 003, 004…)
php bin/migrate.php
```

Cada migração corre dentro de uma transação e fica registada na tabela
`schema_migrations` com a soma de verificação sha256 do ficheiro — se um
ficheiro já aplicado for alterado, o runner recusa aplicá-lo novamente.

## Ordem e conteúdo das migrações

| Ficheiro | Conteúdo |
| --- | --- |
| `001_security_tables.sql` | Tabelas de segurança: `auth_tokens` (sessões "remember me" por dispositivo), `payment_orders` (pedidos de pagamento CJ-01), `documents` + `document_downloads` (documentos privados CJ-07) |
| `002_backfill_expense_documents.sql` | Indexa recibos/fotos de despesas **já existentes** na tabela `documents` (ver procedimento físico abaixo) |
| `003_legacy_columns.php` | Colunas que antigamente eram criadas em runtime (CJ-09): `last_activity_at`, `is_reel`, `video_qualities`, `partners.whatsapp`, `vendor_store_id`, `stock_status`, `discount_pct`, `coupon` — adicionadas só se não existirem |
| `004_marketplace_vendor_tables.sql` | Tabelas de parceiros/marketplace (`partners`, `vendor_stores`, `marketplace_products`) + conteúdo inicial de parceiros apenas se a tabela estiver vazia + preenchimento do `whatsapp` genérico em linhas legadas |

## 002 — Procedimento físico para recibos/despesas antigas (CJ-07)

O `002_backfill_expense_documents.sql` cria as linhas em `documents` para as
despesas que já tinham `receipt_url`/`photo_url` **antes** desta correção.
Esses ficheiros ainda vivem na pasta pública `uploads/expenses/…`.

Para os retirar do alcance público:

1. Antes de mais, aplique as migrações 001 e 002:
   ```bash
   php bin/migrate.php
   ```

2. Crie a pasta privada (se ainda não existir) e mova os ficheiros legados:
   ```bash
   # No diretório raiz da aplicação
   mkdir -p storage/private/expenses/receipts storage/private/expenses/photos
   mv uploads/expenses/receipts/* storage/private/expenses/receipts/ 2>/dev/null || true
   mv uploads/expenses/photos/*   storage/private/expenses/photos/   2>/dev/null || true
   ```

3. Atualize os caminhos nas duas tabelas (o ficheiro físico mudou de sítio):
   ```sql
   -- execute com o seu cliente mysql/phpMyAdmin
   UPDATE documents d
   JOIN expenses e ON e.id = d.entity_id AND d.kind IN ('receipt', 'expense_photo')
   SET d.file_path = REPLACE(d.file_path, 'uploads/expenses/', 'storage/private/expenses/')
   WHERE d.file_path LIKE 'uploads/expenses/%';

   UPDATE expenses e
   JOIN documents d ON d.entity_id = e.id AND d.kind IN ('receipt', 'expense_photo')
   SET e.receipt_url = d.file_path
   WHERE d.kind = 'receipt' AND e.receipt_url LIKE 'uploads/expenses/%';

   UPDATE expenses e
   JOIN documents d ON d.entity_id = e.id AND d.kind IN ('receipt', 'expense_photo')
   SET e.photo_url = d.file_path
   WHERE d.kind = 'expense_photo' AND e.photo_url LIKE 'uploads/expenses/%';
   ```

4. Valide:
   ```bash
   php bin/migrate.php --status
   # e confirme na app que o anexo abre em /api/files?id=… (autenticado)
   ```

> O diretório `storage/` fica fora do controlo de versão (`.gitignore`) e é
> bloqueado por `.htaccess` na raiz (`RedirectMatch 403 ^/storage`). Os novos
> recibos/fotos carregados pela app vão diretamente para `storage/private/`
> e são servidos **apenas** via `/api/files` com autorização por projeto.

## Criar um administrador (CJ-15)

Desde a correção CJ-15, o registo público **nunca** cria administradores
(o primeiro utilizador registado deixou de ser promovido automaticamente).
Para promover um utilizador existente a administrador, use o MySQL:

```sql
UPDATE profiles SET is_admin = 1 WHERE email = 'email-do-admin@exemplo.ao';
```

…ou um script equivalente executado por si — nunca através de um pedido web
não autenticado.

## Notas

- `schema.sql` é um instalador de estrutura **idempotente** (desde a revisão
  CJ-08/§8): usa `CREATE TABLE IF NOT EXISTS`, sem `DROP TABLE`, sem contas
  nem palavras-passe. Serve apenas para bases novas; bases existentes evoluem
  exclusivamente por migrações.
- As credenciais de produção vivem **apenas** em `.env` (fora do Git desde a
  correção CJ-02). Rode `cp .env.example .env` para criar o seu ficheiro local.
