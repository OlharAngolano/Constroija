<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método não permitido.', 405);
}

if (!validate_csrf()) {
    json_error('Token CSRF inválido ou expirado.', 403);
}

$user = current_user();
if (!$user) {
    json_error('Não autorizado.', 401);
}

$projectId = (int)input('project_id', 0);
if ($projectId <= 0) {
    json_error('ID de projeto inválido.');
}

$db = db();

try {
    // 1. Validar se o utilizador é gestor ou dono do projeto
    $manager = $db->fetch(
        "SELECT role FROM project_managers WHERE project_id = ? AND user_id = ?",
        [$projectId, $user['id']]
    );

    if (!$manager || !in_array($manager['role'], ['owner', 'manager'])) {
        json_error('Permissão negada. Apenas gestores do projeto podem importar planeamento.', 403);
    }

    // 2. Validar upload do ficheiro
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_error('Selecione um ficheiro CSV ou Excel válido para carregar.');
    }

    $fileTmpPath = $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'];
    $fileSize = $_FILES['file']['size'];
    
    // Validar tipo de ficheiro básico
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt'])) {
        json_error('Formato de ficheiro não suportado. Por favor, envie uma folha de cálculo em formato CSV (.csv).');
    }

    // 3. Processar e analisar o CSV
    $handle = fopen($fileTmpPath, 'r');
    if ($handle === false) {
        json_error('Erro ao abrir o ficheiro carregado no servidor.');
    }

    // Ler a primeira linha (cabeçalho) para detetar o delimitador
    $firstLine = fgets($handle);
    rewind($handle); // Reposicionar no início

    // Detetar se usa ponto e vírgula (padrão Excel europeu/português) ou vírgula (padrão americano)
    $delimiter = ';';
    if (strpos($firstLine, ',') !== false && (strpos($firstLine, ';') === false || count(explode(',', $firstLine)) > count(explode(';', $firstLine)))) {
        $delimiter = ',';
    }

    // Ler o cabeçalho real
    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) {
        fclose($handle);
        json_error('O ficheiro está vazio ou é inválido.');
    }

    // Mapear colunas de forma inteligente e insensível a acentuação/case
    $mappedColumns = [];
    foreach ($headers as $idx => $h) {
        // Remover acentuação, caracteres especiais, converter para minúsculas
        $norm = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $h))));
        $mappedColumns[$norm] = $idx;
    }

    // Identificar colunas suportadas através de sinónimos comuns de Excel
    $colItem = null;
    foreach (['item', 'nome', 'material', 'servico', 'descricao', 'artigo'] as $alias) {
        if (isset($mappedColumns[$alias])) { $colItem = $mappedColumns[$alias]; break; }
    }

    $colPrice = null;
    foreach (['preco', 'precounitario', 'unitario', 'valor', 'precoestimado', 'custounitario'] as $alias) {
        if (isset($mappedColumns[$alias])) { $colPrice = $mappedColumns[$alias]; break; }
    }

    $colQty = null;
    foreach (['quantidade', 'quant', 'qtd', 'quantidadeestimada'] as $alias) {
        if (isset($mappedColumns[$alias])) { $colQty = $mappedColumns[$alias]; break; }
    }

    $colUnit = null;
    foreach (['unidade', 'unid', 'un', 'medida'] as $alias) {
        if (isset($mappedColumns[$alias])) { $colUnit = $mappedColumns[$alias]; break; }
    }

    $colPhase = null;
    foreach (['fase', 'faseobra', 'etapa', 'fasedaobra'] as $alias) {
        if (isset($mappedColumns[$alias])) { $colPhase = $mappedColumns[$alias]; break; }
    }

    // Validar se as colunas mínimas foram identificadas
    if ($colItem === null || $colPrice === null || $colPhase === null) {
        fclose($handle);
        json_error('Estrutura de colunas inválida. Certifique-se de que a primeira linha contém colunas identificadas como: "Item" (Nome), "Preço" (Preço Unitário) e "Fase" (Fase da obra).');
    }

    // 4. Iniciar transação na BD para eficiência e segurança
    $db->beginTransaction();
    $insertedCount = 0;

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        // Ignorar linhas vazias ou incompletas
        if (empty($row) || count($row) <= max($colItem, $colPrice, $colPhase) || empty(trim($row[$colItem]))) {
            continue;
        }

        $name = trim($row[$colItem]);
        
        // Tratar números decimais europeus (ex: 6.500,00 ou 5,5)
        $rawPrice = trim($row[$colPrice]);
        $rawPrice = str_replace('.', '', $rawPrice); // Remover separador de milhares
        $rawPrice = str_replace(',', '.', $rawPrice); // Converter vírgula decimal em ponto
        $price = (float)$rawPrice;
        if ($price < 0.00) $price = 0.00;

        $quantity = 1.00;
        if ($colQty !== null && isset($row[$colQty])) {
            $rawQty = trim($row[$colQty]);
            $rawQty = str_replace('.', '', $rawQty);
            $rawQty = str_replace(',', '.', $rawQty);
            $quantity = (float)$rawQty;
        }
        if ($quantity <= 0.00) $quantity = 1.00;

        $unit = 'un';
        if ($colUnit !== null && isset($row[$colUnit])) {
            $unit = trim($row[$colUnit]) ?: 'un';
        }

        $phase = trim($row[$colPhase]);
        if ($phase === '') $phase = 'Fundação'; // Fase padrão de fallback

        // Inserir item na BD
        $db->execute(
            "INSERT INTO pre_budgets (project_id, user_id, name, price, quantity, unit, phase) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$projectId, $user['id'], $name, $price, $quantity, $unit, $phase]
        );
        
        $insertedCount++;
    }

    fclose($handle);
    $db->commit();

    set_flash_message('success', "Importação de planeamento bem-sucedida! Adicionados {$insertedCount} itens de pré-orçamento.");
    json_ok(['count' => $insertedCount], "Importados {$insertedCount} itens com sucesso!");

} catch (Exception $e) {
    if (isset($db) && $db->beginTransaction()) {
        $db->rollBack();
    }
    json_error('Erro técnico ao importar o ficheiro CSV no servidor: ' . $e->getMessage(), 500);
}
