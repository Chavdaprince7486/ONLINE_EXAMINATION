<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';
require_once '../config/auth.php';

require_login('student');

$studentId = (int)($_SESSION['user_id'] ?? 0);

if ($studentId <= 0) {
    header('Location: ../auth/login.php');
    exit;
}


function profile_e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function profile_date(
    mixed $value,
    string $format = 'd M Y'
): string {

    if (
        $value === null ||
        trim((string)$value) === ''
    ) {
        return '—';
    }

    try {

        return (
            new DateTimeImmutable(
                (string)$value
            )
        )->format($format);

    } catch (Throwable) {

        return '—';
    }
}


function profile_percent(
    mixed $value
): string {

    return number_format(
        max(
            0,
            min(
                100,
                (float)($value ?? 0)
            )
        ),
        1
    );
}


$student = null;

$stats = [
    'attempted' => 0,
    'completed' => 0,
    'average_score' => 0,
    'best_score' => 0,
    'attempted_questions' => 0,
    'correct_answers' => 0,
    'wrong_answers' => 0,
    'unanswered_questions' => 0
];

$subscription = null;

$pageError = '';

$csrfToken = csrf_token();


try {

    /*
    |--------------------------------------------------------------------------
    | STUDENT
    |--------------------------------------------------------------------------
    */

    $studentQuery =
        $conn->prepare(
            "
            SELECT

                id,
                student_code,
                full_name,
                email,
                mobile,
                gender,
                dob,
                address,
                city,
                state,
                pincode,
                profile_photo,
                email_verified,
                status,
                last_login,
                created_at

            FROM students

            WHERE id = ?

            LIMIT 1
            "
        );


    $studentQuery->execute([
        $studentId
    ]);


    $student =
        $studentQuery->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$student ||
        (string)$student['status'] !== 'Active'
    ) {

        session_destroy();

        header(
            'Location: ../auth/login.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | RESULT STATS
    |--------------------------------------------------------------------------
    */

    $statsQuery =
        $conn->prepare(
            "
            SELECT

                COUNT(*) AS attempted,

                COALESCE(
                    SUM(
                        CASE
                            WHEN result_status
                                IN ('Pass','Fail')
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS completed,

                COALESCE(
                    AVG(
                        CASE
                            WHEN result_status
                                IN ('Pass','Fail')
                            THEN percentage
                            ELSE NULL
                        END
                    ),
                    0
                ) AS average_score,

                COALESCE(
                    MAX(
                        CASE
                            WHEN result_status
                                IN ('Pass','Fail')
                            THEN percentage
                            ELSE NULL
                        END
                    ),
                    0
                ) AS best_score,

                COALESCE(
                    SUM(attempted_questions),
                    0
                ) AS attempted_questions,

                COALESCE(
                    SUM(correct_answers),
                    0
                ) AS correct_answers,

                COALESCE(
                    SUM(wrong_answers),
                    0
                ) AS wrong_answers,

                COALESCE(
                    SUM(unanswered_questions),
                    0
                ) AS unanswered_questions

            FROM results

            WHERE student_id = ?
            "
        );


    $statsQuery->execute([
        $studentId
    ]);


    $statsRow =
        $statsQuery->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        is_array($statsRow)
    ) {

        $stats =
            array_merge(
                $stats,
                $statsRow
            );
    }


    /*
    |--------------------------------------------------------------------------
    | ACTIVE SUBSCRIPTION
    |--------------------------------------------------------------------------
    */

    $subscriptionQuery =
        $conn->prepare(
            "
            SELECT

                s.id,
                s.start_date,
                s.end_date,
                s.status,

                p.name AS plan_name,
                p.duration_months,
                p.price,
                p.description,
                p.benefits

            FROM subscriptions s

            INNER JOIN subscription_plans p
                ON p.id = s.plan_id

            WHERE

                s.student_id = ?

                AND s.status = 'Active'

                AND s.start_date <= CURDATE()

                AND s.end_date >= CURDATE()

            ORDER BY

                s.end_date DESC,
                s.id DESC

            LIMIT 1
            "
        );


    $subscriptionQuery->execute([
        $studentId
    ]);


    $subscription =
        $subscriptionQuery->fetch(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $exception) {

    error_log(
        'ExamSphere profile load failed: ' .
        $exception->getMessage()
    );

    $pageError =
        'Unable to load some profile information right now.';
}


/*
|--------------------------------------------------------------------------
| PROFILE PHOTO
|--------------------------------------------------------------------------
*/

$profilePhoto =
    '../assets/images/default-user.png';


if (
    is_array($student) &&
    !empty($student['profile_photo'])
) {

    $photoName =
        basename(
            (string)$student['profile_photo']
        );


    $photoPath =
        dirname(__DIR__) .
        '/uploads/students/' .
        $photoName;


    if (
        $photoName !== '' &&
        is_file($photoPath)
    ) {

        $profilePhoto =
            '../uploads/students/' .
            rawurlencode($photoName);
    }
}


/*
|--------------------------------------------------------------------------
| VALUES
|--------------------------------------------------------------------------
*/

$attempted =
    (int)($stats['attempted'] ?? 0);

$completed =
    (int)($stats['completed'] ?? 0);

$averageScore =
    (float)($stats['average_score'] ?? 0);

$bestScore =
    (float)($stats['best_score'] ?? 0);

$attemptedQuestions =
    (int)($stats['attempted_questions'] ?? 0);

$correctAnswers =
    (int)($stats['correct_answers'] ?? 0);

$wrongAnswers =
    (int)($stats['wrong_answers'] ?? 0);

$unansweredQuestions =
    (int)($stats['unanswered_questions'] ?? 0);


$accuracy =
    $attemptedQuestions > 0
        ? (
            $correctAnswers /
            $attemptedQuestions
        ) * 100
        : 0;


/*
|--------------------------------------------------------------------------
| PROFILE COMPLETENESS
|--------------------------------------------------------------------------
*/

$profileFields = [

    $student['full_name'] ?? '',
    $student['email'] ?? '',
    $student['mobile'] ?? '',
    $student['gender'] ?? '',
    $student['dob'] ?? '',
    $student['address'] ?? '',
    $student['city'] ?? '',
    $student['state'] ?? '',
    $student['pincode'] ?? ''

];


$filledFields = 0;


foreach (
    $profileFields
    as $field
) {

    if (
        trim((string)$field) !== ''
    ) {

        $filledFields++;
    }
}


$profileCompletion =
    round(
        (
            $filledFields /
            count($profileFields)
        ) * 100
    );


/*
|--------------------------------------------------------------------------
| SUBSCRIPTION PROGRESS
|--------------------------------------------------------------------------
*/

$subscriptionRemaining = 0;

$subscriptionProgress = 0;


if (
    is_array($subscription)
) {

    try {

        $startDate =
            new DateTimeImmutable(
                (string)$subscription['start_date']
            );


        $endDate =
            new DateTimeImmutable(
                (string)$subscription['end_date']
            );


        $today =
            new DateTimeImmutable(
                'today'
            );


        if (
            $today >= $startDate &&
            $today <= $endDate
        ) {

            $subscriptionRemaining =
                $today->diff(
                    $endDate
                )->days + 1;
        }


        $totalDays =
            max(
                1,
                $startDate
                    ->diff(
                        $endDate
                    )
                    ->days + 1
            );


        $usedDays =
            $today > $startDate
                ? min(
                    $totalDays,
                    $startDate
                        ->diff(
                            $today
                        )
                        ->days + 1
                )
                : 0;


        $subscriptionProgress =
            round(
                (
                    $usedDays /
                    $totalDays
                ) * 100,
                1
            );

    } catch (Throwable) {

        $subscriptionRemaining =
            0;

        $subscriptionProgress =
            0;
    }
}

?>

<!doctype html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<meta
    name="theme-color"
    content="#F5F5DC"
>

<meta
    name="csrf-token"
    content="<?= profile_e($csrfToken) ?>"
>


<title>
    My Profile | ExamSphere
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
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet"
>


<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
>


<link
    rel="stylesheet"
    href="assets/css/student-nav.css"
>


<link
    rel="stylesheet"
    href="assets/css/profile.css"
>

</head>


<body>


<?php include 'includes/navbar.php'; ?>


<main class="profile-page">


<!-- =====================================================
     HERO
====================================================== -->

<section class="profile-hero">

    <div class="hero-ring hero-ring-one"></div>

    <div class="hero-ring hero-ring-two"></div>


    <div class="profile-hero-copy">

        <div class="profile-eyebrow">

            <span></span>

            STUDENT ACCOUNT

        </div>


        <h1>

            Your profile.

            <span>
                Your journey.
            </span>

        </h1>


        <p>

            Manage your identity, preparation progress,
            membership and account information from your
            personal ExamSphere command center.

        </p>


        <div class="hero-actions">

            <a
                href="profile_edit.php"
                class="hero-btn hero-btn-primary"
            >

                <i class="fa-solid fa-pen"></i>

                Edit Profile

            </a>


            <a
                href="performance.php"
                class="hero-btn hero-btn-secondary"
            >

                <i class="fa-solid fa-chart-line"></i>

                View Performance

            </a>

        </div>

    </div>


    <div class="profile-health">

        <div class="health-head">

            <span>
                PROFILE HEALTH
            </span>

            <i
                class="
                    fa-solid
                    fa-shield-halved
                "
            ></i>

        </div>


        <strong class="health-number">

            <?= $profileCompletion ?>%

        </strong>


        <div class="health-title">
            Profile completeness
        </div>


        <div class="health-text">

            Keep your profile updated for
            a better ExamSphere experience.

        </div>


        <div class="health-track">

            <div
                class="health-fill"
                style="
                    width:
                    <?= $profileCompletion ?>%;
                "
            ></div>

        </div>


        <a
            href="profile_edit.php"
            class="health-link"
        >

            Complete profile

            <i
                class="
                    fa-solid
                    fa-arrow-right
                "
            ></i>

        </a>

    </div>

</section>


<?php if (
    $pageError !== ''
): ?>

<div class="profile-error">

    <i
        class="
            fa-solid
            fa-triangle-exclamation
        "
    ></i>

    <?= profile_e($pageError) ?>

</div>

<?php endif; ?>


<!-- =====================================================
     IDENTITY
====================================================== -->

<section class="identity-card">

    <div class="identity-top-line"></div>


    <div class="identity-body">


        <div class="identity-avatar-wrap">


            <img
                id="profilePreview"
                class="identity-avatar"
                src="<?= profile_e(
                    $profilePhoto
                ) ?>"
                alt="Student profile photo"
            >


            <label
                for="profilePhoto"
                class="photo-upload-button"
                title="Upload profile photo"
            >

                <i
                    class="
                        fa-solid
                        fa-camera
                    "
                ></i>

            </label>


            <input
                type="file"
                id="profilePhoto"
                accept="
                    image/jpeg,
                    image/png,
                    image/webp
                "
                hidden
            >

        </div>


        <div class="identity-information">


            <div class="student-status">

                <span></span>

                ACTIVE STUDENT

            </div>


            <h2>

                <?= profile_e(
                    $student['full_name']
                ) ?>

            </h2>


            <div class="student-code">

                <i
                    class="
                        fa-solid
                        fa-id-badge
                    "
                ></i>

                <?= profile_e(
                    $student['student_code']
                ) ?>

            </div>


            <div class="contact-row">


                <span>

                    <i
                        class="
                            fa-solid
                            fa-envelope
                        "
                    ></i>

                    <?= profile_e(
                        $student['email']
                    ) ?>

                </span>


                <span>

                    <i
                        class="
                            fa-solid
                            fa-phone
                        "
                    ></i>

                    <?= profile_e(
                        $student['mobile']
                        ?: 'Mobile not added'
                    ) ?>

                </span>


                <?php if (
                    !empty(
                        $student['city']
                    )
                ): ?>

                <span>

                    <i
                        class="
                            fa-solid
                            fa-location-dot
                        "
                    ></i>

                    <?= profile_e(
                        $student['city']
                    ) ?>

                </span>

                <?php endif; ?>


            </div>

        </div>


        <a
            href="profile_edit.php"
            class="identity-edit"
        >

            <i
                class="
                    fa-solid
                    fa-pen
                "
            ></i>

            Edit Profile

        </a>


    </div>


    <!-- =================================================
         METRICS
    ================================================== -->

    <div class="metric-grid">


        <div class="metric-card">

            <div class="
                metric-icon
                metric-icon-brown
            ">

                <i
                    class="
                        fa-solid
                        fa-file-circle-check
                    "
                ></i>

            </div>


            <div>

                <small>
                    Exams Attempted
                </small>

                <strong>
                    <?= $attempted ?>
                </strong>

                <span>
                    total attempts
                </span>

            </div>

        </div>


        <div class="metric-card">

            <div class="
                metric-icon
                metric-icon-olive
            ">

                <i
                    class="
                        fa-solid
                        fa-circle-check
                    "
                ></i>

            </div>


            <div>

                <small>
                    Completed
                </small>

                <strong>
                    <?= $completed ?>
                </strong>

                <span>
                    verified results
                </span>

            </div>

        </div>


        <div class="metric-card">

            <div class="
                metric-icon
                metric-icon-gold
            ">

                <i
                    class="
                        fa-solid
                        fa-chart-line
                    "
                ></i>

            </div>


            <div>

                <small>
                    Average Score
                </small>

                <strong>

                    <?= profile_percent(
                        $averageScore
                    ) ?>%

                </strong>

                <span>
                    across results
                </span>

            </div>

        </div>


        <div class="metric-card">

            <div class="
                metric-icon
                metric-icon-purple
            ">

                <i
                    class="
                        fa-solid
                        fa-trophy
                    "
                ></i>

            </div>


            <div>

                <small>
                    Best Score
                </small>

                <strong>

                    <?= profile_percent(
                        $bestScore
                    ) ?>%

                </strong>

                <span>
                    personal best
                </span>

            </div>

        </div>


    </div>

</section>


<!-- =====================================================
     CONTENT
====================================================== -->

<section class="profile-content">


<div class="profile-left">


<!-- =================================================
     PERSONAL
================================================== -->

<article class="surface-card">


<div class="surface-head">

    <div>

        <span class="section-kicker">
            PERSONAL INFORMATION
        </span>

        <h3>
            About you
        </h3>

        <p>
            Your registered ExamSphere information.
        </p>

    </div>


    <a
        href="profile_edit.php"
        class="surface-link"
    >

        Edit

        <i
            class="
                fa-solid
                fa-arrow-right
            "
        ></i>

    </a>

</div>


<div class="info-grid">


    <div class="info-item">

        <span>
            Full Name
        </span>

        <strong>

            <?= profile_e(
                $student['full_name']
            ) ?>

        </strong>

    </div>


    <div class="info-item">

        <span>
            Student Code
        </span>

        <strong>

            <?= profile_e(
                $student['student_code']
            ) ?>

        </strong>

    </div>


    <div class="info-item">

        <span>
            Email Address
        </span>

        <strong>

            <?= profile_e(
                $student['email']
            ) ?>

        </strong>

    </div>


    <div class="info-item">

        <span>
            Mobile Number
        </span>

        <strong>

            <?= profile_e(
                $student['mobile']
                ?: 'Not added'
            ) ?>

        </strong>

    </div>


    <div class="info-item">

        <span>
            Gender
        </span>

        <strong>

            <?= profile_e(
                $student['gender']
                ?: 'Not specified'
            ) ?>

        </strong>

    </div>


    <div class="info-item">

        <span>
            Date of Birth
        </span>

        <strong>

            <?= profile_date(
                $student['dob']
                ?? null
            ) ?>

        </strong>

    </div>


    <div class="info-item">

        <span>
            City
        </span>

        <strong>

            <?= profile_e(
                $student['city']
                ?: 'Not added'
            ) ?>

        </strong>

    </div>


    <div class="info-item">

        <span>
            State
        </span>

        <strong>

            <?= profile_e(
                $student['state']
                ?: 'Not added'
            ) ?>

        </strong>

    </div>


    <div class="info-item">

        <span>
            Pincode
        </span>

        <strong>

            <?= profile_e(
                $student['pincode']
                ?: 'Not added'
            ) ?>

        </strong>

    </div>


    <div class="
        info-item
        info-wide
    ">

        <span>
            Address
        </span>

        <strong>

            <?= profile_e(
                $student['address']
                ?: 'No address added'
            ) ?>

        </strong>

    </div>


</div>


</article>


<!-- =================================================
     PERFORMANCE
================================================== -->

<article class="surface-card">


<div class="surface-head">

    <div>

        <span class="section-kicker">
            PERFORMANCE
        </span>

        <h3>
            Your preparation snapshot
        </h3>

        <p>
            A live overview based on your completed results.
        </p>

    </div>


    <a
        href="performance.php"
        class="surface-link"
    >

        Full Analytics

        <i
            class="
                fa-solid
                fa-arrow-right
            "
        ></i>

    </a>

</div>


<div class="performance-layout">


    <div
        class="accuracy-ring"
        style="
            --score:
            <?= max(
                0,
                min(
                    100,
                    $accuracy
                )
            ) ?>%;
        "
    >

        <div class="accuracy-center">

            <strong>

                <?= profile_percent(
                    $accuracy
                ) ?>%

            </strong>

            <span>
                Accuracy
            </span>

        </div>

    </div>


    <div class="performance-list">


        <div class="performance-line">

            <div>

                <span>
                    Correct Answers
                </span>

                <strong>
                    <?= $correctAnswers ?>
                </strong>

            </div>


            <div class="performance-track">

                <div
                    class="
                        performance-fill
                        performance-correct
                    "
                    style="
                        width:
                        <?= $attemptedQuestions > 0
                            ? min(
                                100,
                                (
                                    $correctAnswers /
                                    $attemptedQuestions
                                ) * 100
                            )
                            : 0
                        ?>%;
                    "
                ></div>

            </div>

        </div>


        <div class="performance-line">

            <div>

                <span>
                    Wrong Answers
                </span>

                <strong>
                    <?= $wrongAnswers ?>
                </strong>

            </div>


            <div class="performance-track">

                <div
                    class="
                        performance-fill
                        performance-wrong
                    "
                    style="
                        width:
                        <?= $attemptedQuestions > 0
                            ? min(
                                100,
                                (
                                    $wrongAnswers /
                                    $attemptedQuestions
                                ) * 100
                            )
                            : 0
                        ?>%;
                    "
                ></div>

            </div>

        </div>


        <div class="performance-line">

            <div>

                <span>
                    Unanswered
                </span>

                <strong>
                    <?= $unansweredQuestions ?>
                </strong>

            </div>


            <div class="performance-track">

                <div
                    class="
                        performance-fill
                        performance-unanswered
                    "
                    style="
                        width:
                        <?= $attemptedQuestions > 0
                            ? min(
                                100,
                                (
                                    $unansweredQuestions /
                                    $attemptedQuestions
                                ) * 100
                            )
                            : 0
                        ?>%;
                    "
                ></div>

            </div>

        </div>


    </div>


</div>


</article>


</div>


<!-- =================================================
     RIGHT
================================================== -->

<aside class="profile-right">


<!-- =================================================
     MEMBERSHIP
================================================== -->

<?php if (
    is_array($subscription)
): ?>


<article class="membership-card">


    <div
        class="membership-glow"
    ></div>


    <div class="membership-header">


        <div>

            <span>
                MEMBERSHIP
            </span>


            <div
                class="membership-status"
            >

                <i
                    class="
                        fa-solid
                        fa-circle
                    "
                ></i>

                Active

            </div>

        </div>


        <div
            class="membership-icon"
        >

            <i
                class="
                    fa-solid
                    fa-gem
                "
            ></i>

        </div>


    </div>


    <h3>

        <?= profile_e(
            $subscription[
                'plan_name'
            ]
        ) ?>

    </h3>


    <p class="membership-description">

        Premium ExamSphere membership

    </p>


    <div class="membership-date-grid">


        <div>

            <span>
                STARTED
            </span>

            <strong>

                <?= profile_date(
                    $subscription[
                        'start_date'
                    ]
                ) ?>

            </strong>

        </div>


        <div>

            <span>
                EXPIRES
            </span>

            <strong>

                <?= profile_date(
                    $subscription[
                        'end_date'
                    ]
                ) ?>

            </strong>

        </div>


    </div>


    <div class="membership-progress">


        <div>

            <span>
                Membership progress
            </span>

            <strong>

                <?= profile_percent(
                    $subscriptionProgress
                ) ?>%

            </strong>

        </div>


        <div class="
            membership-progress-track
        ">

            <div
                class="
                    membership-progress-fill
                "
                style="
                    width:
                    <?= min(
                        100,
                        max(
                            0,
                            $subscriptionProgress
                        )
                    ) ?>%;
                "
            ></div>

        </div>

    </div>


    <div
        class="membership-bottom"
    >

        <span>

            <i
                class="
                    fa-solid
                    fa-clock
                "
            ></i>

            <?= $subscriptionRemaining ?>

            day<?= $subscriptionRemaining === 1
                ? ''
                : 's' ?>

            remaining

        </span>


        <a
            href="subscriptions.php"
        >

            Manage

            <i
                class="
                    fa-solid
                    fa-arrow-right
                "
            ></i>

        </a>

    </div>


</article>


<?php else: ?>


<article
    class="
        membership-card
        membership-free
    "
>


    <div class="membership-header">


        <span>
            MEMBERSHIP
        </span>


        <div
            class="membership-icon"
        >

            <i
                class="
                    fa-solid
                    fa-gem
                "
            ></i>

        </div>


    </div>


    <h3>
        Free Account
    </h3>


    <p class="membership-description">

        Practice exams remain free.
        Subscribe to unlock eligible Live Exams
        and subscription-only study materials.

    </p>


    <a
        href="subscriptions.php"
        class="membership-cta"
    >

        Explore Plans

        <i
            class="
                fa-solid
                fa-arrow-right
            "
        ></i>

    </a>


</article>


<?php endif; ?>


<!-- =================================================
     ACCOUNT STATUS
================================================== -->

<article class="
    surface-card
    compact-card
">


    <div class="compact-head">


        <div>

            <span class="section-kicker">
                ACCOUNT
            </span>

            <h3>
                Account status
            </h3>

        </div>


        <div class="compact-icon">

            <i
                class="
                    fa-solid
                    fa-shield-halved
                "
            ></i>

        </div>


    </div>


    <div class="status-list">


        <div class="status-row">

            <span>
                Account
            </span>

            <strong class="status-active">

                <i
                    class="
                        fa-solid
                        fa-circle
                    "
                ></i>

                Active

            </strong>

        </div>


        <div class="status-row">

            <span>
                Email verification
            </span>

            <strong
                class="<?= (
                    (string)(
                        $student[
                            'email_verified'
                        ] ?? ''
                    ) === 'Yes'
                )
                    ? 'status-ok'
                    : 'status-pending' ?>"
            >

                <?= (
                    (string)(
                        $student[
                            'email_verified'
                        ] ?? ''
                    ) === 'Yes'
                )
                    ? 'Verified'
                    : 'Pending' ?>

            </strong>

        </div>


        <div class="status-row">

            <span>
                Member since
            </span>

            <strong>

                <?= profile_date(
                    $student[
                        'created_at'
                    ] ?? null
                ) ?>

            </strong>

        </div>


        <div class="status-row">

            <span>
                Last login
            </span>

            <strong>

                <?= profile_date(
                    $student[
                        'last_login'
                    ] ?? null,
                    'd M Y, h:i A'
                ) ?>

            </strong>

        </div>


    </div>


</article>


<!-- =================================================
     SECURITY
================================================== -->

<article class="
    surface-card
    compact-card
">


    <div class="compact-head">


        <div>

            <span class="section-kicker">
                SECURITY
            </span>

            <h3>
                Account protection
            </h3>

        </div>


        <div class="security-icon">

            <i
                class="
                    fa-solid
                    fa-lock
                "
            ></i>

        </div>


    </div>


    <div class="security-panel">


        <div class="security-panel-icon">

            <i
                class="
                    fa-solid
                    fa-shield
                "
            ></i>

        </div>


        <div>

            <strong>
                Secure account access
            </strong>

            <p>

                Keep your credentials private
                and use a strong password.

            </p>

        </div>


    </div>


    <div class="security-note">

        <i
            class="
                fa-solid
                fa-circle-info
            "
        ></i>

        Change Password is available
        directly from your navbar profile menu.

    </div>


</article>


<!-- =================================================
     QUICK ACCESS
================================================== -->

<article class="
    surface-card
    compact-card
">


    <div class="compact-head">


        <div>

            <span class="section-kicker">
                QUICK ACCESS
            </span>

            <h3>
                Continue learning
            </h3>

        </div>


    </div>


    <a
        href="practice_exams.php"
        class="quick-action"
    >

        <span
            class="
                quick-icon
                quick-brown
            "
        >

            <i
                class="
                    fa-solid
                    fa-file-pen
                "
            ></i>

        </span>


        <span
            class="quick-text"
        >

            <strong>
                Practice Exams
            </strong>

            <small>
                Improve your preparation
            </small>

        </span>


        <i
            class="
                fa-solid
                fa-arrow-right
                quick-arrow
            "
        ></i>

    </a>


    <a
        href="live_exams.php"
        class="quick-action"
    >

        <span
            class="
                quick-icon
                quick-olive
            "
        >

            <i
                class="
                    fa-solid
                    fa-tower-broadcast
                "
            ></i>

        </span>


        <span
            class="quick-text"
        >

            <strong>
                Live Exams
            </strong>

            <small>
                View scheduled exams
            </small>

        </span>


        <i
            class="
                fa-solid
                fa-arrow-right
                quick-arrow
            "
        ></i>

    </a>


    <a
        href="materials.php"
        class="quick-action"
    >

        <span
            class="
                quick-icon
                quick-gold
            "
        >

            <i
                class="
                    fa-solid
                    fa-book-open
                "
            ></i>

        </span>


        <span
            class="quick-text"
        >

            <strong>
                Study Materials
            </strong>

            <small>
                Continue your study
            </small>

        </span>


        <i
            class="
                fa-solid
                fa-arrow-right
                quick-arrow
            "
        ></i>

    </a>


    <a
        href="results.php"
        class="quick-action"
    >

        <span
            class="
                quick-icon
                quick-purple
            "
        >

            <i
                class="
                    fa-solid
                    fa-chart-column
                "
            ></i>

        </span>


        <span
            class="quick-text"
        >

            <strong>
                Results
            </strong>

            <small>
                Review completed exams
            </small>

        </span>


        <i
            class="
                fa-solid
                fa-arrow-right
                quick-arrow
            "
        ></i>

    </a>


</article>


</aside>


</section>


</main>


<!-- =====================================================
     PASSWORD MODAL
====================================================== -->

<div
    id="profilePasswordModal"
    class="password-modal"
    aria-hidden="true"
>


    <div class="password-modal-card">


        <div
            class="
                password-modal-header
            "
        >


            <div
                class="
                    password-title-wrap
                "
            >

                <div
                    class="
                        password-title-icon
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-key
                        "
                    ></i>

                </div>


                <div>

                    <span>
                        ACCOUNT SECURITY
                    </span>

                    <h3>
                        Change Password
                    </h3>

                    <p>
                        Protect your ExamSphere account.
                    </p>

                </div>

            </div>


            <button
                type="button"
                class="password-close"
                id="passwordModalClose"
            >

                <i
                    class="
                        fa-solid
                        fa-xmark
                    "
                ></i>

            </button>


        </div>


        <form
            id="passwordForm"
            class="password-form"
        >


            <input
                type="hidden"
                name="csrf_token"
                value="<?= profile_e(
                    $csrfToken
                ) ?>"
            >


            <div class="password-field">


                <label>
                    Current Password
                </label>


                <div
                    class="
                        password-input-wrap
                    "
                >

                    <input
                        id="currentPassword"
                        name="current_password"
                        type="password"
                        class="
                            password-input
                        "
                        autocomplete="current-password"
                        required
                    >


                    <button
                        type="button"
                        class="password-eye"
                        data-password-target="currentPassword"
                    >

                        <i
                            class="
                                fa-solid
                                fa-eye
                            "
                        ></i>

                    </button>


                </div>


            </div>


            <div class="password-field">


                <label>
                    New Password
                </label>


                <div
                    class="
                        password-input-wrap
                    "
                >

                    <input
                        id="newPassword"
                        name="new_password"
                        type="password"
                        class="
                            password-input
                        "
                        minlength="8"
                        maxlength="72"
                        autocomplete="new-password"
                        required
                    >


                    <button
                        type="button"
                        class="password-eye"
                        data-password-target="newPassword"
                    >

                        <i
                            class="
                                fa-solid
                                fa-eye
                            "
                        ></i>

                    </button>


                </div>


            </div>


            <div
                id="passwordStrength"
                class="password-strength"
            >

                <div
                    class="
                        password-strength-head
                    "
                >

                    <span>
                        Password strength
                    </span>


                    <strong
                        id="passwordStrengthText"
                    >
                        —
                    </strong>

                </div>


                <div
                    class="
                        password-strength-track
                    "
                >

                    <div
                        id="passwordStrengthFill"
                        class="
                            password-strength-fill
                        "
                    ></div>

                </div>

            </div>


            <div class="password-field">


                <label>
                    Confirm New Password
                </label>


                <div
                    class="
                        password-input-wrap
                    "
                >

                    <input
                        id="confirmPassword"
                        name="confirm_password"
                        type="password"
                        class="
                            password-input
                        "
                        minlength="8"
                        maxlength="72"
                        autocomplete="new-password"
                        required
                    >


                    <button
                        type="button"
                        class="password-eye"
                        data-password-target="confirmPassword"
                    >

                        <i
                            class="
                                fa-solid
                                fa-eye
                            "
                        ></i>

                    </button>

                </div>


            </div>


            <div class="password-hint">

                <i
                    class="
                        fa-solid
                        fa-shield-halved
                    "
                ></i>

                Minimum 8 characters with
                at least one letter and one number.

            </div>


            <div
                class="
                    password-modal-actions
                "
            >


                <button
                    type="button"
                    class="
                        password-button
                        password-cancel
                    "
                    id="passwordCancel"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    class="
                        password-button
                        password-submit
                    "
                    id="passwordSubmit"
                >

                    <i
                        class="
                            fa-solid
                            fa-key
                        "
                    ></i>

                    Update Password

                </button>


            </div>


        </form>


    </div>

</div>


<script>

window.EXAMSPHERE_CSRF_TOKEN =

<?= json_encode(
    $csrfToken,
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
) ?>;

</script>


<script
    src="
        https://cdn.jsdelivr.net/npm/sweetalert2@11
    "
></script>


<script
    src="assets/js/profile.js"
    defer
></script>


</body>

</html>