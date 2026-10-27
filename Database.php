<?php
declare(strict_types=1);

/**
 * Database Connection Helper
 *
 * Configured as requested:
 * Host: localhost
 * Port: 3306
 * Username: root
 * Password:  
 * DBName: embeddings
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function getConfig(): array
    {
        return [
            "Host" => "localhost",
            "Port" => "3306",
            "Username" => "root",
            "Password" => "",
            "DBName" => "embeddings"
        ];
    }

    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            $config = self::getConfig();
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
                $config["Host"],
                $config["Port"],
                $config["DBName"]
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$pdo = new PDO($dsn, $config["Username"], $config["Password"], $options);
            } catch (PDOException $e) {
                // If database doesn't exist yet, attempt to create it
                if ($e->getCode() == 1049) {
                    $rootDsn = sprintf("mysql:host=%s;port=%s;charset=utf8mb4", $config["Host"], $config["Port"]);
                    $tmpPdo = new PDO($rootDsn, $config["Username"], $config["Password"], $options);
                    $tmpPdo->exec("CREATE DATABASE IF NOT EXISTS `" . $config["DBName"] . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    self::$pdo = new PDO($dsn, $config["Username"], $config["Password"], $options);
                } else {
                    throw $e;
                }
            }

            self::initializeSchema(self::$pdo);
        }

        return self::$pdo;
    }

    /**
     * Ensures table `rag_document` exists with required fields:
     * - rag_id (Primary key)
     * - context (Text content)
     * - embeddings (Longtext JSON array representing 1024-dimensional float vector)
     */
    public static function initializeSchema(PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `rag_document` (
            `rag_id` INT AUTO_INCREMENT PRIMARY KEY,
            `context` TEXT NOT NULL,
            `embeddings` LONGTEXT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $pdo->exec($sql);
    }
}
