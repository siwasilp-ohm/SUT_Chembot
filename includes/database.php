<?php
/**
 * Database Connection & Query Handler
 * Fixed: SQL injection protection, PDO LIMIT/OFFSET binding
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;
    
    /**
     * Whitelist of allowed table names to prevent SQL injection
     */
    private static array $allowedTables = [
        'organizations', 'roles', 'users', 'user_sessions',
        'labs', 'buildings', 'rooms', 'cabinets', 'shelves', 'slots',
        'chemical_categories', 'chemicals', 'chemical_suppliers',
        'containers', 'container_history',
        'borrow_requests', 'transfers',
        'ai_chat_sessions', 'ai_chat_messages',
        'visual_searches', 'usage_predictions',
        'alerts', 'notification_settings',
        'audit_logs', 'compliance_checks',
        'container_3d_models', 'ar_sessions',
        'departments', 'manufacturers', 'suppliers',
        'funding_sources', 'quantity_units',
        'import_staging', 'import_field_mapping',
        'system_settings',
        'chemical_sds_files', 'chemical_ghs_data',
        'chemical_packaging',
        'packaging_3d_models', 'model_requests',
        'chemical_warehouses',
        'chemical_stock',
        'chemical_transactions',
        'disposal_bin',
        'lab_stores'
    ];
    
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Enable emulated prepares for compatibility with dynamic SQL building
                    PDO::ATTR_EMULATE_PREPARES => true,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
                ];
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed. Please try again later.");
            }
        }
        return self::$instance;
    }
    
    /**
     * Validate table name against whitelist to prevent SQL injection
     */
    private static function validateTableName(string $table): string {
        if (!in_array($table, self::$allowedTables, true)) {
            throw new Exception("Invalid table name: {$table}");
        }
        return '`' . $table . '`';
    }
    
    /**
     * Validate column name - only allows alphanumeric and underscores
     */
    private static function validateColumnName(string $column): string {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new Exception("Invalid column name: {$column}");
        }
        return '`' . $column . '`';
    }
    
    public static function query(string $sql, array $params = []): PDOStatement {
        $db = self::getInstance();
        $stmt = $db->prepare($sql);
        
        // Bind parameters with proper types (fixes LIMIT/OFFSET with integers)
        foreach ($params as $key => $value) {
            // Convert numeric keys to 1-based for PDO (PDO uses 1-based indexing)
            $pdo_key = is_int($key) ? $key + 1 : $key;
            
            if (is_int($value)) {
                $stmt->bindValue($pdo_key, $value, PDO::PARAM_INT);
            } elseif (is_bool($value)) {
                $stmt->bindValue($pdo_key, $value, PDO::PARAM_BOOL);
            } elseif (is_null($value)) {
                $stmt->bindValue($pdo_key, $value, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue($pdo_key, $value, PDO::PARAM_STR);
            }
        }
        
        $stmt->execute();
        return $stmt;
    }
    
    public static function fetch(string $sql, array $params = []): ?array {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public static function fetchAll(string $sql, array $params = []): array {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Insert data with table/column name validation
     */
    public static function insert(string $table, array $data): int {
        $safeTable = self::validateTableName($table);
        
        $safeColumns = [];
        $placeholders = [];
        $safeData = [];
        
        foreach ($data as $key => $value) {
            $safeCol = self::validateColumnName($key);
            $safeColumns[] = $safeCol;
            $paramName = ':' . $key;
            $placeholders[] = $paramName;
            $safeData[$paramName] = $value;
        }
        
        $sql = "INSERT INTO {$safeTable} (" . implode(', ', $safeColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        self::query($sql, $safeData);
        return (int) self::getInstance()->lastInsertId();
    }
    
    /**
     * Update data with table/column name validation
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int {
        $safeTable = self::validateTableName($table);
        
        $setParts = [];
        $safeData = [];
        
        foreach ($data as $key => $value) {
            $safeCol = self::validateColumnName($key);
            $paramName = ':' . $key;
            $setParts[] = "{$safeCol} = {$paramName}";
            $safeData[$paramName] = $value;
        }
        
        $setClause = implode(', ', $setParts);
        $sql = "UPDATE {$safeTable} SET {$setClause} WHERE {$where}";
        $stmt = self::query($sql, array_merge($safeData, $whereParams));
        return $stmt->rowCount();
    }
    
    /**
     * Delete with table name validation
     */
    public static function delete(string $table, string $where, array $params = []): int {
        $safeTable = self::validateTableName($table);
        $sql = "DELETE FROM {$safeTable} WHERE {$where}";
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }
    
    public static function beginTransaction(): void {
        self::getInstance()->beginTransaction();
    }
    
    public static function commit(): void {
        self::getInstance()->commit();
    }
    
    public static function rollback(): void {
        self::getInstance()->rollBack();
    }
    
    public static function lastInsertId(): int {
        return (int) self::getInstance()->lastInsertId();
    }
}
