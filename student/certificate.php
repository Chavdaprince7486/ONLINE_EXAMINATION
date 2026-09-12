<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';

require_role('student');

$studentId = current_user_id();
$requestedResultId = filter_input(INPUT_GET, 'result_id', FILTER_VALIDATE_INT);

function certificate_e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function certificate_date(
    ?string $value,
    string $format = 'd F Y'
): string
{
    if (!$value) {
        return '—';
    }

    try {
        return (
            new DateTimeImmutable($value)
        )->format($format);
    } catch (Throwable) {
        return '—';
    }
}

$student = null;
$certificates = [];

try {

    $studentStmt = $conn->prepare(
        'SELECT
            id,
            student_code,
            full_name,
            email
         FROM students
         WHERE id = ?
         LIMIT 1'
    );

    $studentStmt->execute([
        $studentId
    ]);

    $student =
        $studentStmt->fetch(
            PDO::FETCH_ASSOC
        ) ?: null;

    if (!$student) {

        clear_invalid_auth_session();

        header(
            'Location: ../auth/login.php'
        );

        exit;
    }

    $resultStmt = $conn->prepare(
        "SELECT
            r.id,
            r.attempt_id,
            r.exam_id,
            r.total_questions,
            r.total_marks,
            r.obtained_marks,
            r.percentage,
            r.grade,
            r.result_status,
            r.created_at,
            e.title AS exam_title,
            s.name AS subject_name

         FROM results r

         INNER JOIN exams e
             ON e.id = r.exam_id

         LEFT JOIN subjects s
             ON s.id = e.subject_id

         WHERE r.student_id = ?
           AND r.result_status = 'Pass'

         ORDER BY
            r.created_at DESC,
            r.id DESC"
    );

    $resultStmt->execute([
        $studentId
    ]);

    $certificates =
        $resultStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $e) {

    error_log(
        'Student certificate load failed: ' .
        $e->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load certificates right now.'
    );
}

$selected = null;

if (
    $requestedResultId !== false &&
    $requestedResultId !== null &&
    $requestedResultId > 0
) {

    foreach (
        $certificates
        as $item
    ) {

        if (
            (int)$item['id'] ===
            (int)$requestedResultId
        ) {

            $selected = $item;

            break;
        }
    }
}

if (
    $selected === null &&
    !empty($certificates)
) {

    $selected = $certificates[0];
}

?>

<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >

    <title>
        Certificates | ExamSphere
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
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/student-nav.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/dashboard.css"
    >

    <style>

        .certificate-page{
            width:min(1240px,94%);
            margin:35px auto 60px
        }

        .certificate-head{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:18px;
            margin-bottom:20px
        }

        .certificate-head h1{
            margin:0;
            font-size:1.55rem
        }

        .certificate-head p{
            margin:5px 0 0;
            color:#81766f;
            font-size:.68rem
        }

        .certificate-shell{
            display:grid;
            grid-template-columns:
                280px
                minmax(0,1fr);
            gap:18px
        }

        .certificate-list,
        .certificate-paper{
            background:#fff;
            border:1px solid #e9e1d6;
            border-radius:22px;
            box-shadow:
                0 16px 45px
                rgba(72,48,37,.08)
        }

        .certificate-list{
            padding:14px
        }

        .certificate-item{
            display:block;
            padding:13px;
            border-radius:14px;
            margin-bottom:8px;
            border:1px solid transparent
        }

        .certificate-item:hover,
        .certificate-item.active{
            background:#f7f4ec;
            border-color:#e8dfd0
        }

        .certificate-item strong{
            display:block;
            font-size:.7rem
        }

        .certificate-item small{
            display:block;
            margin-top:3px;
            color:#81766f;
            font-size:.57rem
        }

        .certificate-paper{
            padding:16px;
            background:
                linear-gradient(
                    135deg,
                    #fcfaf4,
                    #fff
                )
        }

        .certificate-inner{
            position:relative;
            min-height:560px;
            border:1px solid #d9c9ab;
            padding:55px 45px;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            text-align:center;
            overflow:hidden
        }

        .certificate-inner:before,
        .certificate-inner:after{
            content:"";
            position:absolute;
            width:170px;
            height:170px;
            border:1px solid
                rgba(93,64,55,.12);
            border-radius:50%
        }

        .certificate-inner:before{
            top:-90px;
            left:-90px
        }

        .certificate-inner:after{
            right:-90px;
            bottom:-90px
        }

        .certificate-kicker{
            font-size:.65rem;
            letter-spacing:.16em;
            color:#6d8120;
            font-weight:800
        }

        .certificate-mark{
            width:72px;
            height:72px;
            border-radius:50%;
            display:grid;
            place-items:center;
            background:#f0eadf;
            color:#5D4037;
            font-size:1.5rem;
            margin:15px 0
        }

        .certificate-inner h2{
            font-size:2rem;
            color:#5D4037;
            margin:0
        }

        .certificate-subtitle{
            margin:8px 0 18px;
            color:#81766f;
            font-size:.7rem
        }

        .certificate-name{
            font-size:1.5rem;
            font-weight:800;
            color:#2e2621;
            border-bottom:1px solid #cdbb9e;
            padding:0 30px 8px
        }

        .certificate-copy{
            max-width:690px;
            margin:17px auto;
            color:#675d55;
            font-size:.7rem;
            line-height:1.7
        }

        .certificate-copy strong{
            color:#5D4037
        }

        .certificate-meta{
            display:flex;
            justify-content:center;
            flex-wrap:wrap;
            gap:30px;
            margin-top:18px
        }

        .certificate-meta div span{
            display:block;
            color:#938980;
            font-size:.56rem
        }

        .certificate-meta div strong{
            display:block;
            margin-top:4px;
            font-size:.7rem
        }

        .certificate-actions{
            display:flex;
            justify-content:center;
            gap:10px;
            margin-top:23px
        }

        .certificate-btn{
            border:0;
            border-radius:11px;
            padding:11px 15px;
            background:#5D4037;
            color:#fff;
            font:inherit;
            font-size:.68rem;
            font-weight:700;
            cursor:pointer
        }

        .certificate-btn.secondary{
            background:#f0eadf;
            color:#5D4037
        }

        @media(max-width:850px){

            .certificate-shell{
                grid-template-columns:1fr
            }

            .certificate-list{
                display:grid;
                grid-template-columns:
                    repeat(2,1fr);
                gap:8px
            }

            .certificate-item{
                margin:0
            }

        }

        @media(max-width:620px){

            .certificate-page{
                width:
                    calc(100% - 24px)
            }

            .certificate-head{
                align-items:flex-start;
                flex-direction:column
            }

            .certificate-list{
                grid-template-columns:1fr
            }

            .certificate-inner{
                min-height:500px;
                padding:35px 18px
            }

            .certificate-inner h2{
                font-size:1.45rem
            }

            .certificate-name{
                font-size:1.15rem;
                padding:0 10px 8px
            }

            .certificate-meta{
                gap:16px
            }

        }

        @media print{

            body *{
                visibility:hidden
            }

            .certificate-inner,
            .certificate-inner *{
                visibility:visible
            }

            .certificate-inner{
                position:absolute;
                left:0;
                top:0;
                width:100%;
                min-height:100vh;
                border:2px solid #d9c9ab
            }

        }

    </style>

</head>

<body>

<?php include 'includes/navbar.php'; ?>

<main class="certificate-page">

    <header class="certificate-head">

        <div>

            <h1>
                Certificates
            </h1>

            <p>
                Certificates are available
                for your passed examinations.
            </p>

        </div>

        <a
            class="certificate-btn secondary"
            href="results.php"
        >

            <i class="fa-solid fa-chart-column"></i>

            Results

        </a>

    </header>

    <?php if (!$certificates): ?>

        <section class="certificate-paper">

            <div class="certificate-inner">

                <div class="certificate-mark">

                    <i class="fa-solid fa-award"></i>

                </div>

                <h2>
                    No Certificate Yet
                </h2>

                <p class="certificate-copy">

                    Complete and pass an
                    examination to make its
                    certificate available here.

                </p>

                <a
                    class="certificate-btn"
                    href="practice_exams.php"
                >

                    <i class="fa-solid fa-file-pen"></i>

                    Explore Exams

                </a>

            </div>

        </section>

    <?php else: ?>

        <section class="certificate-shell">

            <aside class="certificate-list">

                <?php foreach (
                    $certificates
                    as $item
                ): ?>

                    <a
                        class="
                            certificate-item
                            <?= $selected &&
                            (int)$selected['id'] ===
                            (int)$item['id']
                                ? 'active'
                                : ''
                            ?>
                        "
                        href="
                            certificate.php?result_id=
                            <?= (int)$item['id'] ?>
                        "
                    >

                        <strong>
                            <?= certificate_e(
                                $item['exam_title']
                            ) ?>
                        </strong>

                        <small>

                            <?= certificate_e(
                                $item['percentage']
                            ) ?>%

                            ·

                            <?= certificate_date(
                                $item['created_at']
                            ) ?>

                        </small>

                    </a>

                <?php endforeach; ?>

            </aside>

            <section class="certificate-paper">

                <div class="certificate-inner">

                    <div class="certificate-kicker">

                        EXAMSPHERE • CERTIFICATE OF ACHIEVEMENT

                    </div>

                    <div class="certificate-mark">

                        <i class="fa-solid fa-award"></i>

                    </div>

                    <h2>
                        Certificate of Achievement
                    </h2>

                    <p class="certificate-subtitle">
                        This certificate is proudly presented to
                    </p>

                    <div class="certificate-name">

                        <?= certificate_e(
                            $student['full_name']
                        ) ?>

                    </div>

                    <p class="certificate-copy">

                        This is to certify that

                        <strong>
                            <?= certificate_e(
                                $student['full_name']
                            ) ?>
                        </strong>,

                        Student ID

                        <strong>
                            <?= certificate_e(
                                $student['student_code']
                            ) ?>
                        </strong>,

                        successfully completed and passed
                        the examination

                        <strong>
                            <?= certificate_e(
                                $selected['exam_title']
                            ) ?>
                        </strong>

                        with a score of

                        <strong>
                            <?= certificate_e(
                                $selected['percentage']
                            ) ?>%
                        </strong>.

                    </p>

                    <div class="certificate-meta">

                        <div>

                            <span>
                                Subject
                            </span>

                            <strong>

                                <?= certificate_e(
                                    $selected['subject_name']
                                    ?: 'General'
                                ) ?>

                            </strong>

                        </div>

                        <div>

                            <span>
                                Marks
                            </span>

                            <strong>

                                <?= certificate_e(
                                    $selected['obtained_marks']
                                ) ?>

                                /

                                <?= certificate_e(
                                    $selected['total_marks']
                                ) ?>

                            </strong>

                        </div>

                        <div>

                            <span>
                                Grade
                            </span>

                            <strong>

                                <?= certificate_e(
                                    $selected['grade']
                                ) ?>

                            </strong>

                        </div>

                        <div>

                            <span>
                                Date
                            </span>

                            <strong>

                                <?= certificate_date(
                                    $selected['created_at']
                                ) ?>

                            </strong>

                        </div>

                    </div>

                    <div class="certificate-actions">

                        <button
                            class="certificate-btn"
                            type="button"
                            onclick="window.print()"
                        >

                            <i class="fa-solid fa-print"></i>

                            Print Certificate

                        </button>

                        <a
                            class="
                                certificate-btn
                                secondary
                            "
                            href="
                                result.php?id=
                                <?= (int)$selected['id'] ?>
                            "
                        >

                            <i class="fa-solid fa-file-lines"></i>

                            View Result

                        </a>

                    </div>

                </div>

            </section>

        </section>

    <?php endif; ?>

</main>

</body>

</html>