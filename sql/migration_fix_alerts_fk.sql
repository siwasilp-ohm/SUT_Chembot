-- Fix: alerts.borrow_request_id FK constraint blocked all borrow_request alert inserts
-- The column was referencing borrow_requests.id (legacy table) but now stores chemical_transactions.id
-- Solution: drop the bad FK constraint; keep column as loose reference INT

ALTER TABLE alerts DROP FOREIGN KEY alerts_ibfk_5;

-- Also ensure borrow_request alert_type is in the ENUM (idempotent on live DB)
ALTER TABLE alerts MODIFY COLUMN alert_type
    ENUM('expiry','low_stock','overdue_borrow','safety_violation','temperature_alert','compliance','custom','borrow_request')
    NOT NULL;
