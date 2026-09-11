/* ExamSphere — Live Exam Razorpay Payment Migration */
ALTER TABLE live_exam_payments
    MODIFY payment_method VARCHAR(30) NULL,
    ADD COLUMN gateway_order_id VARCHAR(100) NULL AFTER reference_no,
    ADD COLUMN gateway_payment_id VARCHAR(100) NULL AFTER gateway_order_id,
    ADD COLUMN gateway_signature VARCHAR(128) NULL AFTER gateway_payment_id,
    ADD COLUMN gateway_status VARCHAR(30) NULL AFTER gateway_signature,
    ADD COLUMN gateway_method VARCHAR(30) NULL AFTER gateway_status,
    ADD COLUMN gateway_currency CHAR(3) NOT NULL DEFAULT 'INR' AFTER gateway_method,
    ADD UNIQUE KEY uq_live_gateway_order (gateway_order_id),
    ADD UNIQUE KEY uq_live_gateway_payment (gateway_payment_id),
    ADD INDEX idx_live_gateway_status (gateway_status),
    ADD INDEX idx_live_payment_lookup (student_id, exam_id, payment_status, created_at);
