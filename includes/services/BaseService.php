<?php
/**
 * Base Service Class for SUT chemBot
 * Provides common functionality for all services
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/logger.php';

abstract class BaseService {
    protected string $tableName;
    protected array $allowedFields = [];
    protected string $primaryKey = 'id';
    
    /**
     * Get all records with optional filters
     */
    public function getAll(array $filters = [], array $orderBy = [], int $limit = 100, int $offset = 0): array {
        $where = [];
        $params = [];
        
        foreach ($filters as $field => $value) {
            if (in_array($field, $this->allowedFields)) {
                if (is_array($value)) {
                    $where[] = "`{$field}` IN (:" . str_replace('.', '_', $field) . ")";
                    $params[':' . str_replace('.', '_', $field)] = implode(',', $value);
                } elseif ($value === null) {
                    $where[] = "`{$field}` IS NULL";
                } else {
                    $where[] = "`{$field}` = :" . str_replace('.', '_', $field);
                    $params[':' . str_replace('.', '_', $field)] = $value;
                }
            }
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $orderClause = '';
        if (!empty($orderBy)) {
            $orderParts = [];
            foreach ($orderBy as $field => $direction) {
                $orderParts[] = "`{$field}` " . (strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC');
            }
            $orderClause = 'ORDER BY ' . implode(', ', $orderParts);
        }
        
        $sql = "SELECT * FROM `{$this->tableName}` {$whereClause} {$orderClause} LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        return Database::fetchAll($sql, $params);
    }
    
    /**
     * Get record by ID
     */
    public function getById(int $id): ?array {
        $sql = "SELECT * FROM `{$this->tableName}` WHERE `{$this->primaryKey}` = :id";
        return Database::fetch($sql, [':id' => $id]);
    }
    
    /**
     * Get record by field value
     */
    public function getByField(string $field, $value): ?array {
        if (!in_array($field, $this->allowedFields)) {
            throw new InvalidArgumentException("Field {$field} is not allowed");
        }
        
        $sql = "SELECT * FROM `{$this->tableName}` WHERE `{$field}` = :value";
        return Database::fetch($sql, [':value' => $value]);
    }
    
    /**
     * Count records
     */
    public function count(array $filters = []): int {
        $where = [];
        $params = [];
        
        foreach ($filters as $field => $value) {
            if (in_array($field, $this->allowedFields)) {
                if ($value === null) {
                    $where[] = "`{$field}` IS NULL";
                } else {
                    $where[] = "`{$field}` = :" . str_replace('.', '_', $field);
                    $params[':' . str_replace('.', '_', $field)] = $value;
                }
            }
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT COUNT(*) as cnt FROM `{$this->tableName}` {$whereClause}";
        $result = Database::fetch($sql, $params);
        
        return (int)($result['cnt'] ?? 0);
    }
    
    /**
     * Create new record
     */
    public function create(array $data): int {
        $allowedData = array_intersect_key($data, array_flip($this->allowedFields));
        
        if (empty($allowedData)) {
            throw new InvalidArgumentException("No valid fields provided");
        }
        
        // Remove primary key if auto-increment
        unset($allowedData[$this->primaryKey]);
        
        $id = Database::insert($this->tableName, $allowedData);
        
        ErrorLogger::info("Created new record in {$this->tableName}", [
            'id' => $id,
            'data' => $allowedData
        ]);
        
        return $id;
    }
    
    /**
     * Update record
     */
    public function update(int $id, array $data): bool {
        $allowedData = array_intersect_key($data, array_flip($this->allowedFields));
        
        if (empty($allowedData)) {
            throw new InvalidArgumentException("No valid fields provided");
        }
        
        // Don't update primary key
        unset($allowedData[$this->primaryKey]);
        
        if (empty($allowedData)) {
            return false;
        }
        
        $affected = Database::update(
            $this->tableName,
            $allowedData,
            "`{$this->primaryKey}` = :id",
            [':id' => $id]
        );
        
        if ($affected > 0) {
            ErrorLogger::info("Updated record in {$this->tableName}", [
                'id' => $id,
                'data' => $allowedData
            ]);
        }
        
        return $affected > 0;
    }
    
    /**
     * Delete record (soft delete)
     */
    public function delete(int $id, bool $hardDelete = false): bool {
        if ($hardDelete) {
            $affected = Database::delete($this->tableName, "`{$this->primaryKey}` = :id", [':id' => $id]);
        } else {
            // Soft delete - update is_active or status
            if (in_array('is_active', $this->allowedFields)) {
                $affected = Database::update(
                    $this->tableName,
                    ['is_active' => 0],
                    "`{$this->primaryKey}` = :id",
                    [':id' => $id]
                );
            } elseif (in_array('status', $this->allowedFields)) {
                $affected = Database::update(
                    $this->tableName,
                    ['status' => 'deleted'],
                    "`{$this->primaryKey}` = :id",
                    [':id' => $id]
                );
            } else {
                throw new Exception("Soft delete not supported for this table");
            }
        }
        
        if ($affected > 0) {
            ErrorLogger::info("Deleted record from {$this->tableName}", [
                'id' => $id,
                'hard_delete' => $hardDelete
            ]);
        }
        
        return $affected > 0;
    }
    
    /**
     * Check if record exists
     */
    public function exists(int $id): bool {
        $sql = "SELECT 1 FROM `{$this->tableName}` WHERE `{$this->primaryKey}` = :id LIMIT 1";
        return Database::fetch($sql, [':id' => $id]) !== null;
    }
    
    /**
     * Get paginated results
     */
    public function getPaginated(int $page = 1, int $perPage = 20, array $filters = [], array $orderBy = []): array {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $total = $this->count($filters);
        
        // Get data
        $data = $this->getAll($filters, $orderBy, $perPage, $offset);
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
            'has_next' => $page * $perPage < $total,
            'has_prev' => $page > 1
        ];
    }
    
    /**
     * Log activity
     */
    protected function logActivity(string $action, array $data = []): void {
        ErrorLogger::info("{$this->tableName}: {$action}", $data);
    }
}

/**
 * Chemical Service
 */
class ChemicalService extends BaseService {
    protected string $tableName = 'chemicals';
    protected array $allowedFields = [
        'cas_number', 'name', 'iupac_name', 'synonyms', 'molecular_formula',
        'molecular_weight', 'description', 'category_id', 'physical_state',
        'appearance', 'odor', 'melting_point', 'boiling_point', 'density',
        'solubility', 'vapor_pressure', 'flash_point', 'auto_ignition_temp',
        'ghs_classifications', 'hazard_pictograms', 'signal_word',
        'hazard_statements', 'precautionary_statements', 'sds_url',
        'sds_pdf_path', 'sds_last_updated', 'safety_info', 'handling_procedures',
        'storage_requirements', 'disposal_methods', 'first_aid_measures',
        'fire_fighting_measures', 'accidental_release_measures',
        'exposure_controls', 'incompatible_chemicals', 'storage_compatibility_group',
        'image_url', 'model_3d_url', 'model_3d_glb', 'model_3d_usdz',
        'created_by', 'is_active', 'verified', 'manufacturer_id',
        'catalogue_number', 'substance_type', 'substance_category'
    ];
    
    /**
     * Search chemicals
     */
    public function search(string $query, int $limit = 20): array {
        $s = '%' . $query . '%';
        return Database::fetchAll("
            SELECT * FROM {$this->tableName} 
            WHERE is_active = 1 
            AND (name LIKE :s1 OR cas_number LIKE :s2 OR iupac_name LIKE :s3 OR catalogue_number LIKE :s4)
            ORDER BY CASE 
                WHEN name LIKE :s5 THEN 1 
                WHEN cas_number LIKE :s6 THEN 2 
                ELSE 3 
            END, name
            LIMIT :limit",
            [
                ':s1' => $s, ':s2' => $s, ':s3' => $s, ':s4' => $s,
                ':s5' => $query . '%', ':s6' => $query . '%',
                ':limit' => $limit
            ]
        );
    }
    
    /**
     * Get chemicals by CAS number
     */
    public function getByCasNumber(string $casNumber): ?array {
        return $this->getByField('cas_number', $casNumber);
    }
    
    /**
     * Get chemicals by category
     */
    public function getByCategory(int $categoryId, int $limit = 50): array {
        return $this->getAll(
            ['category_id' => $categoryId, 'is_active' => 1],
            ['name' => 'ASC'],
            $limit
        );
    }
    
    /**
     * Get expiring chemicals
     */
    public function getExpiring(int $days = 30, int $limit = 50): array {
        $futureDate = date('Y-m-d', strtotime("+{$days} days"));
        return Database::fetchAll("
            SELECT c.*, cn.expiry_date, cn.current_quantity, cn.quantity_unit
            FROM {$this->tableName} c
            JOIN containers cn ON cn.chemical_id = c.id
            WHERE c.is_active = 1 
            AND cn.status = 'active'
            AND cn.expiry_date <= :future_date
            AND cn.expiry_date >= CURDATE()
            ORDER BY cn.expiry_date ASC
            LIMIT :limit",
            [':future_date' => $futureDate, ':limit' => $limit]
        );
    }
}

/**
 * Container Service
 */
class ContainerService extends BaseService {
    protected string $tableName = 'containers';
    protected array $allowedFields = [
        'qr_code', 'qr_code_image', 'chemical_id', 'owner_id', 'lab_id',
        'location_slot_id', 'container_type', 'container_material',
        'container_size', 'container_capacity', 'capacity_unit',
        'initial_quantity', 'current_quantity', 'quantity_unit',
        'manufacture_date', 'received_date', 'opened_date', 'expiry_date',
        'expiry_alert_days', 'status', 'quality_status', 'label_image',
        'container_3d_model', 'batch_number', 'lot_number', 'po_number',
        'supplier_id', 'cost', 'notes', 'created_by'
    ];
    
    /**
     * Generate new QR code
     */
    public function generateQrCode(): string {
        do {
            $code = 'CHEM-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        } while ($this->qrCodeExists($code));
        
        return $code;
    }
    
    /**
     * Check if QR code exists
     */
    public function qrCodeExists(string $qrCode): bool {
        $result = Database::fetch(
            "SELECT 1 FROM {$this->tableName} WHERE qr_code = :code LIMIT 1",
            [':code' => $qrCode]
        );
        return $result !== null;
    }
    
    /**
     * Get container by QR code
     */
    public function getByQrCode(string $qrCode): ?array {
        return Database::fetch("
            SELECT c.*, ch.name as chemical_name, ch.cas_number,
                   u.first_name, u.last_name, l.name as lab_name
            FROM {$this->tableName} c
            JOIN chemicals ch ON c.chemical_id = ch.id
            JOIN users u ON c.owner_id = u.id
            JOIN labs l ON c.lab_id = l.id
            WHERE c.qr_code = :qr_code",
            [':qr_code' => $qrCode]
        );
    }
    
    /**
     * Update quantity
     */
    public function updateQuantity(int $id, float $newQuantity): bool {
        return $this->update($id, ['current_quantity' => $newQuantity]);
    }
    
    /**
     * Get containers by owner
     */
    public function getByOwner(int $ownerId, string $status = 'active'): array {
        return $this->getAll(
            ['owner_id' => $ownerId, 'status' => $status],
            ['created_at' => 'DESC']
        );
    }
    
    /**
     * Get containers by lab
     */
    public function getByLab(int $labId, string $status = 'active'): array {
        return $this->getAll(
            ['lab_id' => $labId, 'status' => $status],
            ['created_at' => 'DESC']
        );
    }
    
    /**
     * Get low stock containers
     */
    public function getLowStock(int $thresholdPercent = 20, int $limit = 50): array {
        return Database::fetchAll("
            SELECT c.*, ch.name as chemical_name, ch.cas_number
            FROM {$this->tableName} c
            JOIN chemicals ch ON c.chemical_id = ch.id
            WHERE c.status = 'active'
            AND (c.current_quantity / c.initial_quantity * 100) <= :threshold
            ORDER BY (c.current_quantity / c.initial_quantity) ASC
            LIMIT :limit",
            [':threshold' => $thresholdPercent, ':limit' => $limit]
        );
    }
    
    /**
     * Get expired containers
     */
    public function getExpired(int $limit = 50): array {
        return Database::fetchAll("
            SELECT c.*, ch.name as chemical_name, ch.cas_number,
                   u.first_name, u.last_name
            FROM {$this->tableName} c
            JOIN chemicals ch ON c.chemical_id = ch.id
            JOIN users u ON c.owner_id = u.id
            WHERE c.status = 'active'
            AND c.expiry_date < CURDATE()
            ORDER BY c.expiry_date ASC
            LIMIT :limit",
            [':limit' => $limit]
        );
    }
}

/**
 * Alert Service
 */
class AlertService extends BaseService {
    protected string $tableName = 'alerts';
    protected array $allowedFields = [
        'alert_type', 'severity', 'title', 'message', 'user_id',
        'chemical_id', 'container_id', 'lab_id', 'borrow_request_id',
        'is_read', 'read_at', 'created_at'
    ];
    
    /**
     * Create alert
     */
    public function createAlert(
        string $type,
        string $severity,
        string $title,
        string $message,
        ?int $userId = null,
        ?int $chemicalId = null,
        ?int $containerId = null,
        ?int $labId = null
    ): int {
        return $this->create([
            'alert_type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'user_id' => $userId,
            'chemical_id' => $chemicalId,
            'container_id' => $containerId,
            'lab_id' => $labId,
            'is_read' => 0
        ]);
    }
    
    /**
     * Get unread alerts for user
     */
    public function getUnreadForUser(int $userId, int $limit = 20): array {
        return Database::fetchAll("
            SELECT * FROM {$this->tableName}
            WHERE user_id = :user_id AND is_read = 0
            ORDER BY severity ASC, created_at DESC
            LIMIT :limit",
            [':user_id' => $userId, ':limit' => $limit]
        );
    }
    
    /**
     * Mark alert as read
     */
    public function markAsRead(int $alertId): bool {
        return Database::update(
            $this->tableName,
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
            'id = :id',
            [':id' => $alertId]
        ) > 0;
    }
    
    /**
     * Mark all as read for user
     */
    public function markAllAsRead(int $userId): int {
        return Database::update(
            $this->tableName,
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
            'user_id = :user_id AND is_read = 0',
            [':user_id' => $userId]
        );
    }
    
    /**
     * Get alert counts by type
     */
    public function getCountsByType(int $userId): array {
        return Database::fetchAll("
            SELECT alert_type, severity, COUNT(*) as count
            FROM {$this->tableName}
            WHERE user_id = :user_id AND is_read = 0
            GROUP BY alert_type, severity",
            [':user_id' => $userId]
        );
    }
    
    /**
     * Create expiry alert
     */
    public function createExpiryAlert(int $containerId, int $daysLeft): void {
        $container = Database::fetch("
            SELECT c.*, ch.name as chemical_name, u.id as owner_id
            FROM containers c
            JOIN chemicals ch ON c.chemical_id = ch.id
            JOIN users u ON c.owner_id = u.id
            WHERE c.id = :id",
            [':id' => $containerId]
        );
        
        if ($container) {
            $severity = $daysLeft <= 7 ? 'critical' : ($daysLeft <= 30 ? 'warning' : 'info');
            
            $this->createAlert(
                'expiry',
                $severity,
                'สารเคมีใกล้หมดอายุ',
                "{$container['chemical_name']} จะหมดอายุภายใน {$daysLeft} วัน",
                $container['owner_id'],
                $container['chemical_id'],
                $containerId,
                $container['lab_id']
            );
        }
    }
    
    /**
     * Create low stock alert
     */
    public function createLowStockAlert(int $containerId, float $remainingPercent): void {
        $container = Database::fetch("
            SELECT c.*, ch.name as chemical_name, u.id as owner_id
            FROM containers c
            JOIN chemicals ch ON c.chemical_id = ch.id
            JOIN users u ON c.owner_id = u.id
            WHERE c.id = :id",
            [':id' => $containerId]
        );
        
        if ($container) {
            $severity = $remainingPercent <= 10 ? 'critical' : ($remainingPercent <= 25 ? 'warning' : 'info');
            
            $this->createAlert(
                'low_stock',
                $severity,
                'สต็อกสารเคมีต่ำ',
                "{$container['chemical_name']} คงเหลือเพียง " . round($remainingPercent) . "%",
                $container['owner_id'],
                $container['chemical_id'],
                $containerId,
                $container['lab_id']
            );
        }
    }
}
