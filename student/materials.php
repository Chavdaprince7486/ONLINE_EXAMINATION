<?php

declare(strict_types=1);

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


$studentId = (int)($_SESSION['user_id'] ?? 0);


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function materials_escape(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function materials_date(mixed $value): string
{
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
        )->format('d M Y');
    } catch (Throwable) {
        return '—';
    }
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim(
    (string)(
        $_GET['q'] ?? ''
    )
);


$subjectId = filter_input(
    INPUT_GET,
    'subject_id',
    FILTER_VALIDATE_INT
);


$subjectId = (
    $subjectId &&
    $subjectId > 0
)
    ? (int)$subjectId
    : null;


/*
|--------------------------------------------------------------------------
| DATA
|--------------------------------------------------------------------------
*/

$subjects = [];

$materials = [];

$hasSubscription = false;

$activePlan = null;

$pageError = '';


try {

    /*
    |--------------------------------------------------------------------------
    | SUBJECTS
    |--------------------------------------------------------------------------
    */

    $subjectStatement = $conn->query(
        "
        SELECT
            id,
            name

        FROM subjects

        WHERE
            status = 'Active'

        ORDER BY
            name ASC
        "
    );


    $subjects = $subjectStatement->fetchAll(
        PDO::FETCH_ASSOC
    );


    /*
    |--------------------------------------------------------------------------
    | MATERIALS
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT

            m.id,
            m.title,
            m.description,
            m.file_path,
            m.access_type,
            m.uploaded_at,

            s.name AS subject_name

        FROM study_materials m

        LEFT JOIN subjects s
            ON s.id = m.subject_id

        WHERE
            m.status = 'Active'
    ";


    $params = [];


    if ($search !== '') {

        $sql .= "
            AND (
                m.title LIKE ?
                OR m.description LIKE ?
                OR s.name LIKE ?
            )
        ";

        $like = '%' . $search . '%';

        $params = [
            $like,
            $like,
            $like
        ];
    }


    if ($subjectId !== null) {

        $sql .= "
            AND m.subject_id = ?
        ";

        $params[] = $subjectId;
    }


    $sql .= "
        ORDER BY
            m.uploaded_at DESC,
            m.id DESC
    ";


    $materialStatement = $conn->prepare(
        $sql
    );


    $materialStatement->execute(
        $params
    );


    $materials =
        $materialStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | ACTIVE SUBSCRIPTION
    |--------------------------------------------------------------------------
    */

    $hasSubscription =
        has_active_subscription(
            $conn,
            $studentId
        );


    /*
    |--------------------------------------------------------------------------
    | ACTIVE PLAN DETAILS
    |--------------------------------------------------------------------------
    */

    if ($hasSubscription) {

        $activeStatement = $conn->prepare(
            "
            SELECT

                s.start_date,
                s.end_date,

                p.name AS plan_name,
                p.duration_months

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


        $activeStatement->execute([
            $studentId
        ]);


        $activePlan =
            $activeStatement->fetch(
                PDO::FETCH_ASSOC
            );
    }


} catch (Throwable $exception) {

    error_log(
        'Student materials page failed: ' .
        $exception->getMessage()
    );

    $pageError =
        'Unable to load study materials right now.';
}


/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/

$totalMaterials =
    count($materials);


$publicMaterials = 0;

$premiumMaterials = 0;

$lockedMaterials = 0;


foreach (
    $materials
    as $material
) {

    if (
        ($material['access_type'] ?? '')
        ===
        'Subscription Only'
    ) {

        $premiumMaterials++;

        if (!$hasSubscription) {
            $lockedMaterials++;
        }

    } else {

        $publicMaterials++;
    }
}


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$pageTitle =
    'Study Materials | ExamSphere';

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


<title>
    <?= materials_escape($pageTitle) ?>
</title>


<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>


<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
>


<link
    href="
        https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap
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
    href="../assets/css/main.css"
>


<link
    rel="stylesheet"
    href="assets/css/student-nav.css"
>


<style>

/* =========================================================
   ROOT
========================================================= */

:root{

    --m-bg:#F5F5DC;
    --m-white:#FFFFFF;

    --m-brown:#5D4037;
    --m-dark:#3E2723;

    --m-brown-soft:#F1E8E1;

    --m-olive:#556B2F;
    --m-olive-dark:#465923;
    --m-olive-soft:#ECF2E2;

    --m-gold:#A47B29;
    --m-gold-soft:#FFF2D7;

    --m-red:#A84538;
    --m-red-soft:#FBEAE7;

    --m-muted:#766D65;
    --m-light:#968C83;

    --m-line:#E3DCD2;
    --m-line-soft:#EEE8E0;

    --m-shadow:
        0 16px 42px
        rgba(
            62,
            39,
            35,
            .055
        );
}


/* =========================================================
   PAGE
========================================================= */

.student-materials-page{

    width:100%;

    min-height:100vh;

    margin:0 !important;

    padding:
        15px 0 60px !important;

    color:
        var(--m-dark);

    font-family:
        'Poppins',
        Arial,
        sans-serif;

    background:

        radial-gradient(
            circle at 5% 3%,
            rgba(
                85,
                107,
                47,
                .06
            ),
            transparent 24%
        ),

        radial-gradient(
            circle at 95% 7%,
            rgba(
                93,
                64,
                55,
                .05
            ),
            transparent 23%
        ),

        linear-gradient(
            180deg,
            #FBFAF6 0%,
            #F5F5DC 62%,
            #EFEEE6 100%
        );
}


.student-materials-page section{

    margin:0 !important;

    padding:0 !important;
}


/* =========================================================
   CONTAINER
========================================================= */

.materials-container{

    width:
        min(
            1290px,
            calc(
                100% - 30px
            )
        );

    margin:0 auto;
}


/* =========================================================
   HERO
========================================================= */

.materials-hero{

    position:relative;

    overflow:hidden;

    min-height:
        235px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:28px;

    padding:
        30px 32px;

    border-radius:
        24px;

    color:#fff;

    background:
        linear-gradient(
            135deg,
            #5D4037 0%,
            #47312B 55%,
            #556B2F 100%
        );

    box-shadow:
        0
        22px
        55px
        rgba(
            62,
            39,
            35,
            .14
        );
}


.materials-hero::before{

    content:'';

    position:absolute;

    width:
        420px;

    height:
        420px;

    right:
        -155px;

    top:
        -270px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .10
        );

    border-radius:50%;
}


.materials-hero::after{

    content:'';

    position:absolute;

    width:
        220px;

    height:
        220px;

    left:
        45%;

    bottom:
        -175px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .07
        );

    border-radius:50%;
}


.materials-hero-content{

    position:relative;

    z-index:2;

    max-width:
        820px;
}


.materials-kicker{

    display:inline-flex;

    align-items:center;

    gap:7px;

    color:
        #D8D0A5;

    font-size:
        7px;

    font-weight:
        900;

    letter-spacing:
        1.2px;
}


.materials-hero h1{

    margin:
        10px 0 8px;

    color:#fff;

    font-size:
        clamp(
            35px,
            4.4vw,
            50px
        );

    line-height:
        .99;

    letter-spacing:
        -.055em;

    font-weight:
        900;
}


.materials-hero h1 span{

    color:
        #D5E1A4;
}


.materials-hero p{

    max-width:
        720px;

    margin:0;

    color:
        #DACFC7;

    font-size:
        8px;

    line-height:
        1.75;
}


.materials-hero-action{

    position:relative;

    z-index:2;

    min-height:
        43px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:7px;

    padding:
        0 13px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .15
        );

    border-radius:
        10px;

    color:#fff;

    background:
        rgba(
            255,
            255,
            255,
            .07
        );

    font-size:
        7px;

    font-weight:
        900;

    text-decoration:none;

    backdrop-filter:
        blur(8px);

    transition:.2s ease;
}


.materials-hero-action:hover{

    color:#fff;

    background:
        rgba(
            255,
            255,
            255,
            .14
        );

    transform:
        translateY(-2px);
}


/* =========================================================
   MEMBERSHIP BOX
========================================================= */

.materials-membership{

    position:relative;

    z-index:2;

    width:
        292px;

    flex:
        0 0 292px;

    padding:
        15px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .10
        );

    border-radius:
        16px;

    background:
        rgba(
            255,
            255,
            255,
            .055
        );

    backdrop-filter:
        blur(10px);
}


.materials-membership-top{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:10px;
}


.materials-membership-top span{

    color:
        #BFB3A9;

    font-size:
        5.7px;

    font-weight:
        900;

    letter-spacing:
        .9px;
}


.materials-membership-status{

    display:inline-flex;

    align-items:center;

    gap:4px;

    color:
        #D9E5B8;

    font-size:
        5.7px;

    font-weight:
        900;
}


.materials-membership-status i{

    font-size:
        5px;
}


.materials-membership-plan{

    margin-top:
        8px;

    color:#fff;

    font-size:
        15px;

    font-weight:
        900;
}


.materials-membership-details{

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:6px;

    margin-top:
        9px;
}


.materials-membership-detail{

    padding:
        8px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .08
        );

    border-radius:
        9px;

    background:
        rgba(
            255,
            255,
            255,
            .035
        );
}


.materials-membership-detail span{

    display:block;

    color:
        #AFA39A;

    font-size:
        4.8px;

    font-weight:
        700;

    text-transform:
        uppercase;
}


.materials-membership-detail strong{

    display:block;

    margin-top:
        3px;

    color:#fff;

    font-size:
        6.8px;

    font-weight:
        900;
}


/* =========================================================
   STATS
========================================================= */

.materials-stats{

    display:grid;

    grid-template-columns:
        repeat(
            3,
            minmax(
                0,
                1fr
            )
        );

    gap:9px;

    margin-top:
        10px !important;
}


.material-stat{

    min-height:
        76px;

    display:flex;

    align-items:center;

    gap:9px;

    padding:
        10px 12px;

    border:
        1px solid
        var(--m-line);

    border-radius:
        14px;

    background:#fff;

    box-shadow:
        0
        9px
        24px
        rgba(
            62,
            39,
            35,
            .035
        );

    transition:.2s ease;
}


.material-stat:hover{

    transform:
        translateY(-3px);

    box-shadow:
        var(--m-shadow);
}


.material-stat-icon{

    width:
        40px;

    height:
        40px;

    flex:
        0 0 40px;

    display:grid;

    place-items:center;

    border-radius:
        11px;

    font-size:
        10px;
}


.material-stat-icon.green{

    color:
        var(--m-olive);

    background:
        var(--m-olive-soft);
}


.material-stat-icon.brown{

    color:
        var(--m-brown);

    background:
        var(--m-brown-soft);
}


.material-stat-icon.gold{

    color:
        var(--m-gold);

    background:
        var(--m-gold-soft);
}


.material-stat small{

    display:block;

    color:
        var(--m-light);

    font-size:
        5.8px;

    font-weight:
        700;

    text-transform:
        uppercase;
}


.material-stat strong{

    display:block;

    margin-top:
        3px;

    color:
        var(--m-dark);

    font-size:
        19px;

    font-weight:
        900;
}


/* =========================================================
   TOOLBAR
========================================================= */

.materials-toolbar{

    display:grid;

    grid-template-columns:
        minmax(
            0,
            1fr
        )
        auto;

    gap:8px;

    margin-top:
        10px;

    padding:
        9px;

    border:
        1px solid
        var(--m-line);

    border-radius:
        14px;

    background:
        rgba(
            255,
            255,
            255,
            .96
        );

    box-shadow:
        0
        8px
        22px
        rgba(
            62,
            39,
            35,
            .03
        );
}


.material-filter-group{

    min-width:0;

    display:grid;

    grid-template-columns:
        minmax(
            0,
            1fr
        )
        190px;

    gap:7px;
}


.material-search-wrap{

    position:relative;
}


.material-search-icon{

    position:absolute;

    left:
        11px;

    top:
        50%;

    transform:
        translateY(-50%);

    color:
        #968C83;

    font-size:
        8px;

    pointer-events:none;
}


.material-search{

    width:100%;

    min-height:
        40px;

    padding:
        0 11px 0 31px;

    border:
        1px solid
        #E0D8CF;

    border-radius:
        9px;

    outline:0;

    color:
        var(--m-dark);

    background:#fff;

    font-family:inherit;

    font-size:
        7px;

    font-weight:
        600;
}


.material-search:focus{

    border-color:
        var(--m-olive);

    box-shadow:
        0 0 0 3px
        rgba(
            85,
            107,
            47,
            .08
        );
}


.material-subject{

    min-height:
        40px;

    padding:
        0 10px;

    border:
        1px solid
        #E0D8CF;

    border-radius:
        9px;

    outline:0;

    color:
        var(--m-brown);

    background:#fff;

    font-family:inherit;

    font-size:
        7px;

    font-weight:
        600;
}


.material-filter-btn{

    min-height:
        40px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:6px;

    padding:
        0 13px;

    border:0;

    border-radius:
        9px;

    color:#fff;

    background:
        var(--m-brown);

    font-family:inherit;

    font-size:
        7px;

    font-weight:
        900;

    cursor:pointer;

}


.material-filter-btn:hover{

    background:
        var(--m-olive);
}


/* =========================================================
   SECTION HEADING
========================================================= */

.materials-section-header{

    display:flex;

    align-items:flex-end;

    justify-content:space-between;

    gap:15px;

    margin:
        13px 0 9px;
}


.materials-section-kicker{

    color:
        var(--m-olive);

    font-size:
        6px;

    font-weight:
        900;

    letter-spacing:
        1px;
}


.materials-section-header h2{

    margin:
        4px 0 2px;

    color:
        var(--m-dark);

    font-size:
        20px;

    line-height:
        1.1;

    font-weight:
        900;
}


.materials-section-header p{

    margin:0;

    color:
        var(--m-muted);

    font-size:
        6.8px;
}


.materials-count{

    display:inline-flex;

    align-items:center;

    min-height:
        25px;

    padding:
        0 8px;

    border:
        1px solid
        var(--m-line);

    border-radius:
        999px;

    color:
        var(--m-muted);

    background:
        rgba(
            255,
            255,
            255,
            .70
        );

    font-size:
        5.8px;

    font-weight:
        800;
}


/* =========================================================
   MATERIAL GRID
========================================================= */

.materials-grid{

    display:grid;

    grid-template-columns:
        repeat(
            3,
            minmax(
                0,
                1fr
            )
        );

    gap:
        10px;
}


.material-card{

    position:relative;

    overflow:hidden;

    display:flex;

    flex-direction:column;

    min-height:
        270px;

    padding:
        14px;

    border:
        1px solid
        var(--m-line);

    border-radius:
        16px;

    background:
        #fff;

    box-shadow:
        0
        11px
        28px
        rgba(
            62,
            39,
            35,
            .04
        );

    transition:
        transform .2s ease,
        box-shadow .2s ease,
        border-color .2s ease;
}


.material-card::after{

    content:'';

    position:absolute;

    left:
        20%;

    right:
        20%;

    bottom:
        0;

    height:
        3px;

    border-radius:
        999px;

    background:
        var(--m-olive);
}


.material-card:hover{

    transform:
        translateY(-4px);

    border-color:
        #D6CDC2;

    box-shadow:
        0
        20px
        43px
        rgba(
            62,
            39,
            35,
            .075
        );
}


.material-top{

    display:flex;

    align-items:flex-start;

    justify-content:space-between;

    gap:8px;
}


.material-icon{

    width:
        43px;

    height:
        43px;

    display:grid;

    place-items:center;

    border-radius:
        12px;

    color:
        var(--m-red);

    background:
        var(--m-red-soft);

    font-size:
        12px;
}


.material-type{

    display:inline-flex;

    align-items:center;

    gap:4px;

    padding:
        5px 7px;

    border-radius:
        999px;

    color:
        var(--m-olive);

    background:
        var(--m-olive-soft);

    font-size:
        5.5px;

    font-weight:
        900;
}


.material-type.locked{

    color:
        #817267;

    background:
        #F0EBE5;
}


.material-title{

    margin:
        12px 0 5px;

    color:
        var(--m-dark);

    font-size:
        14px;

    line-height:
        1.3;

    font-weight:
        900;
}


.material-description{

    min-height:
        47px;

    margin:0;

    color:
        var(--m-muted);

    font-size:
        7px;

    line-height:
        1.65;
}


.material-meta{

    display:flex;

    flex-wrap:wrap;

    gap:5px;

    margin-top:
        9px;
}


.material-meta span{

    display:inline-flex;

    align-items:center;

    gap:4px;

    padding:
        5px 7px;

    border:
        1px solid
        var(--m-line-soft);

    border-radius:
        7px;

    color:
        var(--m-light);

    background:
        #FCFBF8;

    font-size:
        5.6px;

    font-weight:
        600;
}


.material-meta i{

    color:
        var(--m-olive);
}


.material-divider{

    height:1px;

    margin:
        10px 0 8px;

    background:
        var(--m-line-soft);
}


.material-access-info{

    display:flex;

    align-items:flex-start;

    gap:6px;

    color:
        var(--m-muted);

    font-size:
        6.2px;

    line-height:
        1.5;
}


.material-access-info i{

    margin-top:2px;

    color:
        var(--m-olive);
}


.material-access-info.locked i{

    color:
        #8A776A;
}


/* =========================================================
   ACTION
========================================================= */

.material-action{

    margin-top:auto;

    padding-top:
        10px;
}


.material-action a{

    width:100%;

    min-height:
        38px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:7px;

    border-radius:
        9px;

    color:#fff;

    background:
        var(--m-brown);

    font-size:
        6.8px;

    font-weight:
        900;

    text-decoration:none;

    transition:.2s ease;
}


.material-action a:hover{

    color:#fff;

    background:
        var(--m-olive);
}


.material-action a.unlock{

    color:
        var(--m-brown);

    background:
        #F3EEE7;
}


.material-action a.unlock:hover{

    color:
        var(--m-dark);

    background:
        var(--m-olive-soft);
}


/* =========================================================
   EMPTY
========================================================= */

.materials-empty{

    position:relative;

    overflow:hidden;

    min-height:
        295px;

    display:flex;

    align-items:center;

    justify-content:center;

    flex-direction:column;

    padding:
        28px;

    border:
        1px solid
        var(--m-line);

    border-radius:
        20px;

    background:
        rgba(
            255,
            255,
            255,
            .92
        );

    box-shadow:
        var(--m-shadow);

    text-align:center;
}


.materials-empty::before{

    content:'';

    position:absolute;

    width:
        220px;

    height:
        220px;

    right:
        -100px;

    top:
        -105px;

    border:
        1px solid
        rgba(
            85,
            107,
            47,
            .08
        );

    border-radius:50%;
}


.materials-empty-icon{

    width:
        64px;

    height:
        64px;

    display:grid;

    place-items:center;

    margin-bottom:
        13px;

    border-radius:
        17px;

    color:
        var(--m-olive);

    background:
        var(--m-olive-soft);

    font-size:
        21px;
}


.materials-empty-kicker{

    color:
        var(--m-olive);

    font-size:
        6.5px;

    font-weight:
        900;

    letter-spacing:
        1px;
}


.materials-empty h2{

    margin:
        7px 0 5px;

    color:
        var(--m-dark);

    font-size:
        20px;

    font-weight:
        900;
}


.materials-empty p{

    max-width:
        520px;

    margin:0;

    color:
        var(--m-muted);

    font-size:
        7px;

    line-height:
        1.7;
}


.materials-empty-button{

    min-height:
        39px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:6px;

    margin-top:
        14px;

    padding:
        0 12px;

    border-radius:
        9px;

    color:#fff;

    background:
        var(--m-brown);

    font-size:
        6.8px;

    font-weight:
        900;

    text-decoration:none;
}


.materials-empty-button:hover{

    color:#fff;

    background:
        var(--m-olive);
}


/* =========================================================
   ERROR
========================================================= */

.materials-error{

    margin-top:
        10px;

    padding:
        11px 13px;

    border:
        1px solid
        #ECD0CA;

    border-radius:
        10px;

    color:
        var(--m-red);

    background:
        var(--m-red-soft);

    font-size:
        7px;

    font-weight:
        700;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1050px){

    .materials-hero{

        flex-direction:
            column;

        align-items:
            flex-start;
    }


    .materials-membership{

        width:
            100%;

        max-width:
            430px;
    }


    .materials-grid{

        grid-template-columns:
            repeat(
                2,
                minmax(
                    0,
                    1fr
                )
            );
    }

}


@media(max-width:800px){

    .materials-toolbar{

        grid-template-columns:
            1fr;
    }


    .material-filter-group{

        grid-template-columns:
            1fr 175px;
    }


    .material-filter-btn{

        width:
            100%;
    }

}


@media(max-width:650px){

    .student-materials-page{

        padding:
            10px 0 45px !important;
    }


    .materials-container{

        width:
            calc(
                100% - 14px
            );
    }


    .materials-hero{

        padding:
            23px 18px;

        border-radius:
            20px;
    }


    .materials-hero h1{

        font-size:
            31px;
    }


    .materials-hero p{

        font-size:
            7.4px;
    }


    .materials-membership{

        max-width:none;
    }


    .materials-stats{

        grid-template-columns:
            1fr;
    }


    .material-filter-group{

        grid-template-columns:
            1fr;
    }


    .materials-grid{

        grid-template-columns:
            1fr;
    }


    .materials-section-header{

        align-items:
            flex-start;

        flex-direction:
            column;
    }


    .material-card{

        min-height:
            260px;
    }

}


@media(max-width:400px){

    .materials-membership-details{

        grid-template-columns:
            1fr;
    }


    .materials-hero h1{

        font-size:
            28px;
    }

}

</style>

</head>


<body>


<?php include 'includes/navbar.php'; ?>


<main
    class="
        student-materials-page
    "
>


<div
    class="
        materials-container
    "
>


<!-- =====================================================
     HERO
====================================================== -->

<section
    class="
        materials-hero
    "
>


<div
    class="
        materials-hero-content
    "
>


<div
    class="
        materials-kicker
    "
>

<i
    class="
        fa-solid
        fa-book-open
    "
></i>

LEARNING LIBRARY

</div>


<h1>

Study smarter.

<span>
    Learn stronger.
</span>

</h1>


<p>

Explore ExamSphere study resources prepared
for your subjects and examinations. Public
materials are available to everyone, while
subscription-only resources unlock with an
active membership.

</p>


</div>


<?php if (
    $hasSubscription &&
    $activePlan
): ?>


<div
    class="
        materials-membership
    "
>


<div
    class="
        materials-membership-top
    "
>


<span>
    CURRENT MEMBERSHIP
</span>


<div
    class="
        materials-membership-status
    "
>

<i
    class="
        fa-solid
        fa-circle
    "
></i>

ACTIVE

</div>


</div>


<div
    class="
        materials-membership-plan
    "
>

<?= materials_escape(
    $activePlan[
        'plan_name'
    ]
) ?>

</div>


<div
    class="
        materials-membership-details
    "
>


<div
    class="
        materials-membership-detail
    "
>

<span>
    Started
</span>


<strong>

<?= materials_date(
    $activePlan[
        'start_date'
    ]
) ?>

</strong>

</div>


<div
    class="
        materials-membership-detail
    "
>

<span>
    Valid Until
</span>


<strong>

<?= materials_date(
    $activePlan[
        'end_date'
    ]
) ?>

</strong>

</div>


</div>


</div>


<?php else: ?>


<a
    href="subscriptions.php"
    class="
        materials-hero-action
    "
>

<i
    class="
        fa-solid
        fa-gem
    "
></i>

Unlock Premium Materials

</a>


<?php endif; ?>


</section>


<!-- =====================================================
     ERROR
====================================================== -->

<?php if (
    $pageError !== ''
): ?>


<div
    class="
        materials-error
    "
>

<i
    class="
        fa-solid
        fa-triangle-exclamation
    "
    style="
        margin-right:5px;
    "
></i>

<?= materials_escape(
    $pageError
) ?>

</div>


<?php endif; ?>


<!-- =====================================================
     STATS
====================================================== -->

<section
    class="
        materials-stats
    "
>


<article
    class="
        material-stat
    "
>


<div
    class="
        material-stat-icon
        green
    "
>

<i
    class="
        fa-solid
        fa-book-open
    "
></i>

</div>


<div>

<small>
    Available resources
</small>


<strong>
    <?= $totalMaterials ?>
</strong>

</div>


</article>


<article
    class="
        material-stat
    "
>


<div
    class="
        material-stat-icon
        brown
    "
>

<i
    class="
        fa-solid
        fa-unlock
    "
></i>

</div>


<div>

<small>
    Public materials
</small>


<strong>
    <?= $publicMaterials ?>
</strong>

</div>


</article>


<article
    class="
        material-stat
        "
>


<div
    class="
        material-stat-icon
        gold
    "
>

<i
    class="
        fa-solid
        fa-crown
    "
></i>

</div>


<div>

<small>
    Premium materials
</small>


<strong>
    <?= $premiumMaterials ?>
</strong>

</div>


</article>


</section>


<!-- =====================================================
     SEARCH / FILTER
====================================================== -->

<form
    class="
        materials-toolbar
    "
    method="get"
>


<div
    class="
        material-filter-group
    "
>


<div
    class="
        material-search-wrap
    "
>


<i
    class="
        fa-solid
        fa-magnifying-glass
        material-search-icon
    "
></i>


<input
    class="
        material-search
    "
    type="search"
    name="q"
    value="<?= materials_escape(
        $search
    ) ?>"
    placeholder="
        Search title, description or subject...
    "
    autocomplete="off"
>


</div>


<select
    class="
        material-subject
    "
    name="subject_id"
>


<option
    value=""
>
    All subjects
</option>


<?php foreach (
    $subjects
    as $subject
): ?>


<option
    value="<?= (int)$subject['id'] ?>"
    <?= $subjectId ===
        (int)$subject['id']
        ? 'selected'
        : '' ?>
>

<?= materials_escape(
    $subject['name']
) ?>

</option>


<?php endforeach; ?>


</select>


</div>


<button
    type="submit"
    class="
        material-filter-btn
    "
>

<i
    class="
        fa-solid
        fa-filter
    "
></i>

Apply Filter

</button>


</form>


<!-- =====================================================
     HEADER
====================================================== -->

<div
    class="
        materials-section-header
    "
>


<div>


<div
    class="
        materials-section-kicker
    "
>

CURATED FOR YOUR PREPARATION

</div>


<h2>
    Study resources
</h2>


<p>

<?php if (
    $search !== ''
): ?>

Results for
“<?= materials_escape($search) ?>”.

<?php elseif (
    $subjectId !== null
): ?>

Resources for the selected subject.

<?php else: ?>

Browse the latest resources available on ExamSphere.

<?php endif; ?>

</p>


</div>


<div
    class="
        materials-count
    "
>

<?= $totalMaterials ?>

resource<?= $totalMaterials === 1
    ? ''
    : 's' ?>

</div>


</div>


<!-- =====================================================
     MATERIALS
====================================================== -->

<?php if (
    !empty(
        $materials
    )
): ?>


<section
    class="
        materials-grid
    "
>


<?php foreach (
    $materials
    as $material
): ?>


<?php

$isLocked =
    (
        ($material['access_type'] ?? '')
        ===
        'Subscription Only'
    )
    &&
    !$hasSubscription;


$fileExtension =
    strtolower(
        pathinfo(
            (string)(
                $material['file_path']
                ?? ''
            ),
            PATHINFO_EXTENSION
        )
    );


$fileIcon =
    match (
        $fileExtension
    ) {

        'pdf' =>
            'fa-file-pdf',

        'doc',
        'docx' =>
            'fa-file-word',

        'ppt',
        'pptx' =>
            'fa-file-powerpoint',

        'xls',
        'xlsx' =>
            'fa-file-excel',

        default =>
            'fa-file-lines'
    };

?>


<article
    class="
        material-card
    "
>


<div
    class="
        material-top
    "
>


<div
    class="
        material-icon
    "
>

<i
    class="
        fa-solid
        <?= $fileIcon ?>
    "
></i>

</div>


<div
    class="
        material-type
        <?= $isLocked
            ? 'locked'
            : '' ?>"
>

<i
    class="
        fa-solid
        <?= $isLocked
            ? 'fa-lock'
            : 'fa-circle-check' ?>
    "
></i>


<?= materials_escape(
    $material[
        'access_type'
        ]
) ?>

</div>


</div>


<h3
    class="
        material-title
    "
>

<?= materials_escape(
    $material[
        'title'
    ]
) ?>

</h3>


<p
    class="
        material-description
    "
>

<?= materials_escape(
    $material[
        'description'
    ]
    ?:
    'Study resource for examination preparation.'
) ?>

</p>


<div
    class="
        material-meta
    "
>


<span>

<i
    class="
        fa-solid
        fa-book
    "
></i>

<?= materials_escape(
    $material[
        'subject_name'
    ]
    ?:
    'General'
) ?>

</span>


<span>

<i
    class="
        fa-regular
        fa-calendar
    "
></i>

<?= materials_date(
    $material[
        'uploaded_at'
    ]
) ?>

</span>


<span>

<i
    class="
        fa-solid
        fa-file
    "
></i>

<?= strtoupper(
    $fileExtension !== ''
        ? $fileExtension
        : 'FILE'
) ?>

</span>


</div>


<div
    class="
        material-divider
    "
></div>


<div
    class="
        material-access-info
        <?= $isLocked
            ? 'locked'
            : '' ?>"
>


<i
    class="
        fa-solid
        <?= $isLocked
            ? 'fa-lock'
            : 'fa-shield-halved' ?>
    "
></i>


<span>

<?php if (
    $isLocked
): ?>

Subscription required to download this resource.

<?php elseif (
    (
        $material[
            'access_type'
        ]
        ??
        ''
    )
    ===
    'Subscription Only'
): ?>

Premium resource unlocked with your active membership.

<?php else: ?>

Public resource available for download.

<?php endif; ?>

</span>


</div>


<div
    class="
        material-action
    "
>


<?php if (
    $isLocked
): ?>


<a
    href="subscriptions.php"
    class="
        unlock
    "
>

<i
    class="
        fa-solid
        fa-lock
    "
></i>

Unlock with Plan

</a>


<?php else: ?>


<a
    href="
        download_material.php?id=
        <?= (int)$material['id'] ?>
    "
>

<i
    class="
        fa-solid
        fa-download
    "
></i>

Download Resource

</a>


<?php endif; ?>


</div>


</article>


<?php endforeach; ?>


</section>


<?php else: ?>


<!-- =====================================================
     EMPTY
====================================================== -->

<div
    class="
        materials-empty
    "
>


<div
    class="
        materials-empty-icon
    "
>

<i
    class="
        fa-solid
        fa-folder-open
    "
></i>

</div>


<div
    class="
        materials-empty-kicker
    "
>

LEARNING LIBRARY

</div>


<h2>

<?php if (
    $search !== '' ||
    $subjectId !== null
): ?>

No resources match your search.

<?php else: ?>

Your study library is getting ready.

<?php endif; ?>

</h2>


<p>

<?php if (
    $search !== '' ||
    $subjectId !== null
): ?>

Try another keyword or choose
a different subject.

<?php else: ?>

No active study materials have been
published yet. Resources uploaded by
your teachers will appear here automatically.

<?php endif; ?>

</p>


<?php if (
    $search !== '' ||
    $subjectId !== null
): ?>


<a
    href="materials.php"
    class="
        materials-empty-button
    "
>

<i
    class="
        fa-solid
        fa-rotate-left
    "
></i>

Clear Filters

</a>


<?php elseif (
    !$hasSubscription
): ?>


<a
    href="subscriptions.php"
    class="
        materials-empty-button
    "
>

<i
    class="
        fa-solid
        fa-gem
    "
></i>

Explore Membership

</a>


<?php endif; ?>


</div>


<?php endif; ?>


</div>


</main>


<?php

/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
|
| Keep this include only if your existing project has
| student/includes/footer.php.
|
*/

$footerFile =
    __DIR__ .
    '/includes/footer.php';


if (
    is_file(
        $footerFile
    )
) {

    include $footerFile;
}

?>


</body>

</html>