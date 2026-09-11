<?php

function clean($data)
{
    return htmlspecialchars(trim($data));
}

function redirect($url)
{
    header("Location: " . $url);
    exit();
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function subscription_period(DateTimeImmutable $startDate, int $durationMonths): array
{
    if ($durationMonths < 1 || $durationMonths > 255) {
        throw new InvalidArgumentException('Invalid subscription duration.');
    }

    $startDate = $startDate->setTime(0, 0);
    $targetMonth = $startDate->modify('first day of this month')
        ->modify('+' . $durationMonths . ' months');
    $anniversaryDay = min((int)$startDate->format('d'), (int)$targetMonth->format('t'));
    $exclusiveEnd = $targetMonth->setDate(
        (int)$targetMonth->format('Y'),
        (int)$targetMonth->format('m'),
        $anniversaryDay
    );

    return [$startDate, $exclusiveEnd->modify('-1 day')];
}

function has_active_subscription($conn, $student_id)
{
    $stmt = $conn->prepare(
        "SELECT id
         FROM subscriptions
         WHERE student_id = ?
           AND status = 'Active'
           AND start_date <= CURDATE()
           AND end_date >= CURDATE()
         ORDER BY end_date DESC
         LIMIT 1"
    );

    $stmt->execute([
        (int) $student_id
    ]);

    return (bool) $stmt->fetchColumn();
}

function live_exam_access_message($conn, $student_id, $exam)
{
    if ((float) $exam['exam_fee'] > 0) {

        $stmt = $conn->prepare(
            "SELECT id
             FROM live_exam_payments
             WHERE student_id = ?
               AND exam_id = ?
               AND payment_status = 'Paid'
             LIMIT 1"
        );

        $stmt->execute([
            (int) $student_id,
            (int) $exam['id']
        ]);

        if (!$stmt->fetchColumn()) {
            return 'A verified payment is required for this live exam.';
        }
    }

    if (
        (int) $exam['subscription_required'] === 1 &&
        !has_active_subscription($conn, $student_id)
    ) {
        return 'An active subscription is required for this live exam.';
    }

    return '';
}

/*
|--------------------------------------------------------------------------
| CANONICAL RESULT GRADE
|--------------------------------------------------------------------------
|
| Every submission/reporting path must use the same grade scale.
|
*/

function examsphere_grade_from_percentage(
    float $percentage
): string {

    $percentage =
        max(
            0.00,
            min(
                100.00,
                $percentage
            )
        );

    return match (true) {

        $percentage >= 90 =>
            'A+',

        $percentage >= 75 =>
            'A',

        $percentage >= 60 =>
            'B',

        $percentage >= 40 =>
            'C',

        default =>
            'F'
    };
}


function examsphere_accuracy_percentage(
    int $correct,
    int $attempted
): float {

    if (
        $attempted <= 0
    ) {
        return 0.00;
    }

    return round(
        max(
            0.00,
            min(
                100.00,
                (
                    $correct /
                    $attempted
                ) *
                100
            )
        ),
        2
    );
}
