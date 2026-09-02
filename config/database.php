<?php
declare(strict_types=1);

class Database {
    private static ?Database $instance = null;
    private ?PDO $pdo = null;

    /**
     * Construtor privado (Singleton)
     */
    private function __construct() {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Utilizar prepared statements nativos
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Sincronizar o fuso horário da sessão MySQL com o do PHP
            $timezoneOffset = (new DateTime())->format('P');
            $this->pdo->exec("SET time_zone = '{$timezoneOffset}'");
        } catch (PDOException $e) {
            // Em desenvolvimento expõe o erro; em produção faz log e mostra mensagem genérica
            if (APP_ENV === 'development') {
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            } else {
                error_log("Database connection failure: " . $e->getMessage());
                
                // Se for um request API, envia JSON
                if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
                    header('Content-Type: application/json; charset=utf-8');
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Falha técnica temporária na ligação ao servidor.',
                        'code' => 500
                    ]);
                    exit;
                } else {
                    http_response_code(500);
                    echo "<div style='font-family:sans-serif; text-align:center; padding: 50px; background:#0a0f1e; color:#f1f5f9; height: 100vh;'>
                            <h1 style='color:#f97316;'>Constrói Já</h1>
                            <p>Ocorreu uma falha técnica ao ligar à Base de Dados. Por favor, tente mais tarde.</p>
                          </div>";
                    exit;
                }
            }
        }
    }

    /**
     * Obter a instância única da base de dados
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obter o objeto PDO nativo se for necessário
     */
    public function getConnection(): PDO {
        return $this->pdo;
    }

    /**
     * Executa uma query preparada e retorna o Statement
     */
    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Executa uma query preparada e retorna uma única linha
     */
    public function fetch(string $sql, array $params = []): ?array {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Executa uma query preparada e retorna todas as linhas correspondentes
     */
    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Executa uma operação INSERT/UPDATE/DELETE e retorna true se afetou linhas
     */
    public function execute(string $sql, array $params = []): bool {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Retorna o último ID inserido na BD
     */
    public function lastInsertId(): string {
        return $this->pdo->lastInsertId();
    }

    /**
     * Métodos de transação simples
     */
    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool {
        return $this->pdo->commit();
    }

    public function rollBack(): bool {
        return $this->pdo->rollBack();
    }
}
