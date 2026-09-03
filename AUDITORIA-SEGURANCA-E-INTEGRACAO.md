# Auditoria estática de segurança e integração — Constrói Já

**Data:** 2 de setembro de 2026  
**Código analisado:** `OlharAngolano/Constroija`, commit `36c8b83`  
**Método:** análise estática e somente leitura do código, rotas, APIs, configuração, uploads e dicionário da base de dados  
**Produção alterada:** não

## 1. Limitações da auditoria

- Não houve acesso à base de dados de produção nem uso de credenciais.
- Não foram feitos pedidos contra `www.constroija.com` nem testes de exploração.
- O ambiente de análise não dispõe do executável PHP; por isso, o código ainda **não foi validado com `php -l` nem executado**.
- O PDF do phpMyAdmin é útil, mas não substitui um `SHOW CREATE TABLE` ou uma exportação atual somente da estrutura.
- As conclusões abaixo referem-se ao commit analisado; é necessário confirmar se ele corresponde exatamente ao código hoje publicado.

## 2. Conclusão executiva

O sistema existente é uma base adequada para a nova área de membros. Já possui PHP/PDO, sessão, CSRF, projetos múltiplos, equipa por obra, despesas, aportes, pré-orçamento, relatórios, perfis, feed, mensagens e administração. A opção de menor risco é **evoluir esta aplicação**, sem reescrever tudo e sem substituir a base atual.

No entanto, a publicação direta deste commit em produção não é recomendada antes da correção dos pontos críticos. Os mais urgentes são:

1. um simulador de pagamento permite a qualquer utilizador autenticado ativar a própria subscrição sem confirmação financeira externa;
2. recibos e outros uploads de aparência real estão no repositório público e no histórico Git;
3. o envio de mensagens possui uma falha de autorização por identificador de conversa;
4. tokens de recuperação são gravados nos logs;
5. documentos financeiros são servidos diretamente por URL pública;
6. migrações DDL são executadas durante pedidos web normais.

## 3. Controles positivos encontrados

- PDO com prepared statements nativos (`PDO::ATTR_EMULATE_PREPARES = false`).
- Uso consistente de parâmetros SQL nos módulos de projetos e finanças inspecionados; não foi confirmada SQL injection nesses fluxos.
- Hash de palavras-passe com Argon2id quando disponível e bcrypt como alternativa.
- Regeneração do identificador de sessão no login.
- Tokens CSRF nos principais pedidos de escrita.
- MIME real verificado com Fileinfo e nomes aleatórios nos uploads.
- Regras para impedir execução de scripts dentro de `uploads`.
- Verificação de `owner`/`manager` nas escritas de despesas, aportes e pré-orçamento.
- Despesas usam soft delete e as somas filtram `deleted_at IS NULL`.
- Links públicos usam tokens aleatórios e prazo de validade.
- Chaves únicas já protegem várias relações, como membros de conversas e gestores de projeto.

## 4. Achados prioritários

### CJ-01 — Crítico — Ativação gratuita de subscrição pelo simulador de pagamento

**Evidência:** `api/payments/callback.php` aceita uma sessão normal e CSRF, recebe `months`, `amount` e `plan_name` do navegador e muda diretamente o perfil para `active`. `pages/subscription.php` chama esse endpoint em `simulatePayment()`.

**Impacto:** qualquer membro autenticado pode conceder a si próprio acesso pago sem pagamento real. O valor recebido do cliente não é validado contra um pedido criado no servidor.

**Ação:** retirar a rota de produção imediatamente. Um webhook real deve validar assinatura do provedor, consultar um pedido criado no servidor, conferir moeda/valor/plano, usar identificador idempotente do evento e atualizar a subscrição numa transação. Webhooks não usam sessão nem CSRF; usam autenticação criptográfica do gateway.

### CJ-02 — Crítico — Dados e documentos de utilizadores publicados no Git

**Evidência:** o repositório público contém avatares, imagens de posts e projetos e recibos em `uploads/expenses/receipts`.

**Impacto:** exposição de dados pessoais/financeiros, permanência no histórico Git e possível incumprimento de deveres de privacidade.

**Ação imediata:** tornar o repositório privado enquanto se avaliam os ficheiros, remover `uploads` do controlo de versão, purgar os objetos do histórico com `git filter-repo` ou BFG, revogar URLs antigas quando possível e informar os titulares se a avaliação jurídica assim determinar. Fazer cópia dos uploads de produção antes de qualquer operação; **não apagar a pasta física do servidor de produção**.

### CJ-03 — Alto — IDOR no envio de mensagens

**Evidência:** em `api/messages/send.php`, quando `conversation_id > 0`, a consulta procura qualquer participante diferente do utilizador atual, mas não confirma que o utilizador atual pertence à conversa.

**Impacto:** um membro autenticado que descubra um ID pode inserir mensagens numa conversa de terceiros.

**Ação:** consultar a participação do remetente e o destinatário na mesma instrução, por exemplo com dois aliases e condição explícita `cp_me.user_id = ?`. Adicionar teste negativo para um utilizador externo à conversa.

### CJ-04 — Alto — Campos secretos do perfil podem chegar à resposta e à sessão

**Evidência:** o login usa `SELECT * FROM profiles` e remove apenas `password_hash`; `api/profile/update.php` volta a usar `SELECT *`, guarda o resultado integral na sessão e devolve-o no JSON. Campos como `remember_token`, `password_reset_token` e `email_verification_token` fazem parte da tabela.

**Impacto:** exposição de tokens de autenticação/recuperação no navegador e aumento do impacto de XSS, logs de frontend ou extensões maliciosas.

**Ação:** criar uma função `public_user_dto()` com lista positiva de campos; criar também uma lista mínima para sessão. Nunca devolver nem guardar na sessão hashes ou tokens de autenticação.

### CJ-05 — Alto — Tokens de recuperação gravados em logs

**Evidência:** `api/auth/forgot.php`, `api/auth/reset.php` e `pages/reset_password.php` escrevem o token completo em `error_log`.

**Impacto:** qualquer pessoa/processo com leitura dos logs pode redefinir palavras-passe enquanto o token for válido.

**Ação:** remover imediatamente esses logs; registar apenas um identificador de correlação não reversível. Após redefinição, invalidar também sessões e tokens “remember me”.

### CJ-06 — Alto — Validação TLS do SMTP desativada

**Evidência:** `includes/Mail.php` usa `verify_peer = false`, `verify_peer_name = false` e `allow_self_signed = true`.

**Impacto:** ataque man-in-the-middle pode capturar credenciais SMTP e conteúdo de e-mail.

**Ação:** ativar validação de certificado e nome; usar uma biblioteca SMTP mantida, como PHPMailer, com CA do sistema. Não contornar falhas TLS em produção.

### CJ-07 — Alto — Recibos e anexos financeiros são ficheiros públicos

**Evidência:** `UPLOAD_DIR` está sob o web root; `api/expenses/index.php` devolve URLs diretas; `pages/public_report.php` inclui links para recibos.

**Impacto:** quem obtiver a URL pode abrir o documento sem autenticação. Um link de relatório partilhado também pode expor comprovativos que deveriam permanecer privados.

**Ação:** guardar recibos fora do web root ou em armazenamento privado; disponibilizar `/api/files/{id}` com autorização por projeto, `Content-Disposition`, `nosniff` e auditoria. O relatório público deve omitir comprovativos por padrão e usar escopos explícitos.

### CJ-08 — Alto — Upload genérico aceita tipos inadequados em todos os contextos

**Evidência:** `Upload::file()` aceita imagens, PDF, CSV, XLS/XLSX e vídeo. O mesmo método é usado para avatar, capa de obra, produtos e recibos sem lista específica por contexto.

**Impacto:** um endpoint de avatar/capa pode armazenar documentos ou vídeos; há abuso de armazenamento e risco de distribuição de ficheiros maliciosos no mesmo domínio.

**Ação:** exigir listas MIME por contexto, limites distintos, limite de pixels para imagens, validação de dimensões e erro explícito. Harmonizar o limite PHP/Apache: o código anuncia 50 MB, mas `.htaccess` limita o pedido a 10 MB.

### CJ-09 — Alto — DDL e “auto-migrações” durante pedidos web

**Evidência:** `includes/helpers.php`, páginas de marketplace/vendor e respetivas APIs executam `ALTER TABLE` ou `CREATE TABLE IF NOT EXISTS` em pedidos normais.

**Impacto:** bloqueios, condições de corrida, privilégios excessivos do utilizador da aplicação e alterações silenciosas da produção sem controlo nem rollback.

**Ação:** remover todo DDL do runtime. Usar migrações versionadas, executadas por operador autorizado, com preflight, backup, verificação e registo em `schema_migrations`. O utilizador SQL da aplicação não deve ter `ALTER`, `CREATE` ou `DROP` em produção.

### CJ-10 — Alto — Entradas PHP diretas não passam pelo bootstrap principal

**Evidência:** `.htaccess` não reescreve ficheiros reais. Assim, `/api/...php` e `/pages/...php` podem ser executados diretamente, enquanto `Security::init()` só é chamado por `index.php`.

**Impacto:** caminhos diretos podem perder headers, configuração de sessão e WAF, ou gerar erros fatais com divulgação de caminhos quando o ambiente está em desenvolvimento.

**Ação:** usar um único front controller e um document root `public/`; bloquear acesso HTTP direto a `config`, `includes`, `templates`, `pages` e scripts internos. Todos os endpoints devem carregar o mesmo bootstrap.

### CJ-11 — Alto — Freemium confundido com suspensão de conta

**Evidência:** `index.php`, `includes/auth.php` e `require_auth()` mudam o perfil para `suspended` quando termina o trial/subscrição e impedem a maior parte da plataforma.

**Impacto:** não existe membro gratuito persistente. O comportamento é incompatível com o modelo freemium solicitado e mistura moderação de conta com faturação.

**Ação:** reservar `profiles.status` para segurança/moderação da conta. Implementar planos, subscrições e direitos de acesso em tabelas próprias. Quando o Premium expirar, o membro deve voltar ao plano gratuito, não ficar suspenso.

### CJ-12 — Alto funcional — CSP bloqueia ações inline

**Evidência:** a CSP não permite `unsafe-inline` em scripts, mas foram contados 129 atributos `onclick`, `onchange`, `onsubmit` etc. nas páginas/templates.

**Impacto:** browsers que aplicam a CSP bloqueiam várias ações de editar, apagar, abrir modal, sair e submeter formulários.

**Ação:** remover handlers inline e ligar eventos por `addEventListener` em ficheiros JS ou blocos com nonce. Não resolver adicionando `unsafe-inline`.

### CJ-13 — Alto — Eliminação de obra é destrutiva e não transacional

**Evidência:** `api/projects/delete.php` apaga primeiro a capa física e depois executa `DELETE FROM projects`, confiando em cascata.

**Impacto:** uma falha SQL pode deixar o registo sem ficheiro; uma ação acidental elimina toda a árvore da obra.

**Ação:** para a nova interface, preferir arquivo/soft delete com confirmação reforçada. Se a eliminação definitiva continuar, usar transação, fila de limpeza de ficheiros após commit, auditoria e política de retenção/restauro.

### CJ-14 — Médio — Exposição de mensagens internas de base de dados

**Evidência:** vários endpoints concatenam `$e->getMessage()` em respostas, e páginas usam `die()` com a mensagem da exceção. `APP_ENV` tem padrão `development` e `APP_DEBUG` padrão `true`.

**Impacto:** divulgação de nomes de tabelas/colunas, caminhos e detalhes internos.

**Ação:** produção deve falhar se `APP_ENV` não estiver configurado, nunca usar debug por padrão e devolver um ID de erro genérico ao cliente; o detalhe fica apenas no log protegido.

### CJ-15 — Médio — Primeiro registo recebe administração automaticamente

**Evidência:** `register_user()` torna administrador o primeiro perfil encontrado como inexistente.

**Impacto:** numa base vazia, restaurada incorretamente ou durante corrida de instalação, um visitante pode obter administração.

**Ação:** remover do registo público. Criar administradores apenas por comando operacional autenticado ou migração controlada.

### CJ-16 — Médio — Token “remember me” em texto simples e sem validade própria

**Evidência:** um único token é guardado diretamente em `profiles.remember_token`; não existe seletor/validador, expiração ou rotação por dispositivo.

**Impacto:** fuga da base concede login persistente; o token pode permanecer válido por tempo indeterminado.

**Ação:** tabela de tokens por dispositivo com seletor público, hash do validador, expiração, rotação e revogação. Nunca devolver o token no perfil JSON.

### CJ-17 — Médio — Relatório/QR divulgava token a um terceiro

**Estado local em 02/09/2026:** mitigado, pendente de validação runtime no alojamento.

**Evidência original:** `pages/projects/signboard.php` enviava a URL completa do relatório, incluindo token, para `api.qrserver.com`.

**Impacto:** o segredo do relatório podia aparecer nos logs de um serviço externo.

**Correção implementada:** a placa gera o QR no navegador com `assets/js/qrcode.bundle.js`, servido pela mesma origem. O link/token não é enviado ao fornecedor anterior. Os avisos MIT do bundle e da dependência incorporada acompanham a release. A expiração e as permissões dos links públicos existentes continuam preservadas.

### CJ-18 — Médio — Problemas de integridade e rotas auxiliares

- `api/pre_budget/import.php` tenta iniciar uma nova transação dentro do `catch` para decidir se faz rollback; deve usar `PDO::inTransaction()`.
- A importação CSV não possui limite claro de linhas/tamanho no código.
- `pages/projects/signboard.php` consulta `profiles.phone`, coluna ausente no dicionário recebido.
- `signboard.php` e `pending_report.php` não estão mapeados no router principal.
- `/api/admin/stats` usa middleware que exige CSRF mesmo em GET, mas o helper JS só envia CSRF em métodos de escrita.
- O relatório público mostra recibos; o acesso deve ser separado do resumo financeiro.

## 5. Correções de exatidão financeira

A integração deve manter conceitos diferentes, sem os confundir:

- **Aportes:** `SUM(project_funds.amount * exchange_rate)`.
- **Gastos:** `SUM(expenses.price * expenses.quantity)` com `deleted_at IS NULL`.
- **Saldo de caixa:** aportes menos gastos.
- **Orçamento disponível:** orçamento aprovado menos gastos.
- **Orçamento utilizado:** gastos dividido pelo orçamento aprovado.

O código atual usa `max(0, ...)` e, em alguns locais, limita a percentagem a 100%. Isso esconde défices e derrapagens. A barra visual pode ficar limitada a 100%, mas o número e o alerta devem mostrar o valor real, inclusive saldo negativo e utilização superior a 100%.

## 6. Compatibilidade da base de dados

O dicionário confirma que a estrutura atual já suporta várias obras por utilizador e finanças independentes. Não é necessário substituir `projects`, `expenses`, `project_funds`, `pre_budgets`, `project_managers` ou `public_links`.

Pontos que exigem extensão aditiva:

- `projects.phases` contém apenas nomes em JSON. Progresso, ordem, estado e datas devem ir para uma tabela `project_phases`, mantendo o JSON durante a transição.
- A Academia ainda não possui tabelas.
- Planos/subscrições/pagamentos precisam de modelo próprio para freemium e idempotência.
- Documentos privados precisam de metadados e endpoint autorizado.

A exportação de instalação com `DROP TABLE` **não é uma migração** e nunca deve ser executada em produção.

## 7. Ordem obrigatória antes da integração visual

1. Conter a exposição do repositório e desativar o simulador de pagamento.
2. Confirmar o commit implantado e obter estrutura atual da base, sem dados.
3. Fazer backup integral da base e dos uploads e testar a restauração.
4. Criar staging isolado com cópia anonimizada ou controlada.
5. Corrigir autorização de mensagens, tokens, SMTP, perfil DTO, uploads privados e bootstrap único.
6. Retirar DDL do runtime e criar mecanismo de migrações.
7. Aplicar o novo shell visual e o adaptador das APIs existentes.
8. Adicionar `project_phases` e, só depois de validar a estrutura, as tabelas de Academia e subscrições.
9. Executar testes de regressão, autorização, migração, dispositivos móveis e restauração.
10. Lançar com janela controlada, métricas, logs e plano de rollback.

## 8. Avaliação do `schema.sql` publicado em 2 de setembro de 2026

O ficheiro posteriormente publicado no repositório **não é a exportação segura solicitada**. A análise automática confirmou:

- 17 instruções `DROP TABLE`;
- `FOREIGN_KEY_CHECKS = 0`;
- uma instrução `INSERT INTO` para uma conta administrativa;
- uma palavra-passe inicial escrita em comentário;
- apenas 17 tabelas de instalação.

O ficheiro diverge do dicionário da base atual: omite, entre outros elementos, colunas já existentes em `posts` e `profiles`, além de estruturas de parceiros/marketplace criadas pelo código. Portanto, ele é um instalador destrutivo e desatualizado, não um retrato da produção.

**Não executar esse ficheiro.** Remover a credencial inicial, rodar/invalidar qualquer conta que a tenha usado e retirar o seed do histórico público. A exportação necessária continua sendo a gerada diretamente da base atual, somente com estrutura, sem `DROP` e sem `INSERT`.

## 9. Estado final desta auditoria

A auditoria estática inicial está concluída. Nenhum dado de produção foi alterado e nenhum segredo foi solicitado. A etapa seguinte depende de uma exportação **somente da estrutura atual** e da confirmação de staging/backup.