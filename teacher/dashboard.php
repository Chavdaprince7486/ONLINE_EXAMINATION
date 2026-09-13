<?php
declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

require_login('teacher');

$teacherId = current_user_id();
$teacherName = (string)($_SESSION['user_name'] ?? 'Teacher');
$teacherEmail = (string)($_SESSION['user_email'] ?? '');
$error = '';

function td_e(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function td_date(mixed $v): string {
    if (empty($v)) return '—';
    $t = strtotime((string)$v);
    return $t === false ? '—' : date('d M Y', $t);
}
function td_ago(mixed $v): string {
    if (empty($v)) return '—';
    $t = strtotime((string)$v);
    if ($t === false) return '—';
    $s = max(0, time() - $t);
    if ($s < 60) return 'just now';
    $m = intdiv($s, 60);
    if ($m < 60) return $m . ' min ago';
    $h = intdiv($m, 60);
    if ($h < 24) return $h . ' hr' . ($h === 1 ? '' : 's') . ' ago';
    $d = intdiv($h, 24);
    return $d < 7 ? $d . ' day' . ($d === 1 ? '' : 's') . ' ago' : date('d M Y', $t);
}
function td_status(array $exam): string {
    $status = (string)$exam['status'];
    $start = !empty($exam['starts_at']) ? strtotime((string)$exam['starts_at']) : false;
    $end = !empty($exam['ends_at']) ? strtotime((string)$exam['ends_at']) : false;
    if ($status === 'Active' && $start !== false && $start > time()) return 'Upcoming';
    if (in_array($status, ['Active','Upcoming','Running','Live','Scheduled'], true) && $end !== false && $end < time()) return 'Completed';
    return $status;
}
function td_status_class(string $status): string {
    return match ($status) {
        'Active','Running','Live' => 'status-active',
        'Upcoming','Scheduled' => 'status-upcoming',
        'Completed' => 'status-completed',
        'Cancelled' => 'status-cancelled',
        default => 'status-draft'
    };
}

$teacher = [];
$totalStudents = $totalExams = $totalQuestions = $totalAttempts = 0;
$averageScore = 0.0;
$recentExams = [];
$activities = [];
$performance = ['Excellent'=>0,'Good'=>0,'Average'=>0,'Poor'=>0];

try {
    $s = $conn->prepare("SELECT id, teacher_code, full_name, email, profile_photo, status, last_login FROM teachers WHERE id=? LIMIT 1");
    $s->execute([$teacherId]);
    $teacher = $s->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($teacher) {
        $teacherName = (string)$teacher['full_name'];
        $teacherEmail = (string)$teacher['email'];
    }

    $s = $conn->prepare("SELECT COUNT(*) FROM exams WHERE teacher_id=?");
    $s->execute([$teacherId]);
    $totalExams = (int)$s->fetchColumn();

    $s = $conn->prepare("SELECT COUNT(*) FROM questions WHERE created_by_teacher_id=?");
    $s->execute([$teacherId]);
    $totalQuestions = (int)$s->fetchColumn();

    $s = $conn->prepare("
        SELECT COUNT(*) total_attempts, COUNT(DISTINCT ea.student_id) total_students
        FROM exam_attempts ea
        INNER JOIN exams e ON e.id=ea.exam_id
        WHERE e.teacher_id=?
    ");
    $s->execute([$teacherId]);
    $x = $s->fetch(PDO::FETCH_ASSOC) ?: [];
    $totalAttempts = (int)($x['total_attempts'] ?? 0);
    $totalStudents = (int)($x['total_students'] ?? 0);

    $s = $conn->prepare("
        SELECT COALESCE(AVG(r.percentage),0)
        FROM results r
        INNER JOIN exams e ON e.id=r.exam_id
        WHERE e.teacher_id=?
    ");
    $s->execute([$teacherId]);
    $averageScore = round((float)$s->fetchColumn(), 1);

    $s = $conn->prepare("
        SELECT e.id,e.title,e.exam_type,e.status,e.required_question_count,e.total_marks,e.starts_at,e.ends_at,e.created_at,
               sub.name subject_name, COUNT(eq.question_id) question_count
        FROM exams e
        LEFT JOIN subjects sub ON sub.id=e.subject_id
        LEFT JOIN exam_questions eq ON eq.exam_id=e.id
        WHERE e.teacher_id=?
        GROUP BY e.id,e.title,e.exam_type,e.status,e.required_question_count,e.total_marks,e.starts_at,e.ends_at,e.created_at,sub.name
        ORDER BY e.created_at DESC,e.id DESC
        LIMIT 5
    ");
    $s->execute([$teacherId]);
    $recentExams = $s->fetchAll(PDO::FETCH_ASSOC);

    $s = $conn->prepare("
        SELECT CASE
            WHEN r.percentage>=80 THEN 'Excellent'
            WHEN r.percentage>=60 THEN 'Good'
            WHEN r.percentage>=40 THEN 'Average'
            ELSE 'Poor' END performance_level, COUNT(*) total
        FROM results r
        INNER JOIN exams e ON e.id=r.exam_id
        WHERE e.teacher_id=?
        GROUP BY performance_level
    ");
    $s->execute([$teacherId]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($performance[$row['performance_level']])) $performance[$row['performance_level']] = (int)$row['total'];
    }

    $s = $conn->prepare("
        (SELECT e.created_at activity_time,'Exam created' activity_type,e.title activity_title FROM exams e WHERE e.teacher_id=?)
        UNION ALL
        (SELECT q.created_at activity_time,'Question added' activity_type,q.question_text activity_title FROM questions q WHERE q.created_by_teacher_id=?)
        UNION ALL
        (SELECT m.uploaded_at activity_time,'Material published' activity_type,m.title activity_title FROM study_materials m WHERE m.teacher_id=?)
        UNION ALL
        (SELECT r.created_at activity_time,'Result declared' activity_type,e.title activity_title
         FROM results r INNER JOIN exams e ON e.id=r.exam_id WHERE e.teacher_id=?)
        ORDER BY activity_time DESC LIMIT 6
    ");
    $s->execute([$teacherId,$teacherId,$teacherId,$teacherId]);
    $activities = $s->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $ex) {
    error_log('Teacher dashboard failed: '.$ex->getMessage());
    $error = 'Some dashboard data could not be loaded right now.';
}

$photoUrl = '';
if (!empty($teacher['profile_photo'])) {
    $photoUrl = '../uploads/teachers/'.rawurlencode(basename((string)$teacher['profile_photo']));
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Teacher Dashboard | ExamSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/portal.css">
<style>
:root{
--earth:#5d4037;--earth-dark:#422d26;--olive:#556b2f;--muted:#746961;--line:#e1d8cc;
--soft:#fbfaf6;--green:#426b35;--red:#944b43;--amber:#8e6a2e;
}
*{box-sizing:border-box}
body.portal-body{
 margin:0;color:#302823;font-family:Poppins,sans-serif;font-size:16px;font-weight:500;
 background:radial-gradient(circle at 5% 0%,rgba(85,107,47,.08),transparent 27%),linear-gradient(135deg,#faf8f2,#efebe4)
}
.dashboard{width:min(1460px,calc(100vw - 24px));margin:auto;padding:30px 0 78px}
.head{display:flex;justify-content:space-between;align-items:flex-end;gap:28px;margin-bottom:30px}
.kicker{color:var(--olive);font-size:.86rem;font-weight:900;letter-spacing:.13em}
.head h1{margin:8px 0 8px;color:var(--earth);font-size:2.85rem;line-height:1.12;font-weight:900;letter-spacing:-.035em}
.head p{margin:0;color:#6c625b;font-size:1rem;font-weight:600;line-height:1.55}
.today{display:flex;align-items:center;gap:13px;padding:15px 19px;border:1px solid #d8dfcf;border-radius:16px;background:#eef3e8;box-shadow:0 8px 22px rgba(61,44,36,.04)}
.today i{color:var(--olive);font-size:1.08rem}.today small{display:block;color:#746b63;font-size:.74rem;font-weight:600}.today strong{display:block;color:var(--earth);font-size:.88rem;line-height:1.5;font-weight:850}
.alert{border-radius:13px;font-size:.9rem;font-weight:700}
.kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:17px;margin-bottom:25px}
.kpi,.card{border:1px solid rgba(93,64,55,.09);border-radius:21px;background:rgba(255,255,255,.97);box-shadow:0 18px 42px rgba(61,44,36,.07)}
.kpi{padding:20px;min-height:151px;transition:transform .2s ease,box-shadow .2s ease}.kpi:hover{transform:translateY(-2px);box-shadow:0 22px 48px rgba(61,44,36,.11)}
.kpi-icon{width:47px;height:47px;display:flex;align-items:center;justify-content:center;border-radius:13px;background:#eef3e8;color:var(--olive);font-size:1.08rem}
.kpi:nth-child(even) .kpi-icon{background:#f2eae2;color:var(--earth)}
.kpi small{display:block;margin-top:14px;color:#514942;font-size:.86rem;font-weight:850;line-height:1.4}
.kpi strong{display:block;margin-top:3px;color:var(--earth);font-size:1.95rem;line-height:1.18;font-weight:900;letter-spacing:-.025em}
.kpi a{display:inline-block;margin-top:8px;color:var(--olive);text-decoration:none;font-size:.82rem;font-weight:900}
.grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(430px,.78fr);gap:22px;margin-bottom:22px;align-items:stretch}
.card{overflow:hidden;min-width:0}.card-head{display:flex;justify-content:space-between;gap:18px;align-items:center;padding:24px 25px;border-bottom:1px solid var(--line)}
.card-head h2{margin:0;color:var(--earth);font-size:1.55rem;font-weight:900;line-height:1.25;letter-spacing:-.02em}.card-head h2:after{content:"";display:block;width:42px;height:3px;margin-top:8px;border-radius:99px;background:var(--olive)}
.card-head p{margin:7px 0 0;color:#665d55;font-size:.91rem;line-height:1.55;font-weight:650}.link{color:var(--olive);text-decoration:none;font-size:.91rem;font-weight:900;white-space:nowrap}
.list{padding:4px 23px 13px}.row{display:flex;justify-content:space-between;gap:26px;padding:21px 0;border-bottom:1px solid #ece5dd}.row:last-child{border-bottom:0}
.main{min-width:0}.main strong{display:block;overflow:hidden;color:var(--earth);font-size:1rem;line-height:1.45;font-weight:900;text-overflow:ellipsis;white-space:nowrap}.main span{display:block;margin-top:7px;color:#756c64;font-size:.78rem;line-height:1.5;font-weight:600}
.side{text-align:right;white-space:nowrap}.side strong{display:block;color:#4a3028;font-size:.9rem;line-height:1.45;font-weight:900}.side span{display:block;margin-top:5px;color:#756c64;font-size:.76rem;font-weight:650}
.badge-status{display:inline-flex;margin-top:8px;padding:7px 11px;border-radius:999px;font-size:.74rem;font-weight:900}
.status-active{background:#eaf3e6;color:var(--green)}.status-upcoming{background:#fff2d9;color:var(--amber)}.status-completed{background:#eeeae4;color:#625951}.status-cancelled{background:#fae8e5;color:var(--red)}.status-draft{background:#f4eee5;color:#7d613d}
.chart{padding:22px 23px 24px}.bars{height:285px;display:flex;align-items:flex-end;gap:18px}.barwrap{flex:1;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:flex-end}.barvalue{font-size:.83rem;color:var(--earth);font-weight:900}.bar{width:64%;min-height:8px;border-radius:10px 10px 4px 4px;background:linear-gradient(180deg,var(--olive),#879b5f)}.barlabel{margin-top:11px;color:#6e655e;font-size:.78rem;font-weight:800}
.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:11px;margin-top:18px}.sum{padding:13px 10px;text-align:center;border-radius:13px;background:#f8f4ec}.sum strong{display:block;color:var(--earth);font-size:1.04rem;font-weight:900}.sum span{display:block;margin-top:3px;color:#716860;font-size:.68rem;font-weight:750}
.activities{padding:7px 25px 18px}.activity{display:flex;gap:15px;padding:18px 0;border-bottom:1px solid #e9e1d7;align-items:center}.activity:last-child{border-bottom:0}.icon{width:48px;height:48px;flex:0 0 48px;display:flex;align-items:center;justify-content:center;border-radius:14px;background:#eef3e8;color:var(--olive);font-size:.98rem}.activity div:nth-child(2){min-width:0;flex:1}.activity strong{display:block;color:var(--earth);font-size:.98rem;line-height:1.4;font-weight:900}.activity span{display:block;margin-top:5px;overflow:hidden;color:#5f5750;font-size:.84rem;line-height:1.5;font-weight:600;text-overflow:ellipsis;white-space:nowrap}.time{margin-left:auto;color:#6c625b;font-size:.82rem;font-weight:800;white-space:nowrap}
.quickgrid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;padding:21px 23px 23px}.quick{display:flex;align-items:center;gap:12px;padding:16px 15px;min-width:0;min-height:76px;border:1px solid var(--line);border-radius:16px;background:#fcfaf6;color:var(--earth);text-decoration:none;transition:transform .18s ease,box-shadow .18s ease,background .18s ease,border-color .18s ease}.quick:hover{background:#f6f1e8;color:var(--earth);transform:translateY(-2px);box-shadow:0 10px 24px rgba(61,44,36,.07);border-color:#d6cabb}.quick i{width:42px;height:42px;display:flex;align-items:center;justify-content:center;border-radius:12px;background:#eef3e8;color:var(--olive);font-size:.95rem;flex:0 0 42px}.quick strong{display:block;font-size:.88rem;line-height:1.35;font-weight:900;overflow-wrap:anywhere}.quick span{display:block;margin-top:4px;color:#655d56;font-size:.76rem;line-height:1.4;font-weight:650}
.empty{padding:52px 20px;text-align:center;color:#746b63}.empty i{font-size:2rem;color:#a39a91}.empty strong{display:block;margin-top:10px;color:var(--earth);font-size:.98rem;font-weight:900}.empty span{display:block;margin-top:5px;font-size:.76rem;line-height:1.55;font-weight:600}
@media(max-width:1250px){.grid{grid-template-columns:minmax(0,1.35fr) minmax(390px,.78fr)}.quickgrid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:1100px){.kpis{grid-template-columns:repeat(3,1fr)}.grid{grid-template-columns:1fr}.head h1{font-size:2.4rem}}
@media(max-width:700px){.dashboard{width:calc(100vw - 18px)}.head{align-items:flex-start;flex-direction:column}.head h1{font-size:2.05rem}.head p{font-size:.9rem}.kpis{grid-template-columns:1fr 1fr}.summary{grid-template-columns:1fr 1fr}.quickgrid{grid-template-columns:1fr 1fr}.row{gap:15px}.activity{align-items:flex-start}.time{font-size:.74rem}}
@media(max-width:430px){.kpis,.quickgrid{grid-template-columns:1fr}.row{align-items:flex-start;flex-direction:column}.side{text-align:left;width:100%}.head h1{font-size:1.8rem}.head p{font-size:.84rem}.today{width:100%}}
.quick-card .quickgrid{grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.quick-card .quick{min-height:82px}.faculty-card .quickgrid{grid-template-columns:repeat(4,minmax(0,1fr))}.faculty-card .quick{min-height:84px}.faculty-card .quick strong{font-size:.9rem}.faculty-card .quick span{font-size:.77rem}
@media(max-width:1250px){.faculty-card .quickgrid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:700px){.quick-card .quickgrid,.faculty-card .quickgrid{grid-template-columns:1fr 1fr}}
</style>
</head>
<body class="portal-body">
<div class="portal-layout">
<?php include 'includes/sidebar.php'; ?>
<main class="portal-main">
<div class="dashboard">

<header class="head">
<div>
<div class="kicker"><i class="fa-solid fa-chalkboard-user me-1"></i>TEACHER DASHBOARD</div>
<h1>Welcome back, <?=td_e($teacherName)?>! 👋</h1>
<p>Here's what's happening with your examinations today.</p>
</div>
<div class="today"><i class="fa-regular fa-calendar-days"></i><div><small>Today is</small><strong><?=date('d M Y')?> | <?=date('l')?></strong></div></div>
</header>

<?php if($error): ?><div class="alert alert-warning mb-3"><i class="fa-solid fa-circle-exclamation me-1"></i><?=td_e($error)?></div><?php endif; ?>

<section class="kpis">
<div class="kpi"><div class="kpi-icon"><i class="fa-solid fa-users"></i></div><small>Students Reached</small><strong><?=$totalStudents?></strong><a href="results.php">View results →</a></div>
<div class="kpi"><div class="kpi-icon"><i class="fa-solid fa-file-lines"></i></div><small>Total Exams</small><strong><?=$totalExams?></strong><a href="exams.php">View all exams →</a></div>
<div class="kpi"><div class="kpi-icon"><i class="fa-solid fa-circle-question"></i></div><small>Total Questions</small><strong><?=$totalQuestions?></strong><a href="questions.php">Question bank →</a></div>
<div class="kpi"><div class="kpi-icon"><i class="fa-solid fa-chart-simple"></i></div><small>Total Attempts</small><strong><?=$totalAttempts?></strong><a href="results.php">Result history →</a></div>
<div class="kpi"><div class="kpi-icon"><i class="fa-solid fa-arrow-trend-up"></i></div><small>Average Score</small><strong><?=number_format($averageScore,1)?>%</strong><a href="results.php">View performance →</a></div>
</section>

<div class="grid">
<section class="card">
<header class="card-head"><div><h2>Recent Exams</h2><p>Your latest examinations and current state.</p></div><a class="link" href="exams.php">View all →</a></header>
<div class="list">
<?php if($recentExams): foreach($recentExams as $exam): $st=td_status($exam); ?>
<div class="row">
<div class="main"><strong><?=td_e($exam['title'])?></strong><span><?=td_e($exam['subject_name']??'General')?> · <?=td_e($exam['exam_type'])?> · <?=td_date($exam['created_at'])?></span></div>
<div class="side"><strong><?=$exam['question_count']?> / <?=$exam['required_question_count']?> questions</strong><span><?=number_format((float)$exam['total_marks'],2)?> marks</span><span class="badge-status <?=td_status_class($st)?>"><?=td_e($st)?></span></div>
</div>
<?php endforeach; else: ?><div class="empty"><i class="fa-solid fa-file-circle-xmark"></i><strong>No examinations yet.</strong><span>Create your first examination to start building your workspace.</span></div><?php endif; ?>
</div>
</section>

<section class="card">
<header class="card-head"><div><h2>Performance Overview</h2><p>Student results grouped by percentage.</p></div></header>
<div class="chart">
<div class="bars">
<?php $max=max(1,...array_values($performance)); foreach($performance as $label=>$value): $h=$value?max(8,(int)round($value/$max*100)):4; ?>
<div class="barwrap"><div class="barvalue"><?=$value?></div><div class="bar" style="height:<?=$h?>%"></div><div class="barlabel"><?=td_e($label)?></div></div>
<?php endforeach; ?>
</div>
<div class="summary">
<?php foreach($performance as $label=>$value): ?><div class="sum"><strong><?=$value?></strong><span><?=td_e($label)?></span></div><?php endforeach; ?>
</div>
</div>
</section>
</div>

<div class="grid">
<section class="card">
<header class="card-head"><div><h2>Recent Activity</h2><p>Latest actions from your teacher workspace.</p></div></header>
<div class="activities">
<?php if($activities): foreach($activities as $a):
$icon = match($a['activity_type']) {
'Exam created'=>'fa-file-circle-plus','Question added'=>'fa-circle-question','Material published'=>'fa-book-open','Result declared'=>'fa-chart-column',default=>'fa-bell'
}; ?>
<div class="activity">
<div class="icon"><i class="fa-solid <?=td_e($icon)?>"></i></div>
<div><strong><?=td_e($a['activity_type'])?></strong><span><?=td_e($a['activity_title'])?></span></div>
<div class="time"><?=td_e(td_ago($a['activity_time']))?></div>
</div>
<?php endforeach; else: ?><div class="empty"><i class="fa-solid fa-clock-rotate-left"></i><strong>No recent activity.</strong><span>Your latest actions will appear here.</span></div><?php endif; ?>
</div>
</section>

<section class="card quick-card">
<header class="card-head"><div><h2>Quick Actions</h2><p>Jump directly to common teacher tasks.</p></div></header>
<div class="quickgrid">
<a class="quick" href="create_exam.php"><i class="fa-solid fa-plus"></i><div><strong>Create Exam</strong><span>Build a new exam</span></div></a>
<a class="quick" href="questions.php"><i class="fa-solid fa-circle-question"></i><div><strong>Question Bank</strong><span>Manage questions</span></div></a>
<a class="quick" href="import_questions.php"><i class="fa-solid fa-file-csv"></i><div><strong>Import CSV</strong><span>Bulk questions</span></div></a>
<a class="quick" href="materials.php"><i class="fa-solid fa-book-open"></i><div><strong>Materials</strong><span>Publish resources</span></div></a>
<a class="quick" href="results.php"><i class="fa-solid fa-chart-line"></i><div><strong>Results</strong><span>View student results</span></div></a>
<a class="quick" href="exam_questions.php"><i class="fa-solid fa-list-check"></i><div><strong>Exam Builder</strong><span>Assign questions</span></div></a>
<a class="quick" href="profile.php"><i class="fa-solid fa-user"></i><div><strong>My Profile</strong><span>Update account</span></div></a>
<a class="quick" href="exams.php"><i class="fa-solid fa-layer-group"></i><div><strong>Exam Library</strong><span>Manage all exams</span></div></a>
</div>
</section>
</div>

<section class="card faculty-card">
<header class="card-head"><div><h2>Faculty Account</h2><p>Current teacher account information.</p></div><a class="link" href="profile.php">Manage profile →</a></header>
<div class="quickgrid" style="grid-template-columns:repeat(4,minmax(0,1fr));">
<div class="quick"><?php if($photoUrl): ?><img src="<?=td_e($photoUrl)?>" alt="Teacher profile" style="width:34px;height:34px;border-radius:9px;object-fit:cover;"><?php else: ?><i class="fa-solid fa-user"></i><?php endif; ?><div><strong><?=td_e($teacherName)?></strong><span>Teacher</span></div></div>
<div class="quick"><i class="fa-solid fa-id-badge"></i><div><strong><?=td_e($teacher['teacher_code']??'Faculty')?></strong><span>Teacher Code</span></div></div>
<div class="quick"><i class="fa-solid fa-envelope"></i><div><strong><?=td_e($teacherEmail)?></strong><span>Email</span></div></div>
<div class="quick"><i class="fa-solid fa-clock"></i><div><strong><?=td_date($teacher['last_login']??null)?></strong><span>Last Login</span></div></div>
</div>
</section>

</div>
</main>
</div>
</body>
</html>
