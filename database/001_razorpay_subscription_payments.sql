/*
   ExamSphere — Subscription Razorpay Payment Migration
   Run once against the existing online_examination database.

   This migration intentionally changes only the subscription payment record
   required by the real Razorpay workflow.
*/

ALTER TABLE subscription_payments
    MODIFY payment_method VARCHAR(30) NULL;

ALTER TABLE subscription_payments
    ADD COLUMN gateway_order_id VARCHAR(100) NULL AFTER reference_no,
    ADD COLUMN gateway_payment_id VARCHAR(100) NULL AFTER gateway_order_id,
    ADD COLUMN gateway_signature VARCHAR(128) NULL AFTER gateway_payment_id,
    ADD COLUMN gateway_status VARCHAR(30) NULL AFTER gateway_signature,
    ADD COLUMN gateway_method VARCHAR(30) NULL AFTER gateway_status,
    ADD COLUMN gateway_currency CHAR(3) NOT NULL DEFAULT 'INR' AFTER gateway_method;

ALTER TABLE subscription_payments
    ADD UNIQUE KEY uq_subscription_gateway_order (gateway_order_id),
    ADD UNIQUE KEY uq_subscription_gateway_payment (gateway_payment_id),
    ADD INDEX idx_subscription_gateway_status (gateway_status),
    ADD INDEX idx_subscription_payment_status (student_id, payment_status, created_at);
