<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

if (!validate_csrf()) {
    json_error('Token CSRF inválido.', 403);
}

$name = trim((string)input('name', ''));
$username = trim((string)input('username', ''));
$bio = trim((string)input('bio', ''));
$location = trim((string)input('location', ''));
$whatsapp = trim((string)input('whatsapp', ''));
$website = trim((string)input('website', ''));
$language = trim((string)input('language', 'pt'));
$currency = trim((string)input('currency', 'AOA'));

// Novos campos de portfólio (JSON)
$portfolioTitle = trim((string)input('portfolio_title', ''));
$portfolioDesc = trim((string)input('portfolio_description', ''));
$portfolioExperience = trim((string)input('portfolio_experience', ''));
$portfolioSkills = input('portfolio_skills', []);

if ($name === '' || $username === '') {
    json_error('O nome e o username são campos obrigatórios.');
}

// Validar formato de username
if (!preg_match('/^[a-zA-Z0-9_\.]{3,30}$/', $username)) {
    json_error('O username deve ter entre 3 e 30 caracteres e conter apenas letras, números, underscores (_) ou pontos (.).');
}

$db = db();

try {
    // Verificar se o username já está em uso por outro utilizador
    $existing = $db->fetch(
        "SELECT id FROM profiles WHERE username = ? AND id != ?",
        [$username, $user['id']]
    );
    if ($existing) {
        json_error('Este username já está em uso. Escolha outro.');
    }

    // Tratar as competências/skills
    $skills = [];
    if (is_array($portfolioSkills)) {
        foreach ($portfolioSkills as $s) {
            $skills[] = sanitize(trim((string)$s));
        }
    } elseif (is_string($portfolioSkills)) {
        $parts = explode(',', $portfolioSkills);
        foreach ($parts as $p) {
            $trimmed = trim($p);
            if ($trimmed !== '') {
                $skills[] = sanitize($trimmed);
            }
        }
    }

    // Construir o JSON de portfólio
    $portfolioObj = [
        'title' => $portfolioTitle,
        'description' => $portfolioDesc,
        'experience' => $portfolioExperience,
        'skills' => $skills
    ];
    $portfolioJson = json_encode($portfolioObj);

    // Atualizar base de dados
    $db->query(
        "UPDATE profiles 
         SET name = ?, username = ?, bio = ?, location = ?, whatsapp = ?, website = ?, language = ?, currency = ?, portfolio_data = ?
         WHERE id = ?",
        [$name, $username, $bio, $location, $whatsapp, $website, $language, $currency, $portfolioJson, $user['id']]
    );

    // Atualizar dados da sessão — apenas campos seguros (CJ-04)
    $updatedUser = $db->fetch("SELECT * FROM profiles WHERE id = ?", [$user['id']]);
    $_SESSION['user'] = user_session_dto($updatedUser);

    json_ok(['user' => user_public_dto($updatedUser)], 'Perfil atualizado com sucesso!');
} catch (PDOException $e) {
    json_internal_error('atualizar perfil', $e);
}
