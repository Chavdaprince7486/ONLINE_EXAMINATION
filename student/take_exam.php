<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {
    header('Location: ../auth/login.php');
    exit;
}

$studentId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| ATTEMPT ID
|--------------------------------------------------------------------------
*/

$attemptId = filter_input(
    INPUT_GET,
    'attempt_id',
    FILTER_VALIDATE_INT
);

if (
    $attemptId === false ||
    $attemptId === null ||
    $attemptId <= 0
) {
    http_response_code(400);
    exit('Invalid examination attempt.');
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function take_exam_escape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| LOAD ATTEMPT
|--------------------------------------------------------------------------
*/

try {

    $attemptStatement = $conn->prepare("
        SELECT

            ea.id AS attempt_id,
            ea.student_id,
            ea.exam_id,

            ea.started_at,
            ea.server_deadline,
            ea.submitted_at,
            ea.last_activity_at,

            ea.status,

            ea.obtained_marks,
            ea.percentage,

            e.title AS exam_title,
            e.description AS exam_description,

            e.exam_type,
            e.status AS exam_status,

            e.duration_minutes,

            e.required_question_count,

            e.total_marks,
            e.passing_marks,

            e.negative_marking,

            e.exam_fee,
            e.subscription_required,

            e.starts_at,
            e.ends_at

        FROM exam_attempts ea

        INNER JOIN exams e
            ON e.id = ea.exam_id

        WHERE
            ea.id = ?
            AND ea.student_id = ?

        LIMIT 1
    ");

    $attemptStatement->execute([
        $attemptId,
        $studentId
    ]);

    $attempt = $attemptStatement->fetch(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $exception) {

    error_log(
        'Take exam attempt query failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load the examination.'
    );
}


if (!$attempt) {

    http_response_code(404);

    exit(
        'Examination attempt not found.'
    );
}


/*
|--------------------------------------------------------------------------
| NON-ACTIVE ATTEMPT
|--------------------------------------------------------------------------
*/

if (
    (string) $attempt['status'] !== 'Started'
) {

    try {

        $resultStatement = $conn->prepare("
            SELECT id

            FROM results

            WHERE
                attempt_id = ?

            LIMIT 1
        ");

        $resultStatement->execute([
            $attemptId
        ]);

        $resultId =
            $resultStatement->fetchColumn();

        if (
            $resultId !== false &&
            $resultId !== null
        ) {

            header(
                'Location: result.php?id=' .
                (int) $resultId
            );

            exit;
        }

    } catch (Throwable) {
    }


    exit(
        'This examination attempt is no longer active.'
    );
}


/*
|--------------------------------------------------------------------------
| SERVER DEADLINE
|--------------------------------------------------------------------------
*/

$deadline = null;

if (
    !empty(
        $attempt['server_deadline']
    )
) {

    try {

        $deadline =
            new DateTimeImmutable(
                (string) $attempt['server_deadline']
            );

    } catch (Throwable) {

        $deadline =
            null;
    }
}


/*
|--------------------------------------------------------------------------
| LEGACY FALLBACK
|--------------------------------------------------------------------------
*/

if (
    $deadline === null
) {

    try {

        $startedAt =
            new DateTimeImmutable(
                (string) $attempt['started_at']
            );

        $deadline =
            $startedAt->modify(
                '+' .
                (int) $attempt['duration_minutes'] .
                ' minutes'
            );

    } catch (Throwable) {

        http_response_code(500);

        exit(
            'This examination attempt has an invalid deadline.'
        );
    }
}


$now =
    new DateTimeImmutable();


/*
|--------------------------------------------------------------------------
| DEADLINE EXPIRED
|--------------------------------------------------------------------------
*/

if (
    $now >= $deadline
) {

    /*
    |--------------------------------------------------------------------------
    | DO NOT FINALIZE HERE
    |--------------------------------------------------------------------------
    |
    | Canonical server-side grading belongs to ajax/submit_exam.php.
    | Marking the attempt Auto Submitted before grading would prevent the
    | canonical submit transaction from processing it.
    |
    */

    $resultId = null;

    try {

        $resultStatement =
            $conn->prepare("
                SELECT id
                FROM results
                WHERE
                    attempt_id = ?
                    AND student_id = ?
                LIMIT 1
            ");

        $resultStatement->execute([
            $attemptId,
            $studentId
        ]);

        $resultId =
            $resultStatement->fetchColumn();

    } catch (Throwable $exception) {

        error_log(
            'Take exam expired result lookup failed: ' .
            $exception->getMessage()
        );
    }


    if (
        $resultId !== false &&
        $resultId !== null &&
        $resultId !== ''
    ) {

        header(
            'Location: result.php?id=' .
            (int) $resultId
        );

        exit;
    }


    $examCsrfToken =
        (string) (
            $_SESSION['exam_csrf_token']
            ?? ''
        );


    if (
        $examCsrfToken === ''
    ) {

        $_SESSION['exam_csrf_token'] =
            bin2hex(
                random_bytes(32)
            );

        $examCsrfToken =
            (string) $_SESSION['exam_csrf_token'];
    }


    $safeAttemptId =
        (int) $attemptId;

    $safeToken =
        htmlspecialchars(
            $examCsrfToken,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );


    echo '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ExamSphere | Submitting Examination</title>
<style>
:root{
    --cream:#F5F5DC;
    --brown:#5D4037;
    --dark:#3E2723;
    --muted:#6E625A;
    --border:#E4DED1;
}
*{box-sizing:border-box}
body{
    margin:0;
    min-height:100vh;
    display:grid;
    place-items:center;
    padding:24px;
    background:var(--cream);
    color:var(--dark);
    font-family:Arial,sans-serif;
}
.card{
    width:min(520px,100%);
    background:#fff;
    border:1px solid var(--border);
    border-radius:24px;
    padding:34px;
    box-shadow:0 24px 70px rgba(62,39,35,.14);
    text-align:center;
}
.spinner{
    width:48px;
    height:48px;
    margin:0 auto 18px;
    border:4px solid #E9E3D8;
    border-top-color:var(--brown);
    border-radius:50%;
    animation:spin .8s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}
h1{margin:0 0 10px;font-size:24px}
p{margin:0;color:var(--muted);line-height:1.7}
form{margin-top:22px}
button{
    min-height:48px;
    padding:0 18px;
    border:0;
    border-radius:14px;
    background:var(--brown);
    color:#fff;
    font-weight:700;
    cursor:pointer;
}
.note{
    margin-top:16px;
    font-size:13px;
    color:var(--muted);
}
</style>
</head>
<body>
<div class="card">
    <div class="spinner" aria-hidden="true"></div>
    <h1>Time is over</h1>
    <p>Your examination is being submitted and graded securely by the server.</p>

    <form id="autoSubmitForm" method="post" action="ajax/submit_exam.php">
        <input type="hidden" name="attempt_id" value="' . $safeAttemptId . '">
        <input type="hidden" name="csrf_token" value="' . $safeToken . '">
        <input type="hidden" name="auto_submit" value="1">
        <button type="submit">Continue to Result</button>
    </form>

    <div class="note">
        Keep this page open until the result page appears.
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    var form = document.getElementById("autoSubmitForm");
    if (form) {
        window.setTimeout(function () {
            form.submit();
        }, 350);
    }
});
</script>
</body>
</html>';

    exit;
}


/*
|--------------------------------------------------------------------------
| REQUIRED QUESTION COUNT
|--------------------------------------------------------------------------
*/

$requiredQuestionCount =
    (int) $attempt[
        'required_question_count'
    ];


if (
    $requiredQuestionCount <= 0
) {

    exit(
        'This examination has an invalid configured question count.'
    );
}


/*
|--------------------------------------------------------------------------
| VERIFY ACTIVE QUESTION COUNT
|--------------------------------------------------------------------------
*/

try {

    $questionCountStatement =
        $conn->prepare("
            SELECT
                COUNT(DISTINCT eq.question_id)

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE
                eq.exam_id = ?

                AND q.status = 'Active'
        ");

    $questionCountStatement->execute([
        (int) $attempt['exam_id']
    ]);

    $activeQuestionCount =
        (int) $questionCountStatement->fetchColumn();

} catch (Throwable $exception) {

    error_log(
        'Take exam question count failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to verify examination questions.'
    );
}


if (
    $activeQuestionCount !== $requiredQuestionCount
) {

    exit(
        'This examination is no longer ready because its active question count does not match the configured question count.'
    );
}


/*
|--------------------------------------------------------------------------
| LOAD QUESTIONS
|--------------------------------------------------------------------------
*/

try {

    $questionStatement =
        $conn->prepare("
            SELECT

                eq.question_id,
                eq.position,

                q.question_type,
                q.question_text,

                q.question_image,

                q.option_a,
                q.option_b,
                q.option_c,
                q.option_d,

                q.difficulty,
                q.marks

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE

                eq.exam_id = ?

                AND q.status = 'Active'

            ORDER BY

                eq.position ASC,
                q.id ASC
        ");

    $questionStatement->execute([
        (int) $attempt['exam_id']
    ]);

    $questions =
        $questionStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Take exam questions query failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load examination questions.'
    );
}


if (
    count($questions) !== $requiredQuestionCount
) {

    exit(
        'This examination is not ready. Its active question count does not match the configured question count.'
    );
}


/*
|--------------------------------------------------------------------------
| LOAD SAVED ANSWERS
|--------------------------------------------------------------------------
*/

$savedAnswers = [];
$savedStatuses = [];


try {

    $answerStatement =
        $conn->prepare("
            SELECT

                question_id,
                selected_answer,
                question_status

            FROM answers

            WHERE
                attempt_id = ?

            ORDER BY
                id ASC
        ");

    $answerStatement->execute([
        $attemptId
    ]);


    while (
        $answer =
            $answerStatement->fetch(
                PDO::FETCH_ASSOC
            )
    ) {

        $questionId =
            (int) $answer[
                'question_id'
            ];


        $selectedAnswer =
            strtoupper(
                trim(
                    (string) (
                        $answer[
                            'selected_answer'
                        ] ?? ''
                    )
                )
            );


        if (
            in_array(
                $selectedAnswer,
                [
                    'A',
                    'B',
                    'C',
                    'D'
                ],
                true
            )
        ) {

            $savedAnswers[
                $questionId
            ] =
                $selectedAnswer;
        }


        $savedStatuses[
            $questionId
        ] =
            (string) (
                $answer[
                    'question_status'
                ] ?? 'Not Answered'
            );
    }

} catch (Throwable $exception) {

    error_log(
        'Take exam answer state failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| BUILD FRONTEND QUESTIONS
|--------------------------------------------------------------------------
|
| Correct answers are NEVER exposed.
|--------------------------------------------------------------------------
*/

$frontendQuestions = [];


foreach (
    $questions as $index => $question
) {

    $questionId =
        (int) $question[
            'question_id'
        ];


    $options = [];


    foreach (
        [
            'A' => $question['option_a'],
            'B' => $question['option_b'],
            'C' => $question['option_c'],
            'D' => $question['option_d']
        ] as $label => $text
    ) {

        $text =
            trim(
                (string) (
                    $text ?? ''
                )
            );


        if (
            $text === ''
        ) {

            continue;
        }


        $options[] = [

            'label' =>
                $label,

            'text' =>
                $text
        ];
    }


    if (
        count($options) !== 4
    ) {

        exit(
            'This examination contains an invalid question option set.'
        );
    }


    $frontendQuestions[] = [

        'id' =>
            $questionId,

        'number' =>
            $index + 1,

        'type' =>
            (string) (
                $question[
                    'question_type'
                ] ?? 'MCQ'
            ),

        'text' =>
            (string) (
                $question[
                    'question_text'
                ] ?? ''
            ),

        'image' =>
            (string) (
                $question[
                    'question_image'
                ] ?? ''
            ),

        'difficulty' =>
            (string) (
                $question[
                    'difficulty'
                ] ?? ''
            ),

        'marks' =>
            (float) (
                $question[
                    'marks'
                ] ?? 0
            ),

        'options' =>
            $options,

        'answer' =>
            $savedAnswers[
                $questionId
            ] ?? '',

        'status' =>
            $savedStatuses[
                $questionId
            ] ?? 'Not Visited'
    ];
}


/*
|--------------------------------------------------------------------------
| EXAM CSRF
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION['exam_csrf_token']
    )
) {

    try {

        $_SESSION['exam_csrf_token'] =
            bin2hex(
                random_bytes(32)
            );

    } catch (Throwable $exception) {

        $_SESSION['exam_csrf_token'] =
            hash(
                'sha256',
                uniqid(
                    '',
                    true
                )
            );
    }
}


$csrfToken =
    (string) $_SESSION[
        'exam_csrf_token'
    ];


$deadlineTimestamp =
    $deadline->getTimestamp();


$deadlineMilliseconds =
    $deadlineTimestamp * 1000;


$initialAnswered =
    count($savedAnswers);

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
        content="#5D4037"
    >

    <title>

        <?= take_exam_escape(
            $attempt['exam_title']
        ) ?>

        | ExamSphere

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
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        rel="stylesheet"
    >


    <style>

        :root {

            --brown-dark:
                #3E2723;

            --brown:
                #5D4037;

            --olive:
                #556B2F;

            --olive-dark:
                #465925;

            --cream:
                #F5F5DC;

            --cream-light:
                #FAF9F3;

            --white:
                #FFFFFF;

            --text:
                #332D29;

            --muted:
                #7B726A;

            --border:
                #E3DED0;

            --green:
                #2E7D52;

            --red:
                #C84E4E;

            --orange:
                #D88A24;

            --shadow:
                0 20px 60px
                rgba(
                    62,
                    39,
                    35,
                    .10
                );
        }


        * {
            box-sizing:
                border-box;
        }


        body {

            margin:
                0;

            min-height:
                100vh;

            background:
                radial-gradient(
                    circle at top left,
                    rgba(
                        85,
                        107,
                        47,
                        .08
                    ),
                    transparent 25%
                ),

                radial-gradient(
                    circle at bottom right,
                    rgba(
                        93,
                        64,
                        55,
                        .08
                    ),
                    transparent 28%
                ),

                var(--cream);

            color:
                var(--text);

            font-family:
                Poppins,
                Arial,
                sans-serif;
        }


        .exam-shell {

            width:
                min(
                    1500px,
                    calc(
                        100% - 32px
                    )
                );

            margin:
                24px auto;
        }


        .exam-topbar {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                20px;

            padding:
                18px 22px;

            border:
                1px solid
                var(--border);

            border-radius:
                22px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .92
                );

            box-shadow:
                var(--shadow);

            backdrop-filter:
                blur(
                    18px
                );

            position:
                sticky;

            top:
                12px;

            z-index:
                100;
        }


        .brand-area {

            display:
                flex;

            align-items:
                center;

            gap:
                13px;

            min-width:
                0;
        }


        .brand-icon {

            width:
                46px;

            height:
                46px;

            border-radius:
                15px;

            display:
                grid;

            place-items:
                center;

            color:
                var(--white);

            background:
                linear-gradient(
                    145deg,
                    var(--brown),
                    var(--brown-dark)
                );

            box-shadow:
                0 10px 25px
                rgba(
                    62,
                    39,
                    35,
                    .18
                );

            flex:
                0 0 auto;
        }


        .brand-text {

            min-width:
                0;
        }


        .brand-text strong {

            display:
                block;

            color:
                var(--brown-dark);

            font-size:
                15px;

            font-weight:
                800;
        }


        .brand-text span {

            display:
                block;

            color:
                var(--muted);

            font-size:
                10px;
        }


        .exam-heading {

            min-width:
                0;

            text-align:
                center;

            flex:
                1;
        }


        .exam-heading small {

            display:
                block;

            color:
                var(--olive);

            font-size:
                9px;

            font-weight:
                800;

            letter-spacing:
                1.6px;

            text-transform:
                uppercase;
        }


        .exam-heading h1 {

            margin:
                3px 0 0;

            color:
                var(--brown-dark);

            font-size:
                17px;

            font-weight:
                800;

            white-space:
                nowrap;

            overflow:
                hidden;

            text-overflow:
                ellipsis;
        }


        .timer-box {

            min-width:
                145px;

            padding:
                10px 15px;

            border:
                1px solid
                rgba(
                    85,
                    107,
                    47,
                    .20
                );

            border-radius:
                16px;

            background:
                #EEF3E6;

            text-align:
                center;
        }


        .timer-box small {

            display:
                block;

            margin-bottom:
                2px;

            color:
                var(--olive-dark);

            font-size:
                9px;

            font-weight:
                700;

            text-transform:
                uppercase;

            letter-spacing:
                1px;
        }


        #examTimer {

            color:
                var(--brown-dark);

            font-size:
                22px;

            font-weight:
                800;

            letter-spacing:
                1px;
        }


        .timer-box.warning {

            background:
                #FFF4DE;

            border-color:
                #F0C06A;
        }


        .timer-box.warning #examTimer {

            color:
                var(--orange);
        }


        .timer-box.danger {

            background:
                #FBEAEA;

            border-color:
                #E5AAAA;
        }


        .timer-box.danger #examTimer {

            color:
                var(--red);
        }


        .exam-layout {

            display:
                grid;

            grid-template-columns:
                minmax(
                    0,
                    1fr
                )
                320px;

            gap:
                22px;

            margin-top:
                22px;
        }


        .question-panel,
        .sidebar-panel {

            border:
                1px solid
                var(--border);

            border-radius:
                24px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .94
                );

            box-shadow:
                var(--shadow);
        }


        .question-panel {

            padding:
                28px;
        }


        .question-header {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                15px;

            padding-bottom:
                20px;

            border-bottom:
                1px solid
                var(--border);
        }


        .question-number {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                9px;

            color:
                var(--brown-dark);

            font-weight:
                800;
        }


        .question-number span {

            display:
                grid;

            place-items:
                center;

            width:
                40px;

            height:
                40px;

            border-radius:
                13px;

            color:
                var(--white);

            background:
                var(--brown);
        }


        .question-progress {

            color:
                var(--muted);

            font-size:
                12px;

            font-weight:
                600;
        }


        .question-body {

            padding:
                28px 5px;
        }


        .question-body h2 {

            margin:
                0 0 24px;

            color:
                var(--brown-dark);

            font-size:
                clamp(
                    18px,
                    2vw,
                    24px
                );

            line-height:
                1.55;

            font-weight:
                700;
        }


        .question-image {

            width:
                min(
                    100%,
                    720px
                );

            max-height:
                340px;

            object-fit:
                contain;

            display:
                block;

            margin:
                0 auto 25px;

            border:
                1px solid
                var(--border);

            border-radius:
                18px;

            background:
                var(--cream-light);
        }


        .options {

            display:
                grid;

            gap:
                13px;
        }


        .option {

            display:
                flex;

            align-items:
                center;

            gap:
                15px;

            padding:
                15px 17px;

            border:
                1px solid
                var(--border);

            border-radius:
                17px;

            background:
                var(--white);

            cursor:
                pointer;

            transition:
                .2s ease;

            user-select:
                none;
        }


        .option:hover {

            transform:
                translateY(
                    -1px
                );

            border-color:
                rgba(
                    93,
                    64,
                    55,
                    .35
                );

            box-shadow:
                0 12px 30px
                rgba(
                    62,
                    39,
                    35,
                    .07
                );
        }


        .option.selected {

            border-color:
                var(--olive);

            background:
                #F0F4E9;

            box-shadow:
                0 12px 30px
                rgba(
                    85,
                    107,
                    47,
                    .10
                );
        }


        .option input {

            position:
                absolute;

            opacity:
                0;

            pointer-events:
                none;
        }


        .option-letter {

            width:
                38px;

            height:
                38px;

            border-radius:
                12px;

            display:
                grid;

            place-items:
                center;

            flex:
                0 0 auto;

            color:
                var(--brown);

            background:
                var(--cream);

            font-weight:
                800;
        }


        .option.selected .option-letter {

            color:
                var(--white);

            background:
                var(--olive);
        }


        .option-text {

            line-height:
                1.45;

            font-size:
                14px;

            font-weight:
                500;
        }


        .question-status-text {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

            margin-top:
                20px;

            color:
                var(--muted);

            font-size:
                11px;

            font-weight:
                600;
        }


        .question-actions {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                14px;

            padding-top:
                20px;

            border-top:
                1px solid
                var(--border);
        }


        .action-left {

            display:
                flex;

            flex-wrap:
                wrap;

            gap:
                10px;
        }


        .action-right {

            display:
                flex;

            gap:
                10px;
        }


        .exam-btn {

            min-height:
                44px;

            padding:
                0 16px;

            border:
                1px solid
                transparent;

            border-radius:
                13px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                7px;

            font-size:
                12px;

            font-weight:
                700;

            transition:
                .2s ease;
        }


        .exam-btn:disabled {

            opacity:
                .48;

            cursor:
                not-allowed;

            transform:
                none !important;
        }


        .btn-primary {

            color:
                var(--white);

            background:
                var(--brown);
        }


        .btn-primary:hover:not(:disabled) {

            color:
                var(--white);

            background:
                var(--brown-dark);

            transform:
                translateY(
                    -1px
                );
        }


        .btn-secondary {

            color:
                var(--brown-dark);

            border-color:
                var(--border);

            background:
                var(--white);
        }


        .btn-secondary:hover:not(:disabled) {

            color:
                var(--brown-dark);

            background:
                var(--cream-light);

            transform:
                translateY(
                    -1px
                );
        }


        .btn-review {

            color:
                var(--olive-dark);

            border-color:
                rgba(
                    85,
                    107,
                    47,
                    .25
                );

            background:
                #F0F4E9;
        }


        .btn-review:hover:not(:disabled) {

            color:
                var(--olive-dark);

            background:
                #E7EEDB;

            transform:
                translateY(
                    -1px
                );
        }


        .sidebar-panel {

            padding:
                22px;

            align-self:
                start;

            position:
                sticky;

            top:
                102px;
        }


        .sidebar-title {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                10px;

            margin-bottom:
                18px;
        }


        .sidebar-title h3 {

            margin:
                0;

            color:
                var(--brown-dark);

            font-size:
                14px;

            font-weight:
                800;
        }


        .answered-count {

            color:
                var(--olive);

            font-size:
                10px;

            font-weight:
                800;
        }


        .status-legend {

            display:
                grid;

            gap:
                9px;

            margin-bottom:
                18px;

            padding-bottom:
                18px;

            border-bottom:
                1px solid
                var(--border);
        }


        .legend-item {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

            color:
                var(--muted);

            font-size:
                10px;

            font-weight:
                600;
        }


        .legend-dot {

            width:
                11px;

            height:
                11px;

            border-radius:
                4px;

            flex:
                0 0 auto;
        }


        .dot-not-visited {

            background:
                #E7E4DD;
        }


        .dot-not-answered {

            background:
                #D7B7A9;
        }


        .dot-answered {

            background:
                #86B894;
        }


        .dot-review {

            background:
                #D8B95B;
        }


        .dot-answered-review {

            background:
                #8E7AB5;
        }


        .palette {

            display:
                grid;

            grid-template-columns:
                repeat(
                    5,
                    1fr
                );

            gap:
                8px;

            max-height:
                430px;

            overflow-y:
                auto;

            padding-right:
                4px;
        }


        .palette button {

            width:
                100%;

            aspect-ratio:
                1;

            border:
                1px solid
                var(--border);

            border-radius:
                11px;

            color:
                var(--brown-dark);

            background:
                var(--white);

            font-size:
                11px;

            font-weight:
                800;

            transition:
                .18s ease;
        }


        .palette button:hover {

            transform:
                translateY(
                    -1px
                );
        }


        .palette button.current {

            color:
                var(--white);

            border-color:
                var(--brown);

            background:
                var(--brown);

            box-shadow:
                0 8px 18px
                rgba(
                    93,
                    64,
                    55,
                    .18
                );
        }


        .palette button.answered {

            border-color:
                #86B894;

            color:
                #245D31;

            background:
                #EDF7EF;
        }


        .palette button.not-answered {

            border-color:
                #D7B7A9;

            color:
                #7A4D3B;

            background:
                #FBF1ED;
        }


        .palette button.review {

            border-color:
                #D8B95B;

            color:
                #705B1E;

            background:
                #FCF6DC;
        }


        .palette button.answered-review {

            border-color:
                #8E7AB5;

            color:
                #55417A;

            background:
                #F2EDF9;
        }


        .palette button.not-visited {

            color:
                var(--brown-dark);

            background:
                #F8F7F3;
        }


        .exam-summary {

            margin-top:
                20px;

            padding-top:
                18px;

            border-top:
                1px solid
                var(--border);
        }


        .summary-row {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                12px;

            padding:
                9px 0;

            color:
                var(--muted);

            font-size:
                10px;

            font-weight:
                600;
        }


        .summary-row strong {

            color:
                var(--brown-dark);

            font-size:
                11px;

            font-weight:
                800;
        }


        .submit-panel {

            margin-top:
                18px;

            padding-top:
                18px;

            border-top:
                1px solid
                var(--border);
        }


        .submit-panel p {

            margin:
                0 0 12px;

            color:
                var(--muted);

            font-size:
                10px;

            line-height:
                1.6;
        }


        .submit-button {

            width:
                100%;

            min-height:
                46px;

            border:
                0;

            border-radius:
                14px;

            color:
                var(--white);

            background:
                var(--olive);

            font-size:
                12px;

            font-weight:
                800;

            transition:
                .2s ease;
        }


        .submit-button:hover:not(:disabled) {

            background:
                var(--olive-dark);

            transform:
                translateY(
                    -1px
                );
        }


        .exam-toast {

            position:
                fixed;

            left:
                50%;

            bottom:
                24px;

            z-index:
                1000;

            min-width:
                260px;

            max-width:
                min(
                    90vw,
                    480px
                );

            padding:
                13px 17px;

            border:
                1px solid
                rgba(
                    62,
                    39,
                    35,
                    .12
                );

            border-radius:
                14px;

            color:
                var(--brown-dark);

            background:
                rgba(
                    255,
                    255,
                    255,
                    .96
                );

            box-shadow:
                0 15px 40px
                rgba(
                    62,
                    39,
                    35,
                    .18
                );

            backdrop-filter:
                blur(
                    18px
                );

            font-size:
                11px;

            font-weight:
                700;

            text-align:
                center;

            opacity:
                0;

            transform:
                translate(
                    -50%,
                    20px
                );

            pointer-events:
                none;

            transition:
                .25s ease;
        }


        .exam-toast.show {

            opacity:
                1;

            transform:
                translate(
                    -50%,
                    0
                );
        }


        .modal-card {

            overflow:
                hidden;

            border:
                0;

            border-radius:
                22px;

            box-shadow:
                0 30px 80px
                rgba(
                    62,
                    39,
                    35,
                    .22
                );
        }


        .modal-card .modal-header {

            color:
                var(--white);

            border:
                0;

            background:
                linear-gradient(
                    135deg,
                    var(--brown),
                    var(--brown-dark)
                );
        }


        @media (
            max-width: 1100px
        ) {

            .exam-layout {

                grid-template-columns:
                    1fr;
            }


            .sidebar-panel {

                position:
                    static;
            }


            .palette {

                max-height:
                    none;
            }
        }


        @media (
            max-width: 760px
        ) {

            .exam-shell {

                width:
                    min(
                        100%,
                        calc(
                            100% - 16px
                        )
                    );

                margin:
                    8px auto;
            }


            .exam-topbar {

                flex-direction:
                    column;

                align-items:
                    stretch;

                padding:
                    15px;
            }


            .exam-heading {

                text-align:
                    left;
            }


            .timer-box {

                width:
                    100%;
            }


            .question-panel {

                padding:
                    18px;
            }


            .question-actions {

                flex-direction:
                    column;

                align-items:
                    stretch;
            }


            .action-left {

                width:
                    100%;
            }


            .action-left .exam-btn,
            .action-right .exam-btn {

                flex:
                    1;
            }


            .action-right {

                width:
                    100%;
            }


            .option {

                align-items:
                    flex-start;
            }
        }

    </style>

</head>


<body>


<div class="exam-shell">


    <header class="exam-topbar">


        <div class="brand-area">

            <div class="brand-icon">

                <i
                    class="fa-solid fa-graduation-cap"
                ></i>

            </div>


            <div class="brand-text">

                <strong>
                    ExamSphere
                </strong>

                <span>
                    Online Examination
                </span>

            </div>

        </div>


        <div class="exam-heading">

            <small>

                <?= take_exam_escape(
                    $attempt['exam_type']
                ) ?>

                Examination

            </small>


            <h1>

                <?= take_exam_escape(
                    $attempt['exam_title']
                ) ?>

            </h1>

        </div>


        <div
            class="timer-box"
            id="timerBox"
        >

            <small>
                Time remaining
            </small>


            <div
                id="examTimer"
            >
                00:00
            </div>

        </div>

    </header>


    <main class="exam-layout">


        <section class="question-panel">


            <div class="question-header">

                <div
                    class="question-number"
                >

                    <span id="questionNumber">
                        1
                    </span>


                    Question

                </div>


                <div
                    class="question-progress"
                    id="questionProgress"
                >

                    Question 1 of
                    <?= $requiredQuestionCount ?>

                </div>

            </div>


            <div
                class="question-body"
                id="questionContainer"
            ></div>


            <div
                class="question-actions"
            >

                <div class="action-left">

                    <button
                        type="button"
                        class="
                            exam-btn
                            btn-secondary
                        "
                        id="previousButton"
                    >

                        <i
                            class="
                                fa-solid
                                fa-arrow-left
                            "
                        ></i>

                        Previous

                    </button>


                    <button
                        type="button"
                        class="
                            exam-btn
                            btn-review
                        "
                        id="reviewButton"
                    >

                        <i
                            class="
                                fa-regular
                                fa-bookmark
                            "
                        ></i>

                        Mark for review

                    </button>


                    <button
                        type="button"
                        class="
                            exam-btn
                            btn-secondary
                        "
                        id="clearButton"
                    >

                        <i
                            class="
                                fa-solid
                                fa-eraser
                            "
                        ></i>

                        Clear

                    </button>

                </div>


                <div class="action-right">

                    <button
                        type="button"
                        class="
                            exam-btn
                            btn-primary
                        "
                        id="nextButton"
                    >

                        Save & Next

                        <i
                            class="
                                fa-solid
                                fa-arrow-right
                            "
                        ></i>

                    </button>

                </div>

            </div>

        </section>


        <aside class="sidebar-panel">


            <div class="sidebar-title">

                <h3>
                    Question navigator
                </h3>


                <span
                    class="answered-count"
                    id="answeredCount"
                >

                    <?= $initialAnswered ?>

                    answered

                </span>

            </div>


            <div class="status-legend">

                <div class="legend-item">

                    <span
                        class="
                            legend-dot
                            dot-not-visited
                        "
                    ></span>

                    Not visited

                </div>


                <div class="legend-item">

                    <span
                        class="
                            legend-dot
                            dot-not-answered
                        "
                    ></span>

                    Not answered

                </div>


                <div class="legend-item">

                    <span
                        class="
                            legend-dot
                            dot-answered
                        "
                    ></span>

                    Answered

                </div>


                <div class="legend-item">

                    <span
                        class="
                            legend-dot
                            dot-review
                        "
                    ></span>

                    Marked for review

                </div>


                <div class="legend-item">

                    <span
                        class="
                            legend-dot
                            dot-answered-review
                        "
                    ></span>

                    Answered & review

                </div>

            </div>


            <div
                class="palette"
                id="questionPalette"
            >

                <?php for (
                    $i = 1;
                    $i <= $requiredQuestionCount;
                    $i++
                ): ?>

                    <button
                        type="button"
                        data-index="<?= $i - 1 ?>"
                        class="not-visited"
                    >

                        <?= $i ?>

                    </button>

                <?php endfor; ?>

            </div>


            <div
                class="exam-summary"
                id="examSummary"
            >

                <div class="summary-row">

                    <span>
                        Total questions
                    </span>

                    <strong>
                        <?= $requiredQuestionCount ?>
                    </strong>

                </div>


                <div class="summary-row">

                    <span>
                        Total marks
                    </span>

                    <strong>
                        <?= take_exam_escape(
                            $attempt['total_marks']
                        ) ?>
                    </strong>

                </div>


                <div class="summary-row">

                    <span>
                        Duration
                    </span>

                    <strong>

                        <?= (int) $attempt[
                            'duration_minutes'
                        ] ?>

                        min

                    </strong>

                </div>


                <div class="summary-row">

                    <span>
                        Negative marking
                    </span>

                    <strong>

                        <?= (int) $attempt[
                            'negative_marking'
                        ] === 1
                            ? 'Enabled'
                            : 'None'
                        ?>

                    </strong>

                </div>

            </div>


            <div class="submit-panel">

                <p>

                    Make sure you have reviewed
                    your answers before submitting
                    the examination.

                </p>


                <button
                    type="button"
                    class="submit-button"
                    id="openSubmitButton"
                >

                    <i
                        class="
                            fa-solid
                            fa-paper-plane
                        "
                    ></i>

                    Submit examination

                </button>

            </div>

        </aside>

    </main>

</div>


<div
    class="modal fade"
    id="submitModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div
        class="
            modal-dialog
            modal-dialog-centered
        "
    >

        <div
            class="
                modal-content
                modal-card
            "
        >

            <div class="modal-header">

                <h5 class="modal-title">

                    <i
                        class="
                            fa-solid
                            fa-paper-plane
                            me-2
                        "
                    ></i>

                    Submit examination

                </h5>


                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <div class="modal-body p-4">

                <p
                    class="mb-3"
                    id="submitWarning"
                >

                    Are you sure you want to submit
                    this examination?

                </p>


                <div
                    class="
                        p-3
                        rounded-4
                    "
                    style="
                        background:#F5F5DC;
                    "
                >

                    <div
                        class="
                            d-flex
                            justify-content-between
                            mb-2
                        "
                    >

                        <span>
                            Answered
                        </span>

                        <strong
                            id="dialogAnswered"
                        >
                            0
                        </strong>

                    </div>


                    <div
                        class="
                            d-flex
                            justify-content-between
                            mb-2
                        "
                    >

                        <span>
                            Unanswered
                        </span>

                        <strong
                            id="dialogUnanswered"
                        >
                            0
                        </strong>

                    </div>


                    <div
                        class="
                            d-flex
                            justify-content-between
                        "
                    >

                        <span>
                            Marked for review
                        </span>

                        <strong
                            id="dialogReviewed"
                        >
                            0
                        </strong>

                    </div>

                </div>

            </div>


            <div
                class="
                    modal-footer
                    border-0
                    p-4
                    pt-0
                "
            >

                <button
                    type="button"
                    class="
                        exam-btn
                        btn-secondary
                    "
                    data-bs-dismiss="modal"
                >

                    Continue exam

                </button>


                <button
                    type="button"
                    class="
                        exam-btn
                        btn-primary
                    "
                    id="confirmSubmitButton"
                >

                    Submit now

                    <i
                        class="
                            fa-solid
                            fa-check
                        "
                    ></i>

                </button>

            </div>

        </div>

    </div>

</div>


<div
    class="exam-toast"
    id="examToast"
></div>


<form
    method="post"
    action="ajax/submit_exam.php"
    id="realSubmitForm"
    style="display:none;"
>


    <input
        type="hidden"
        name="attempt_id"
        value="<?= (int) $attemptId ?>"
    >


    <input
        type="hidden"
        name="csrf_token"
        value="<?= take_exam_escape(
            $csrfToken
        ) ?>"
    >


    <input
        type="hidden"
        name="auto_submit"
        value="0"
        id="autoSubmitField"
    >

</form>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
></script>


<script>

window.EXAMSPHERE_EXAM = {

    attemptId:

        <?= (int) $attemptId ?>,

    csrfToken:

        <?= json_encode(
            $csrfToken,
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        ) ?>,

    deadline:

        <?= (int) $deadlineMilliseconds ?>,

    questions:

        <?= json_encode(
            $frontendQuestions,
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?>

};

</script>


<script
    src="assets/js/exam.js"
    defer
></script>


</body>

</html>