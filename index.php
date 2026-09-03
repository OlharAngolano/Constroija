<?php
declare(strict_types=1);

// 1. Inicializar configurações globais e base de dados.
//    (config.php já arranca o Security::init — sessão segura, headers, WAF e
//    gestão de erros — para que qualquer entrada tenha o mesmo bootstrap, CJ-10)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

// 2. Verificar se existe cookie Remember Me ativo para restabelecer sessão
check_remember_me();

// 4. Capturar a URI amigável atual
$uri = current_uri();

// Limpeza de barras finais
$uri = rtrim($uri, '/');
if ($uri === '') {
    $uri = '/';
}

// Normalizar URI para retrocompatibilidade com links antigos (.php, underscores)
$uriClean = str_replace('.php', '', $uri);
$uriClean = str_replace('_', '-', $uriClean);

// 5. Encaminhar para Rota de API (retornos obrigatoriamente em JSON)
if (strpos($uriClean, '/api') === 0) {
    $apiRoutes = [
        '/api/auth/register'         => '/api/auth/register.php',
        '/api/auth/login'            => '/api/auth/login.php',
        '/api/auth/logout'           => '/api/auth/logout.php',
        '/api/auth/forgot'           => '/api/auth/forgot.php',
        '/api/auth/reset'            => '/api/auth/reset.php',
        '/api/posts'                 => '/api/posts/index.php',
        '/api/posts/create'          => '/api/posts/create.php',
        '/api/posts/like'            => '/api/posts/like.php',
        '/api/posts/comment'         => '/api/posts/comment.php',
        '/api/posts/delete'          => '/api/posts/delete.php',
        '/api/posts/edit'            => '/api/posts/edit.php',
        '/api/posts/detail'          => '/api/posts/detail.php',
        '/api/projects'              => '/api/projects/index.php',
        '/api/projects/create'       => '/api/projects/create.php',
        '/api/projects/update'       => '/api/projects/update.php',
        '/api/projects/delete'       => '/api/projects/delete.php',
        '/api/projects/add-collaborator'=> '/api/projects/add_collaborator.php',
        '/api/projects/remove-collaborator'=> '/api/projects/remove_collaborator.php',
        '/api/expenses'              => '/api/expenses/index.php',
        '/api/expenses/create'       => '/api/expenses/create.php',
        '/api/expenses/update'       => '/api/expenses/update.php',
        '/api/expenses/delete'       => '/api/expenses/delete.php',
        '/api/funds/add'             => '/api/funds/add.php',
        '/api/funds/update'          => '/api/funds/update.php',
        '/api/funds/delete'          => '/api/funds/delete.php',
        '/api/pre-budget/add'        => '/api/pre_budget/add.php',
        '/api/pre-budget/update'     => '/api/pre_budget/update.php',
        '/api/pre-budget/delete'     => '/api/pre_budget/delete.php',
        '/api/pre-budget/import'     => '/api/pre_budget/import.php',
        '/api/marketplace/products/create' => '/api/marketplace/create_product.php',
        '/api/marketplace/products/delete' => '/api/marketplace/delete_product.php',
        '/api/vendor/store/save'     => '/api/vendor/store_save.php',
        '/api/vendor/product/save'   => '/api/vendor/product_save.php',
        '/api/vendor/product/delete' => '/api/vendor/product_delete.php',
        '/api/messages/conversations'=> '/api/messages/conversations.php',
        '/api/messages/messages'     => '/api/messages/messages.php',
        '/api/messages/send'         => '/api/messages/send.php',
        '/api/followers/follow'      => '/api/followers/follow.php',
        '/api/questions'             => '/api/questions/index.php',
        '/api/questions/create'      => '/api/questions/create.php',
        '/api/questions/answer'      => '/api/questions/answer.php',
        '/api/notifications'         => '/api/notifications/index.php',
        '/api/notifications/read'    => '/api/notifications/read.php',
        '/api/profile/update'        => '/api/profile/update.php',
        '/api/profile/avatar'        => '/api/profile/avatar.php',
        '/api/search'                => '/api/search.php',
        '/api/public-links/create'   => '/api/public_links/create.php',
        '/api/admin/stats'           => '/api/admin/stats.php',
        '/api/admin/toggle-user'     => '/api/admin/toggle_user.php',
        '/api/admin/partners/create' => '/api/admin/partners/create.php',
        '/api/admin/partners/update' => '/api/admin/partners/update.php',
        '/api/admin/partners/delete' => '/api/admin/partners/delete.php',
        '/api/payments/create-order' => '/api/payments/create_order.php',
        '/api/payments/webhook'      => '/api/payments/webhook.php',
        '/api/admin/payments/list'   => '/api/admin/payments/list.php',
        '/api/admin/payments/confirm'=> '/api/admin/payments/confirm.php',
        '/api/files'                 => '/api/files.php',
    ];

    if (isset($apiRoutes[$uriClean])) {
        $file = __DIR__ . $apiRoutes[$uriClean];
        if (file_exists($file)) {
            require $file;
            exit;
        }
    }
    json_error('API Endpoint não encontrado.', 404);
}

// 6. Encaminhar para Rota de Páginas HTML
$pagesRoutes = [
    '/'                      => '/pages/landing.php',
    '/login'                 => '/pages/login.php',
    '/register'              => '/pages/register.php',
    '/forgot-password'       => '/pages/forgot_password.php',
    '/reset-password'        => '/pages/reset_password.php',
    '/feed'                  => '/pages/feed.php',
    '/search'                => '/pages/search.php',
    '/messages'              => '/pages/messages.php',
    '/notifications'         => '/pages/notifications.php',
    '/portfolio-settings'    => '/pages/portfolio_settings.php',
    '/projects'              => '/pages/projects/index.php',
    '/projects/create'       => '/pages/projects/create.php',
    '/projects/detail'       => '/pages/projects/detail.php',
    '/projects/financials'   => '/pages/projects/financials.php',
    '/projects/signboard'    => '/pages/projects/signboard.php',
    '/projects/pending-report' => '/pages/projects/pending_report.php',
    '/profile/settings'      => '/pages/profile/settings.php',
    '/subscription'          => '/pages/subscription.php',
    '/marketplace'           => '/pages/marketplace.php',
    '/vendor/store'          => '/pages/vendor/store.php',
    '/qna'                   => '/pages/qna/index.php',
    '/qna/detail'            => '/pages/qna/detail.php',
    '/admin'                 => '/pages/admin/dashboard.php',
    '/admin/users'           => '/pages/admin/users.php',
    '/admin/moderation'      => '/pages/admin/moderation.php',
    '/admin/partners'        => '/pages/admin/partners.php',
    '/report'                => '/pages/public_report.php',
    '/suspended'             => '/pages/suspended.php',
];

// Se for correspondência direta
if (isset($pagesRoutes[$uriClean])) {
    $file = __DIR__ . $pagesRoutes[$uriClean];
    if (file_exists($file)) {
        require $file;
        exit;
    }
}

// 7. Tratamento de Rotas Dinâmicas amigáveis (ex: /profile/username e /portfolio/username)

// 7a. Perfil público (/profile/username)
if (preg_match('/^\/profile\/([a-zA-Z0-9_\.]{3,30})$/', $uri, $matches)) {
    $_GET['username'] = $matches[1];
    $file = __DIR__ . '/pages/profile/view.php';
    if (file_exists($file)) {
        require $file;
        exit;
    }
}

// 7b. Portfólio público (/portfolio/username)
if (preg_match('/^\/portfolio\/([a-zA-Z0-9_\.]{3,30})$/', $uri, $matches)) {
    $_GET['username'] = $matches[1];
    $file = __DIR__ . '/pages/profile/portfolio.php';
    if (file_exists($file)) {
        require $file;
        exit;
    }
}

// 8. Rota Fallback (Página 404)
http_response_code(404);
$file404 = __DIR__ . '/pages/404.php';
if (file_exists($file404)) {
    require $file404;
} else {
    echo "<div style='font-family:sans-serif; text-align:center; padding: 50px; background:#0a0f1e; color:#f1f5f9; height:100vh;'>
            <h1 style='color:#ef4444;'>Erro 404</h1>
            <p>Página não encontrada no Constrói Já.</p>
            <a href='/' style='color:#f97316;'>Voltar ao Início</a>
          </div>";
}
exit;
