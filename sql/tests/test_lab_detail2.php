<?php
require_once __DIR__ . '/../includes/database.php';

$dept = 'ฝ่ายห้องปฏิบัติการวิทยาศาสตร์สุขภาพ';

// Test each query from getLabDetail separately

echo "1. Summary...\n";
try {
    $summary = Database::fetch(
        "SELECT COUNT(DISTINCT cs.id) as total_bottles,
                COUNT(DISTINCT CASE WHEN cs.status IN ('active','low') THEN cs.id END) as active_bottles,
                COUNT(DISTINCT CASE WHEN cs.status = 'low' THEN cs.id END) as low_bottles,
                COUNT(DISTINCT CASE WHEN cs.status = 'expired' THEN cs.id END) as expired_bottles,
                COUNT(DISTINCT cs.chemical_id) as unique_chemicals,
                COUNT(DISTINCT u.id) as user_count,
                ROUND(AVG(cs.remaining_pct),1) as avg_remaining_pct
         FROM users u
         LEFT JOIN chemical_stock cs ON cs.owner_user_id = u.id
         WHERE u.is_active = 1 AND u.department = :dept",
        [':dept' => $dept]
    );
    echo "  OK: " . json_encode($summary) . "\n";
} catch (Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

echo "2. Members...\n";
try {
    $members = Database::fetchAll(
        "SELECT u.id, u.first_name, u.last_name, u.employee_id, u.position,
                COUNT(DISTINCT cs.id) as bottle_count,
                COUNT(DISTINCT CASE WHEN cs.status = 'low' THEN cs.id END) as low_count,
                ROUND(AVG(cs.remaining_pct),1) as avg_pct
         FROM users u
         LEFT JOIN chemical_stock cs ON cs.owner_user_id = u.id AND cs.status IN ('active','low')
         WHERE u.is_active = 1 AND u.department = :dept
         GROUP BY u.id
         ORDER BY bottle_count DESC",
        [':dept' => $dept]
    );
    echo "  OK: " . count($members) . " members\n";
} catch (Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

echo "3. Chemicals...\n";
try {
    $chemicals = Database::fetchAll(
        "SELECT COALESCE(c.name, cs.chemical_name) as name,
                COALESCE(c.cas_number, cs.cas_no) as cas_number,
                c.molecular_formula,
                COUNT(cs.id) as bottle_count,
                SUM(cs.remaining_qty) as total_remaining,
                SUM(cs.package_size) as total_capacity,
                cs.unit,
                ROUND(AVG(cs.remaining_pct),1) as avg_pct,
                MIN(cs.remaining_pct) as min_pct
         FROM chemical_stock cs
         LEFT JOIN chemicals c ON cs.chemical_id = c.id
         JOIN users u ON cs.owner_user_id = u.id
         WHERE u.department = :dept AND cs.status IN ('active','low')
         GROUP BY COALESCE(cs.chemical_id, cs.id)
         ORDER BY bottle_count DESC
         LIMIT 20",
        [':dept' => $dept]
    );
    echo "  OK: " . count($chemicals) . " chemicals\n";
} catch (Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

echo "4. Transactions...\n";
try {
    $transactions = Database::fetchAll(
        "SELECT ct.id, ct.txn_number, ct.txn_type, ct.quantity, ct.unit,
                ct.purpose, ct.status, ct.created_at,
                COALESCE(c.name, cs.chemical_name) as chemical_name,
                ui.first_name as initiated_first, ui.last_name as initiated_last,
                uf.first_name as from_first, uf.last_name as from_last,
                ut.first_name as to_first, ut.last_name as to_last
         FROM chemical_transactions ct
         JOIN chemical_stock cs ON ct.source_id = cs.id AND ct.source_type = 'stock'
         LEFT JOIN chemicals c ON cs.chemical_id = c.id
         LEFT JOIN users ui ON ct.initiated_by = ui.id
         LEFT JOIN users uf ON ct.from_user_id = uf.id
         LEFT JOIN users ut ON ct.to_user_id = ut.id
         WHERE EXISTS (SELECT 1 FROM users u2 WHERE u2.id = cs.owner_user_id AND u2.department = :dept)
         ORDER BY ct.created_at DESC
         LIMIT 15",
        [':dept' => $dept]
    );
    echo "  OK: " . count($transactions) . " transactions\n";
} catch (Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

echo "5. Overdue...\n";
try {
    $overdue = Database::fetchAll(
        "SELECT br.id, br.request_number, br.requested_quantity, br.quantity_unit,
                br.expected_return_date,
                DATEDIFF(CURDATE(), br.expected_return_date) as days_overdue,
                c.name as chemical_name,
                u.first_name, u.last_name
         FROM borrow_requests br
         JOIN chemicals c ON br.chemical_id = c.id
         JOIN users u ON br.requester_id = u.id
         WHERE u.department = :dept AND br.status = 'overdue'
         ORDER BY br.expected_return_date ASC",
        [':dept' => $dept]
    );
    echo "  OK: " . count($overdue) . " overdue\n";
} catch (Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== DONE ===\n";
