<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {
    header('Location: ../auth/login.php');
    exit;
}

$studentId = (int) $_SESSION['user_id'];

$examId = filter_input(
    INPUT_GET,
    'exam_id',
    FILTER_VALIDATE_INT
);

if (
    $examId === false ||
    $examId === null ||
    $examId <= 0
) {
    header('Location: my_exams.php');
    exit;
}

function exam_details_escape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function exam_details_number(
    float|int|string|null $value
): string {
    $number = (float) $value;

    if (floor($number) === $number) {
        return number_format(
            $number,
            0,
            '.',
            ''
        );
    }

    return rtrim(
        rtrim(
            number_format(
                $number,
                2,
                '.',
                ''
            ),
            '0'
        ),
        '.'
    );
}

function exam_details_date(
    ?string $value,
    string $format = 'd M Y, h:i A'
): string {
    if (trim((string) $value) === '') {
        return 'Not scheduled';
    }

    try {
        return (
            new DateTimeImmutable(
                (string) $value
            )
        )->format($format);
    } catch (Throwable) {
        return 'Not scheduled';
    }
}

function exam_details_schedule_state(
    string $examType,
    string $status,
    ?DateTimeImmutable $startsAt,
    ?DateTimeImmutable $endsAt,
    DateTimeImmutable $now
): string {
    if ($examType === 'Practice') {
        return $status === 'Active'
            ? 'Available'
            : 'Unavailable';
    }

    if ($examType !== 'Live') {
        return 'Unavailable';
    }

    if (
        $status !== 'Active' &&
        $status !== 'Live'
    ) {
        return 'Unavailable';
    }

    if (
        $startsAt !== null &&
        $now < $startsAt
    ) {
        return 'Upcoming';
    }

    if (
        $startsAt !== null &&
        $now >= $startsAt &&
        (
            $endsAt === null ||
            $now <= $endsAt
        )
    ) {
        return 'Live';
    }

    if (
        $endsAt !== null &&
        $now > $endsAt
    ) {
        return 'Ended';
    }

    if ($startsAt === null) {
        return 'Unavailable';
    }

    return 'Upcoming';
}


/*
|--------------------------------------------------------------------------
| LOAD EXAM
|--------------------------------------------------------------------------
*/

try {

    $examStatement = $conn->prepare("
        SELECT

            e.id,
            e.subject_id,
            e.teacher_id,

            e.title,
            e.description,

            e.exam_type,

            e.duration_minutes,
            e.required_question_count,

            e.total_marks,
            e.passing_marks,

            e.negative_marking,
            e.exam_fee,
            e.subscription_required,

            e.starts_at,
            e.ends_at,
            e.status,

            e.created_at,
            e.updated_at,

            s.name AS subject_name,
            s.code AS subject_code,
            s.status AS subject_status,

            t.full_name AS teacher_name,
            t.status AS teacher_status,

            (
                SELECT COUNT(DISTINCT eq1.question_id)

                FROM exam_questions eq1

                INNER JOIN questions q1
                    ON q1.id = eq1.question_id

                WHERE eq1.exam_id = e.id
                  AND q1.status = 'Active'

            ) AS active_question_count

        FROM exams e

        LEFT JOIN subjects s
            ON s.id = e.subject_id

        LEFT JOIN teachers t
            ON t.id = e.teacher_id

        WHERE e.id = ?

        LIMIT 1
    ");

    $examStatement->execute([
        $examId
    ]);

    $exam = $examStatement->fetch(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $exception) {

    error_log(
        'Exam details load failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load exam details.'
    );
}

if (!$exam) {
    header('Location: my_exams.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| READINESS
|--------------------------------------------------------------------------
*/

$requiredQuestions =
    (int) $exam['required_question_count'];

$activeQuestionCount =
    (int) $exam['active_question_count'];

$subjectAvailable =
    !empty($exam['subject_id']) &&
    trim((string) $exam['subject_name']) !== '' &&
    ($exam['subject_status'] ?? '') === 'Active';

$examReady =
    $requiredQuestions > 0 &&
    $activeQuestionCount === $requiredQuestions &&
    $subjectAvailable;


/*
|--------------------------------------------------------------------------
| SCHEDULE
|--------------------------------------------------------------------------
*/

$now =
    new DateTimeImmutable();

$startsAt =
    null;

$endsAt =
    null;

if (!empty($exam['starts_at'])) {

    try {

        $startsAt =
            new DateTimeImmutable(
                (string) $exam['starts_at']
            );

    } catch (Throwable) {

        $startsAt =
            null;
    }
}

if (!empty($exam['ends_at'])) {

    try {

        $endsAt =
            new DateTimeImmutable(
                (string) $exam['ends_at']
            );

    } catch (Throwable) {

        $endsAt =
            null;
    }
}

$scheduleState =
    exam_details_schedule_state(
        (string) $exam['exam_type'],
        (string) $exam['status'],
        $startsAt,
        $endsAt,
        $now
    );


/*
|--------------------------------------------------------------------------
| STUDENT ATTEMPT + RESULT + ACCESS
|--------------------------------------------------------------------------
*/

$completedResult =
    null;

$activeAttempt =
    null;

$accessMessage =
    '';

try {

    $completedResultStatement =
        $conn->prepare("
            SELECT

                r.id AS result_id,
                r.percentage,
                r.obtained_marks,
                r.total_marks,
                r.correct_answers,
                r.wrong_answers,
                r.unanswered_questions,
                r.result_status,
                r.grade,
                r.created_at

            FROM results r

            WHERE r.student_id = ?
              AND r.exam_id = ?

            ORDER BY
                r.created_at DESC,
                r.id DESC

            LIMIT 1
        ");

    $completedResultStatement->execute([
        $studentId,
        $examId
    ]);

    $completedResult =
        $completedResultStatement->fetch(
            PDO::FETCH_ASSOC
        ) ?: null;


    $activeAttemptStatement =
        $conn->prepare("
            SELECT

                id,
                started_at,
                submitted_at,
                status

            FROM exam_attempts

            WHERE student_id = ?
              AND exam_id = ?
              AND status = 'Started'

            ORDER BY
                started_at DESC,
                id DESC

            LIMIT 1
        ");

    $activeAttemptStatement->execute([
        $studentId,
        $examId
    ]);

    $activeAttempt =
        $activeAttemptStatement->fetch(
            PDO::FETCH_ASSOC
        ) ?: null;


    if (
        (string) $exam['exam_type'] === 'Live'
    ) {

        $accessMessage =
            live_exam_access_message(
                $conn,
                $studentId,
                $exam
            );
    }

} catch (Throwable $exception) {

    error_log(
        'Exam details state lookup failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| ACCESS FLAGS
|--------------------------------------------------------------------------
*/

$isSubscriptionRequired =
    (int) $exam['subscription_required'] === 1;

$examFee =
    (float) $exam['exam_fee'];

$isPaidExam =
    $examFee > 0;

$isAccessBlocked =
    $accessMessage !== '';

$requiresPayment =
    $isPaidExam &&
    $isAccessBlocked &&
    stripos(
        $accessMessage,
        'payment'
    ) !== false;

$requiresSubscription =
    $isSubscriptionRequired &&
    $isAccessBlocked &&
    stripos(
        $accessMessage,
        'subscription'
    ) !== false;


/*
|--------------------------------------------------------------------------
| ACTION STATE
|--------------------------------------------------------------------------
*/

$actionType =
    'none';

$actionText =
    'Unavailable';

$actionUrl =
    '#';

$canStart =
    false;


/*
|--------------------------------------------------------------------------
| PRACTICE
|--------------------------------------------------------------------------
*/

if (
    (string) $exam['exam_type'] === 'Practice'
) {

    if (
        (string) $exam['status'] === 'Active' &&
        $examReady
    ) {

        $canStart =
            true;

        $actionType =
            $activeAttempt
                ? 'resume'
                : 'start';

        $actionText =
            $activeAttempt
                ? 'Resume exam'
                : 'Start practice';

        $actionUrl =
            'start_exam.php?id=' .
            $examId;

    } elseif (
        (string) $exam['status'] === 'Active'
    ) {

        $actionType =
            'not-ready';

        $actionText =
            'Exam not ready';

    } else {

        $actionType =
            'unavailable';

        $actionText =
            'Exam unavailable';
    }
}


/*
|--------------------------------------------------------------------------
| LIVE
|--------------------------------------------------------------------------
*/

else {

    if (!$examReady) {

        $actionType =
            'not-ready';

        $actionText =
            'Exam not ready';

    } elseif (
        $scheduleState === 'Upcoming'
    ) {

        $actionType =
            'upcoming';

        $actionText =
            'Exam not started';

    } elseif (
        $scheduleState === 'Ended'
    ) {

        $actionType =
            'ended';

        $actionText =
            'Exam ended';

    } elseif (
        $scheduleState === 'Live' &&
        $isAccessBlocked
    ) {

        if ($requiresPayment) {

            $actionType =
                'payment';

            $actionText =
                'Pay to unlock';

            $actionUrl =
                'live_exams.php#payment-' .
                $examId;

        } elseif (
            $requiresSubscription
        ) {

            $actionType =
                'subscription';

            $actionText =
                'Activate subscription';

            $actionUrl =
                'subscriptions.php';

        } else {

            $actionType =
                'locked';

            $actionText =
                'Access required';
        }

    } elseif (
        $scheduleState === 'Live'
    ) {

        $canStart =
            true;

        $actionType =
            $activeAttempt
                ? 'resume'
                : 'start';

        $actionText =
            $activeAttempt
                ? 'Resume live exam'
                : 'Join live exam';

        $actionUrl =
            'start_exam.php?id=' .
            $examId;

    } else {

        $actionType =
            'unavailable';

        $actionText =
            'Exam unavailable';
    }
}


/*
|--------------------------------------------------------------------------
| RESULT
|--------------------------------------------------------------------------
*/

$resultActionAvailable =
    !empty(
        $completedResult['result_id']
    );

$resultUrl =
    $resultActionAvailable
        ? 'result.php?id=' .
            (int) $completedResult['result_id']
        : '#';


/*
|--------------------------------------------------------------------------
| HEADER STATUS
|--------------------------------------------------------------------------
*/

$statusLabel =
    'Unavailable';

$statusClass =
    'status-unavailable';

if (
    (string) $exam['exam_type'] === 'Practice'
) {

    if (
        (string) $exam['status'] === 'Active' &&
        $examReady
    ) {

        $statusLabel =
            'Ready';

        $statusClass =
            'status-ready';

    } elseif (
        (string) $exam['status'] === 'Active'
    ) {

        $statusLabel =
            'Preparing';

        $statusClass =
            'status-warning';
    }

} else {

    switch ($scheduleState) {

        case 'Upcoming':

            $statusLabel =
                'Upcoming';

            $statusClass =
                'status-upcoming';

            break;

        case 'Live':

            $statusLabel =
                $isAccessBlocked
                    ? 'Live • Access required'
                    : 'Live now';

            $statusClass =
                'status-live';

            break;

        case 'Ended':

            $statusLabel =
                'Ended';

            $statusClass =
                'status-ended';

            break;
    }
}


/*
|--------------------------------------------------------------------------
| DESCRIPTION
|--------------------------------------------------------------------------
*/

$description =
    trim(
        (string) (
            $exam['description'] ?? ''
        )
    );

if ($description === '') {

    $description =
        'Prepare with this ExamSphere examination and use your result to understand your performance.';
}


/*
|--------------------------------------------------------------------------
| READINESS MESSAGE
|--------------------------------------------------------------------------
*/

$readinessMessage =
    'This examination is configured and ready to take.';

if (!$subjectAvailable) {

    $readinessMessage =
        'This exam cannot be started because its subject is currently unavailable.';

} elseif ($requiredQuestions <= 0) {

    $readinessMessage =
        'This exam is not ready because the required question count has not been configured.';

} elseif (
    $activeQuestionCount !==
    $requiredQuestions
) {

    $readinessMessage =
        'This exam is not ready yet. ' .
        $requiredQuestions .
        ' questions are required, but only ' .
        $activeQuestionCount .
        ' active questions are assigned.';

} elseif (
    $isAccessBlocked &&
    $scheduleState === 'Live'
) {

    $readinessMessage =
        $accessMessage;
}

if (
    $actionType === 'payment'
) {

    $readinessMessage =
        $accessMessage !== ''
            ? $accessMessage
            : 'Payment is required before you can enter this live examination.';

} elseif (
    $actionType === 'subscription'
) {

    $readinessMessage =
        $accessMessage !== ''
            ? $accessMessage
            : 'An active subscription is required before you can enter this live examination.';
}


$pageTitle =
    (string) $exam['title'] .
    ' | Exam Details';

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#f5f5dc"
    >

    <title>
        <?= exam_details_escape($pageTitle) ?>
    </title>


    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="assets/css/dashboard.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/exam-details-pro.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

</head>


<body class="exam-details-body">


<?php include 'includes/navbar.php'; ?>


<main class="exam-details-page">

    <div class="container">


        <!-- =================================================
             BREADCRUMB
        ================================================== -->

        <div class="exam-details-breadcrumb">

            <a href="dashboard.php">

                <i class="fa-solid fa-house"></i>

                Dashboard

            </a>

            <i class="fa-solid fa-chevron-right"></i>

            <a
                href="<?= (string) $exam['exam_type'] === 'Practice'
                    ? 'practice_exams.php'
                    : 'live_exams.php'
                ?>"
            >

                <?= (string) $exam['exam_type'] === 'Practice'
                    ? 'Practice Exams'
                    : 'Live Exams'
                ?>

            </a>

            <i class="fa-solid fa-chevron-right"></i>

            <span>
                Details
            </span>

        </div>


        <!-- =================================================
             HERO
        ================================================== -->

        <section class="exam-details-hero">

            <div class="exam-details-hero-main">

                <div class="exam-details-topline">

                    <span
                        class="
                            exam-type-pill
                            <?= (string) $exam['exam_type'] === 'Live'
                                ? 'live'
                                : 'practice'
                            ?>
                        "
                    >

                        <i
                            class="
                                fa-solid
                                <?= (string) $exam['exam_type'] === 'Live'
                                    ? 'fa-tower-broadcast'
                                    : 'fa-pen-to-square'
                                ?>
                            "
                        ></i>

                        <?= exam_details_escape(
                            $exam['exam_type']
                        ) ?>

                    </span>


                    <span
                        class="
                            exam-status-pill
                            <?= $statusClass ?>
                        "
                    >

                        <i
                            class="fa-solid fa-circle"
                        ></i>

                        <?= exam_details_escape(
                            $statusLabel
                        ) ?>

                    </span>

                </div>


                <span class="exam-details-subject">

                    <i
                        class="fa-solid fa-book-open"
                    ></i>

                    <?= exam_details_escape(
                        $exam['subject_name']
                        ?: 'General'
                    ) ?>


                    <?php if (
                        !empty(
                            $exam['subject_code']
                        )
                    ): ?>

                        <span>

                            •

                            <?= exam_details_escape(
                                $exam['subject_code']
                            ) ?>

                        </span>

                    <?php endif; ?>

                </span>


                <h1>

                    <?= exam_details_escape(
                        $exam['title']
                    ) ?>

                </h1>


                <p>

                    <?= nl2br(
                        exam_details_escape(
                            $description
                        )
                    ) ?>

                </p>


                <div class="exam-details-hero-tags">

                    <span>

                        <i
                            class="fa-solid fa-list-check"
                        ></i>

                        <?= $activeQuestionCount ?>
                        active questions

                    </span>


                    <span>

                        <i
                            class="fa-regular fa-clock"
                        ></i>

                        <?= (int) $exam['duration_minutes'] ?>
                        minutes

                    </span>


                    <span>

                        <i
                            class="fa-solid fa-star"
                        ></i>

                        <?= exam_details_number(
                            $exam['total_marks']
                        ) ?>

                        marks

                    </span>

                </div>

            </div>


            <div class="exam-details-hero-side">

                <div class="hero-accent-icon">

                    <i
                        class="
                            fa-solid
                            <?= (string) $exam['exam_type'] === 'Live'
                                ? 'fa-tower-broadcast'
                                : 'fa-graduation-cap'
                            ?>
                        "
                    ></i>

                </div>


                <span>
                    EXAMSPHERE
                </span>


                <strong>
                    Prepare with confidence.
                </strong>

            </div>

        </section>


        <!-- =================================================
             MAIN CONTENT
        ================================================== -->

        <div class="exam-details-grid">


            <section class="exam-details-panel">

                <div class="exam-details-panel-heading">

                    <div>

                        <span>
                            EXAM OVERVIEW
                        </span>

                        <h2>
                            Examination information
                        </h2>

                    </div>


                    <i
                        class="fa-solid fa-file-circle-check"
                    ></i>

                </div>


                <div class="exam-details-info-grid">


                    <div class="exam-info-box">

                        <span class="exam-info-icon">

                            <i
                                class="fa-regular fa-clock"
                            ></i>

                        </span>

                        <div>

                            <small>
                                Duration
                            </small>

                            <strong>

                                <?= (int) $exam['duration_minutes'] ?>

                                minutes

                            </strong>

                        </div>

                    </div>


                    <div class="exam-info-box">

                        <span class="exam-info-icon">

                            <i
                                class="fa-solid fa-list-check"
                            ></i>

                        </span>

                        <div>

                            <small>
                                Questions
                            </small>

                            <strong>

                                <?= $activeQuestionCount ?>

                            </strong>

                        </div>

                    </div>


                    <div class="exam-info-box">

                        <span class="exam-info-icon">

                            <i
                                class="fa-solid fa-star"
                            ></i>

                        </span>

                        <div>

                            <small>
                                Total marks
                            </small>

                            <strong>

                                <?= exam_details_number(
                                    $exam['total_marks']
                                ) ?>

                            </strong>

                        </div>

                    </div>


                    <div class="exam-info-box">

                        <span class="exam-info-icon">

                            <i
                                class="fa-solid fa-bullseye"
                            ></i>

                        </span>

                        <div>

                            <small>
                                Passing marks
                            </small>

                            <strong>

                                <?= exam_details_number(
                                    $exam['passing_marks']
                                ) ?>

                            </strong>

                        </div>

                    </div>


                    <div class="exam-info-box">

                        <span class="exam-info-icon">

                            <i
                                class="fa-solid fa-shuffle"
                            ></i>

                        </span>

                        <div>

                            <small>
                                Negative marking
                            </small>

                            <strong>

                                <?= (int) $exam['negative_marking'] === 1
                                    ? 'Enabled'
                                    : 'None'
                                ?>

                            </strong>

                        </div>

                    </div>


                    <div class="exam-info-box">

                        <span class="exam-info-icon">

                            <i
                                class="fa-solid fa-shield-halved"
                            ></i>

                        </span>

                        <div>

                            <small>
                                Question readiness
                            </small>

                            <strong>

                                <?= $examReady
                                    ? 'Ready'
                                    : 'Incomplete'
                                ?>

                            </strong>

                        </div>

                    </div>

                </div>


                <div class="exam-details-marking-box">

                    <div>

                        <span>

                            <i
                                class="fa-solid fa-calculator"
                            ></i>

                            Marking scheme

                        </span>


                        <strong>

                            <?= (int) $exam['negative_marking'] === 1
                                ? 'Negative marking enabled'
                                : 'No negative marking'
                            ?>

                        </strong>

                    </div>


                    <small>

                        <?= (int) $exam['negative_marking'] === 1
                            ? 'Wrong answers use the negative-mark value configured on each question.'
                            : 'Incorrect answers do not reduce your marks.'
                        ?>

                    </small>

                </div>


                <div class="exam-details-description">

                    <span>
                        ABOUT THIS EXAM
                    </span>


                    <h3>
                        What you are preparing for
                    </h3>


                    <p>

                        <?= nl2br(
                            exam_details_escape(
                                $description
                            )
                        ) ?>

                    </p>

                </div>

            </section>


            <!-- =================================================
                 ACTION PANEL
            ================================================== -->

            <aside class="exam-details-side">


                <section class="exam-details-action-panel">

                    <span class="side-panel-kicker">

                        <?= (string) $exam['exam_type'] === 'Live'
                            ? 'LIVE ACCESS'
                            : 'PRACTICE ACCESS'
                        ?>

                    </span>


                    <h2>

                        <?php if (
                            $actionType === 'resume'
                        ): ?>

                            Continue your exam.

                        <?php elseif (
                            $actionType === 'start'
                        ): ?>

                            Ready to begin?

                        <?php elseif (
                            $actionType === 'upcoming'
                        ): ?>

                            Your exam is scheduled.

                        <?php elseif (
                            $actionType === 'payment'
                        ): ?>

                            Payment required.

                        <?php elseif (
                            $actionType === 'subscription'
                        ): ?>

                            Subscription required.

                        <?php else: ?>

                            Exam access information.

                        <?php endif; ?>

                    </h2>


                    <p>

                        <?= exam_details_escape(
                            $readinessMessage
                        ) ?>

                    </p>


                    <?php if (
                        $isPaidExam ||
                        $isSubscriptionRequired
                    ): ?>

                        <div class="exam-access-box">

                            <?php if (
                                $isSubscriptionRequired
                            ): ?>

                                <span>

                                    <i
                                        class="fa-solid fa-crown"
                                    ></i>

                                    Subscription

                                    <?= $isAccessBlocked
                                        ? 'required'
                                        : 'enabled'
                                    ?>

                                </span>

                            <?php endif; ?>


                            <?php if (
                                $isPaidExam
                            ): ?>

                                <span>

                                    <i
                                        class="fa-solid fa-indian-rupee-sign"
                                    ></i>

                                    Exam fee:

                                    <?= exam_details_number(
                                        $examFee
                                    ) ?>

                                </span>

                            <?php endif; ?>

                        </div>

                    <?php else: ?>

                        <div class="exam-access-box free">

                            <span>

                                <i
                                    class="fa-solid fa-circle-check"
                                ></i>

                                No additional access fee

                            </span>

                        </div>

                    <?php endif; ?>


                    <div class="exam-action-buttons">


                        <?php if (
                            $canStart
                        ): ?>

                            <a
                                href="<?= exam_details_escape(
                                    $actionUrl
                                ) ?>"
                                class="exam-primary-action primary"
                            >

                                <span>

                                    <i
                                        class="
                                            fa-solid
                                            <?= $actionType === 'resume'
                                                ? 'fa-play'
                                                : 'fa-rocket'
                                            ?>
                                        "
                                    ></i>

                                    <?= exam_details_escape(
                                        $actionText
                                    ) ?>

                                </span>


                                <i
                                    class="fa-solid fa-arrow-right"
                                ></i>

                            </a>


                        <?php elseif (
                            $actionType === 'payment'
                        ): ?>

                            <a
                                href="<?= exam_details_escape(
                                    $actionUrl
                                ) ?>"
                                class="exam-primary-action primary"
                            >

                                <span>

                                    <i
                                        class="fa-solid fa-credit-card"
                                    ></i>

                                    <?= exam_details_escape(
                                        $actionText
                                    ) ?>

                                </span>


                                <i
                                    class="fa-solid fa-arrow-right"
                                ></i>

                            </a>


                        <?php elseif (
                            $actionType === 'subscription'
                        ): ?>

                            <a
                                href="<?= exam_details_escape(
                                    $actionUrl
                                ) ?>"
                                class="exam-primary-action primary"
                            >

                                <span>

                                    <i
                                        class="fa-solid fa-crown"
                                    ></i>

                                    <?= exam_details_escape(
                                        $actionText
                                    ) ?>

                                </span>


                                <i
                                    class="fa-solid fa-arrow-right"
                                ></i>

                            </a>


                        <?php else: ?>

                            <button
                                type="button"
                                class="exam-disabled-action"
                                disabled
                            >

                                <span>

                                    <i
                                        class="
                                            fa-solid
                                            <?= $actionType === 'upcoming'
                                                ? 'fa-clock'
                                                : 'fa-lock'
                                            ?>
                                        "
                                    ></i>

                                    <?= exam_details_escape(
                                        $actionText
                                    ) ?>

                                </span>

                            </button>

                        <?php endif; ?>


                        <?php if (
                            $resultActionAvailable
                        ): ?>

                            <a
                                href="<?= exam_details_escape(
                                    $resultUrl
                                ) ?>"
                                class="exam-result-action"
                            >

                                <span>

                                    <i
                                        class="fa-solid fa-chart-column"
                                    ></i>

                                    View latest result

                                </span>


                                <i
                                    class="fa-solid fa-arrow-right"
                                ></i>

                            </a>

                        <?php endif; ?>


                        <a
                            href="<?= (string) $exam['exam_type'] === 'Practice'
                                ? 'practice_exams.php'
                                : 'live_exams.php'
                            ?>"
                            class="exam-back-action"
                        >

                            <i
                                class="fa-solid fa-arrow-left"
                            ></i>

                            Back to exams

                        </a>

                    </div>

                </section>


                <!-- =================================================
                     SCHEDULE
                ================================================== -->

                <section class="exam-details-side-panel">

                    <div class="side-panel-heading">

                        <span>

                            <i
                                class="fa-solid fa-calendar-days"
                            ></i>

                        </span>


                        <div>

                            <small>
                                SCHEDULE
                            </small>

                            <strong>
                                Exam timing
                            </strong>

                        </div>

                    </div>


                    <div class="exam-schedule-list">


                        <div>

                            <span>
                                Starts
                            </span>


                            <strong>

                                <?= exam_details_date(
                                    $exam['starts_at']
                                ) ?>

                            </strong>

                        </div>


                        <div>

                            <span>
                                Ends
                            </span>


                            <strong>

                                <?= exam_details_date(
                                    $exam['ends_at']
                                ) ?>

                            </strong>

                        </div>


                        <div>

                            <span>
                                Current status
                            </span>


                            <strong>

                                <?= exam_details_escape(
                                    $statusLabel
                                ) ?>

                            </strong>

                        </div>

                    </div>

                </section>


                <!-- =================================================
                     TEACHER
                ================================================== -->

                <?php if (
                    !empty(
                        $exam['teacher_name']
                    )
                ): ?>

                    <section class="exam-details-side-panel">

                        <div class="side-panel-heading">

                            <span>

                                <i
                                    class="fa-solid fa-chalkboard-user"
                                ></i>

                            </span>


                            <div>

                                <small>
                                    EXAM SET BY
                                </small>

                                <strong>

                                    <?= exam_details_escape(
                                        $exam['teacher_name']
                                    ) ?>

                                </strong>

                            </div>

                        </div>


                        <p class="teacher-note">

                            This examination was prepared
                            and published through ExamSphere.

                        </p>

                    </section>

                <?php endif; ?>


            </aside>

        </div>


        <!-- =================================================
             INSTRUCTIONS
        ================================================== -->

        <section class="exam-instructions-panel">

            <div class="exam-details-panel-heading">

                <div>

                    <span>
                        BEFORE YOU BEGIN
                    </span>

                    <h2>
                        Important examination instructions
                    </h2>

                </div>


                <i
                    class="fa-solid fa-circle-info"
                ></i>

            </div>


            <div class="exam-instructions-grid">


                <div>

                    <span>
                        01
                    </span>

                    <div>

                        <strong>
                            Read carefully
                        </strong>

                        <p>

                            Read each question and all options
                            carefully before submitting your answer.

                        </p>

                    </div>

                </div>


                <div>

                    <span>
                        02
                    </span>

                    <div>

                        <strong>
                            Watch the timer
                        </strong>

                        <p>

                            The examination uses the configured
                            duration and may submit automatically
                            when time expires.

                        </p>

                    </div>

                </div>


                <div>

                    <span>
                        03
                    </span>

                    <div>

                        <strong>
                            Save your responses
                        </strong>

                        <p>

                            Your selected responses are saved through
                            the examination system while you work.

                        </p>

                    </div>

                </div>


                <div>

                    <span>
                        04
                    </span>

                    <div>

                        <strong>
                            Submit carefully
                        </strong>

                        <p>

                            Once an examination is submitted,
                            your attempt is evaluated and cannot be edited.

                        </p>

                    </div>

                </div>


            </div>

        </section>


        <!-- =================================================
             TRUST STRIP
        ================================================== -->

        <section class="exam-details-trust-strip">

            <div>

                <i
                    class="fa-solid fa-shield-halved"
                ></i>

                <span>

                    <strong>
                        Secure examination
                    </strong>

                    <small>
                        Your attempt belongs to your student account.
                    </small>

                </span>

            </div>


            <div>

                <i
                    class="fa-solid fa-chart-line"
                ></i>

                <span>

                    <strong>
                        Instant evaluation
                    </strong>

                    <small>
                        Results are calculated after submission.
                    </small>

                </span>

            </div>


            <div>

                <i
                    class="fa-solid fa-graduation-cap"
                ></i>

                <span>

                    <strong>
                        Learn from every attempt
                    </strong>

                    <small>
                        Review your result and improve your preparation.
                    </small>

                </span>

            </div>

        </section>

    </div>

</main>


</body>

</html>