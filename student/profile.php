<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';


/*
|--------------------------------------------------------------------------
| STUDENT AUTH
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {
    header('Location: ../auth/login.php');
    exit;
}


$studentId = (int)$_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| ESCAPE HELPER
|--------------------------------------------------------------------------
*/

$e = static function (mixed $value): string {

    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};


/*
|--------------------------------------------------------------------------
| LOAD STUDENT
|--------------------------------------------------------------------------
*/

try {

    $statement = $conn->prepare(
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
            created_at,
            last_login

        FROM students

        WHERE
            id = ?

            AND status = 'Active'

        LIMIT 1
        "
    );


    $statement->execute([
        $studentId
    ]);


    $student =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$student
    ) {

        $_SESSION = [];

        session_destroy();

        header(
            'Location: ../auth/login.php'
        );

        exit;
    }


} catch (Throwable $exception) {

    error_log(
        'Student profile load failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load your profile.'
    );
}


/*
|--------------------------------------------------------------------------
| PROFILE STATISTICS
|--------------------------------------------------------------------------
*/

$stats = [
    'completed' => 0,
    'average_score' => 0.00,
    'best_score' => 0.00,
    'correct_answers' => 0,
    'wrong_answers' => 0
];


try {

    $statsStatement =
        $conn->prepare(
            "
            SELECT

                COUNT(*) AS completed,

                COALESCE(
                    AVG(percentage),
                    0
                ) AS average_score,

                COALESCE(
                    MAX(percentage),
                    0
                ) AS best_score,

                COALESCE(
                    SUM(correct_answers),
                    0
                ) AS correct_answers,

                COALESCE(
                    SUM(wrong_answers),
                    0
                ) AS wrong_answers

            FROM results

            WHERE
                student_id = ?
            "
        );


    $statsStatement->execute([
        $studentId
    ]);


    $statsRow =
        $statsStatement->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        is_array($statsRow)
    ) {

        $stats['completed'] =
            (int)(
                $statsRow[
                    'completed'
                ] ?? 0
            );


        $stats['average_score'] =
            round(
                (float)(
                    $statsRow[
                        'average_score'
                    ] ?? 0
                ),
                2
            );


        $stats['best_score'] =
            round(
                (float)(
                    $statsRow[
                        'best_score'
                    ] ?? 0
                ),
                2
            );


        $stats['correct_answers'] =
            (int)(
                $statsRow[
                    'correct_answers'
                ] ?? 0
            );


        $stats['wrong_answers'] =
            (int)(
                $statsRow[
                    'wrong_answers'
                ] ?? 0
            );
    }


} catch (Throwable $exception) {

    error_log(
        'Student profile statistics failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| BEST SCORE RANK
|--------------------------------------------------------------------------
*/

$rank = 0;


try {

    $rankStatement =
        $conn->prepare(
            "
            SELECT
                COUNT(*) + 1

            FROM (
                SELECT
                    student_id,
                    MAX(percentage)
                    AS best_percentage

                FROM results

                GROUP BY
                    student_id
            ) ranked

            WHERE
                ranked.best_percentage >

                (
                    SELECT
                        COALESCE(
                            MAX(percentage),
                            0
                        )

                    FROM results

                    WHERE
                        student_id = ?
                )
            "
        );


    $rankStatement->execute([
        $studentId
    ]);


    $rank =
        (int)(
            $rankStatement->fetchColumn()
            ?: 0
        );


} catch (Throwable $exception) {

    error_log(
        'Student profile rank query failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| ACTIVE SUBSCRIPTION
|--------------------------------------------------------------------------
*/

$activeSubscription = null;


try {

    $subscriptionStatement =
        $conn->prepare(
            "
            SELECT

                p.name,
                s.end_date

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


    $subscriptionStatement->execute([
        $studentId
    ]);


    $activeSubscription =
        $subscriptionStatement->fetch(
            PDO::FETCH_ASSOC
        ) ?: null;


} catch (Throwable $exception) {

    error_log(
        'Student profile subscription query failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| PROFILE PHOTO
|--------------------------------------------------------------------------
*/

$projectBaseUrl = defined('BASE_URL')
    ? rtrim((string) BASE_URL, '/') . '/'
    : '/ONLINE_EXAMINATION/';

$profilePhoto =
    $projectBaseUrl . 'student/assets/images/default-user.png';

$photoName =
    trim((string)($student['profile_photo'] ?? ''));

if ($photoName !== '') {

    $safePhotoName = basename($photoName);

    $photoFile =
        dirname(__DIR__) .
        '/uploads/students/' .
        $safePhotoName;

    if (is_file($photoFile)) {
        $profilePhoto =
            $projectBaseUrl .
            'uploads/students/' .
            rawurlencode($safePhotoName) .
            '?v=' .
            (string)filemtime($photoFile);
    }
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

$csrfToken =
    csrf_token();

?>

<!doctype html>

<html lang="en">

<head>

<meta charset="utf-8">


<meta
    name="viewport"
    content="
        width=device-width,
        initial-scale=1
    "
>


<meta
    name="csrf-token"
    content="<?= $e($csrfToken) ?>"
>


<title>
    My Profile | ExamSphere
</title>


<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>


<link
    href="
        https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap
    "
    rel="stylesheet"
>


<link
    rel="stylesheet"
    href="
        https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css
    "
>


<link
    rel="stylesheet"
    href="assets/css/dashboard.css"
>


<link
    rel="stylesheet"
    href="assets/css/profile.css"
>


</head>


<body>


<?php include 'includes/navbar.php'; ?>


<main class="es-page profile-modern">


<section class="es-hero-card">


<div>

<span class="es-kicker">

<i class="fa-solid fa-id-card"></i>

Student Account

</span>


<h1>
    My Profile
</h1>


<p>
    Keep your personal details secure,
    accurate and up to date.
</p>

</div>


<a
    class="es-btn es-btn-light"
    href="profile_edit.php"
>

<i class="fa-solid fa-pen"></i>

Edit profile

</a>


</section>


<section class="es-profile-grid">


<article
    class="es-card profile-card-main"
>


<div class="profile-cover"></div>


<div class="profile-main-inner">


<div
    class="profile-avatar-wrap"
>


<img
    id="profilePreview"
    src="<?= $e($profilePhoto) ?>"
    data-student-photo
    alt="Profile photo"
    loading="eager"
>


<label
    class="avatar-upload"
    for="profilePhoto"
    title="Change profile photo"
>


<i
    class="fa-solid fa-camera"
></i>


</label>


<input
    id="profilePhoto"
    type="file"
    accept="
        image/jpeg,
        image/png,
        image/webp
    "
    hidden
>


</div>


<div class="profile-ident">


<h2>

<?= $e(
    $student[
        'full_name'
    ]
) ?>

</h2>


<p>

<?= $e(
    $student[
        'student_code'
    ] ?: 'Student'
) ?>

</p>


<div class="profile-chips">


<span>

<i
    class="
        fa-solid
        fa-envelope
    "
></i>

<?= $e(
    $student[
        'email'
    ]
) ?>

</span>


<span>

<i
    class="
        fa-solid
        fa-phone
    "
></i>

<?= $e(
    $student[
        'mobile'
    ]
) ?>

</span>


</div>


</div>


</div>


</article>


<div
    class="
        profile-stats-grid
    "
>


<div class="es-stat">


<span>

<i
    class="
        fa-solid
        fa-file-circle-check
    "
></i>

</span>


<strong>

<?= (int)(
    $stats[
        'completed'
    ] ?? 0
) ?>

</strong>


<small>
    Completed exams
</small>


</div>


<div class="es-stat">


<span>

<i
    class="
        fa-solid
        fa-chart-line
    "
></i>

</span>


<strong>

<?= number_format(
    (float)(
        $stats[
            'average_score'
        ] ?? 0
    ),
    1
) ?>%

</strong>


<small>
    Average score
</small>


</div>


<div class="es-stat">


<span>

<i
    class="
        fa-solid
        fa-trophy
    "
></i>

</span>


<strong>

<?= $rank > 0
    ? '#' . $rank
    : '—'
?>

</strong>


<small>
    Best-score rank
</small>


</div>


<div class="es-stat">


<span>

<i
    class="
        fa-solid
        fa-medal
    "
></i>

</span>


<strong>

<?= number_format(
    (float)(
        $stats[
            'best_score'
        ] ?? 0
    ),
    1
) ?>%

</strong>


<small>
    Best score
</small>


</div>


</div>


</section>


<section
    class="
        es-profile-columns
    "
>


<article class="es-card">


<div class="es-card-head">


<div>


<span class="es-kicker">
    Personal information
</span>


<h3>
    Your details
</h3>


</div>


<a href="profile_edit.php">
    Edit
</a>


</div>


<form
    id="profileForm"
    novalidate
>


<input
    type="hidden"
    name="csrf_token"
    value="<?= $e($csrfToken) ?>"
>


<div class="es-form-grid">


<label>

Full name

<input
    name="full_name"
    maxlength="100"
    required
    value="<?= $e(
        $student[
            'full_name'
        ]
    ) ?>"
>

</label>


<label>

Mobile

<input
    name="mobile"
    inputmode="numeric"
    maxlength="15"
    required
    value="<?= $e(
        $student[
            'mobile'
        ]
    ) ?>"
>

</label>


<label>

Email

<input
    value="<?= $e(
        $student[
            'email'
        ]
    ) ?>"
    readonly
>

</label>


<label>

Student code

<input
    value="<?= $e(
        $student[
            'student_code'
        ]
    ) ?>"
    readonly
>

</label>


<label>

Gender

<select
    name="gender"
>

<option
    value=""
>
    Prefer not to say
</option>


<?php foreach (
    [
        'Male',
        'Female',
        'Other'
    ]
    as $gender
): ?>


<option
    value="<?= $e($gender) ?>"
    <?= (
        ($student['gender'] ?? '')
        ===
        $gender
    )
        ? 'selected'
        : ''
    ?>
>

<?= $e($gender) ?>

</option>


<?php endforeach; ?>


</select>

</label>


<label>

Date of birth

<input
    type="date"
    name="dob"
    value="<?= $e(
        $student[
            'dob'
        ] ?? ''
    ) ?>"
>

</label>


<label>

City

<input
    name="city"
    maxlength="80"
    value="<?= $e(
        $student[
            'city'
        ] ?? ''
    ) ?>"
>

</label>


<label>

State

<input
    name="state"
    maxlength="80"
    value="<?= $e(
        $student[
            'state'
        ] ?? ''
    ) ?>"
>

</label>


<label>

Pincode

<input
    name="pincode"
    inputmode="numeric"
    maxlength="10"
    value="<?= $e(
        $student[
            'pincode'
        ] ?? ''
    ) ?>"
>

</label>


<label class="span-2">

Address

<textarea
    name="address"
    maxlength="1000"
    rows="4"
><?= $e(
    $student[
        'address'
    ] ?? ''
) ?></textarea>

</label>


</div>


<button
    class="
        es-btn
        es-btn-primary
    "
    type="submit"
>


<i
    class="
        fa-solid
        fa-floppy-disk
    "
></i>


Save changes


</button>


</form>


</article>


<aside
    class="
        profile-side-stack
    "
>


<article class="es-card">


<div class="es-card-head">


<div>


<span class="es-kicker">
    Account
</span>


<h3>
    Account status
</h3>


</div>


</div>


<div class="detail-list">


<div>

<span>
    Status
</span>


<strong class="pill-success">

<?= $e(
    $student[
        'status'
    ]
) ?>

</strong>


</div>


<div>

<span>
    Email verification
</span>


<strong>

<?= (
    (
        $student[
            'email_verified'
        ] ?? ''
    )
    ===
    'Yes'
)
    ? 'Verified'
    : 'Not verified'
?>

</strong>


</div>


<div>

<span>
    Member since
</span>


<strong>

<?= $e(
    date(
        'd M Y',
        strtotime(
            (string)(
                $student[
                    'created_at'
                ]
            )
        )
    )
) ?>

</strong>


</div>


<div>

<span>
    Last login
</span>


<strong>

<?=
    !empty(
        $student[
            'last_login'
        ]
    )

    ? $e(
        date(
            'd M Y, h:i A',
            strtotime(
                (string)(
                    $student[
                        'last_login'
                    ]
                )
            )
        )
    )

    : '—'
?>

</strong>


</div>


</div>


</article>


<article class="es-card">


<div class="es-card-head">


<div>


<span class="es-kicker">
    Subscription
</span>


<h3>
    Current access
</h3>


</div>


<a href="subscriptions.php">
    Plans
</a>


</div>


<?php if (
    $activeSubscription
): ?>


<div class="access-box">


<i
    class="
        fa-solid
        fa-crown
    "
></i>


<div>


<strong>

<?= $e(
    $activeSubscription[
        'name'
    ]
) ?>

</strong>


<small>

Active until

<?= $e(
    date(
        'd M Y',
        strtotime(
            (string)(
                $activeSubscription[
                    'end_date'
                ]
            )
        )
    )
) ?>

</small>


</div>


</div>


<?php else: ?>


<div class="empty-mini">


<i
    class="
        fa-solid
        fa-circle-info
    "
></i>


<span>

No active subscription.

<a href="subscriptions.php">
    Explore plans
</a>

</span>


</div>


<?php endif; ?>


</article>


<article class="es-card">


<div class="es-card-head">


<div>


<span class="es-kicker">
    Security
</span>


<h3>
    Password
</h3>


</div>


</div>


<p class="muted-copy">

Use a strong password and never share
your login credentials.

</p>


<a
    class="
        es-btn
        es-btn-secondary
        w-100
    "
    href="settings.php"
>


<i
    class="
        fa-solid
        fa-shield-halved
    "
></i>


Security settings


</a>


</article>


</aside>


</section>


</main>


<?php include 'includes/footer.php'; ?>


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