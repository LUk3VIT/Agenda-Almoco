<?php

namespace App\Config;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            try {
                $driver = Env::get('DB_DRIVER', 'pgsql');
                $host = Env::get('DB_HOST', 'db');
                $port = Env::get('DB_PORT', '5432');
                $dbname = Env::get('DB_NAME', 'braseqWeb');
                $user = Env::get('DB_USER', 'postgres');
                $password = Env::get('DB_PASSWORD', '');

                if ($driver === 'pgsql') {
                    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
                } else {
                    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                }

                self::$instance = new PDO(
                    $dsn,
                    $user,
                    $password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]
                );
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "status" => "erro",
                    "mensagem" => "Falha de conexão com o banco de dados. Tente novamente mais tarde."
                ]);
                exit;
            }
        }
        return self::$instance;
    }

    public static function resetConnection(): void {
        self::$instance = null;
    }
}