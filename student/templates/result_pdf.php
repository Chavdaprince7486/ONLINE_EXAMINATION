<?php

declare(strict_types=1);

$logoPath = realpath(
    dirname(__DIR__, 2) .
    DIRECTORY_SEPARATOR .
    'assets' .
    DIRECTORY_SEPARATOR .
    'images' .
    DIRECTORY_SEPARATOR .
    'exam_logo.png'
);

$logoDataUri = '';
if ($logoPath !== false && is_file($logoPath)) {
    $logoBinary = file_get_contents($logoPath);
    if ($logoBinary !== false && $logoBinary !== '') {
        $logoDataUri = 'data:image/png;base64,' . base64_encode($logoBinary);
    }
}

$resultDate = $result['submitted_at'] ?: $result['result_created_at'];

try {
    $formattedDate = (new DateTimeImmutable($resultDate))->format('d M Y, h:i A');
} catch (Throwable $exception) {
    $formattedDate = (string)$resultDate;
}

if ($timeTakenMinutes === null) {
    $timeTakenText = 'Not available';
} elseif ($timeTakenMinutes >= 60) {
    $timeTakenText = intdiv($timeTakenMinutes, 60) . ' hr ' . ($timeTakenMinutes % 60) . ' min';
} else {
    $timeTakenText = $timeTakenMinutes . ' min';
}

$isPassed = $result['result_status'] === 'Pass';
$statusText = $isPassed ? 'PASS' : 'FAIL';
$statusColor = $isPassed ? '#557233' : '#A23E32';
$statusBg = $isPassed ? '#EDF4E4' : '#FBECE8';

$attemptedPercent = $totalQuestions > 0 ? min(100, round(($attemptedQuestions / $totalQuestions) * 100)) : 0;
$correctPercent = $totalQuestions > 0 ? min(100, round(($correctAnswers / $totalQuestions) * 100)) : 0;
$wrongPercent = $totalQuestions > 0 ? min(100, round(($wrongAnswers / $totalQuestions) * 100)) : 0;
$unansweredPercent = $totalQuestions > 0 ? min(100, round(($unansweredQuestions / $totalQuestions) * 100)) : 0;

$scoreAngle = min(360, max(0, round($percentage * 3.6)));

if ($percentage >= 90) {
    $remark = 'Outstanding Performance';
    $remarkText = 'Your score demonstrates excellent preparation and strong consistency.';
} elseif ($percentage >= 75) {
    $remark = 'Excellent Performance';
    $remarkText = 'You have built a strong foundation. Keep the same consistency in your preparation.';
} elseif ($percentage >= 60) {
    $remark = 'Very Good Performance';
    $remarkText = 'You are progressing well. Continue practising and focus on improving weak areas.';
} elseif ($percentage >= 40) {
    $remark = 'Good Effort';
    $remarkText = 'Review incorrect answers and practise regularly to improve your next attempt.';
} else {
    $remark = 'Keep Practising';
    $remarkText = 'Review your incorrect answers and continue practising for a stronger next attempt.';
}
?>

<div class="report">

    <table class="masthead">
        <tr>
            <td class="brand-cell">
                <?php if ($logoPath !== false && is_file($logoPath)): ?>
                    <img src="<?= pdf_escape($logoDataUri !== '' ? $logoDataUri : $logoPath); ?>" class="brand-logo" alt="ExamSphere">
                <?php endif; ?>
                <div class="brand-copy">
                    <div class="brand-name">ExamSphere</div>
                    <div class="brand-tagline">SMART ASSESSMENT · SEAMLESS LEARNING · REAL RESULTS</div>
                </div>
            </td>
            <td class="report-meta">
                <div class="meta-kicker">OFFICIAL RESULT REPORT</div>
                <div class="meta-main">RESULT #<?= (int)$result['result_id']; ?></div>
                <div class="meta-line">ATTEMPT #<?= (int)$result['attempt_id']; ?></div>
                <div class="meta-line"><?= pdf_escape($formattedDate); ?></div>
            </td>
        </tr>
    </table>

    <table class="hero">
        <tr>
            <td class="hero-left">
                <div class="eyebrow">EXAMSPHERE PERFORMANCE REPORT</div>
                <div class="exam-title"><?= pdf_escape($result['exam_title']); ?></div>
                <div class="exam-meta">
                    <?= pdf_escape($result['subject_name'] ?: 'General'); ?>
                    <?php if (!empty($result['subject_code'])): ?>
                        · <?= pdf_escape($result['subject_code']); ?>
                    <?php endif; ?>
                    · <?= pdf_escape($result['exam_type']); ?>
                </div>
                <div class="hero-copy">
                    A verified performance summary for the completed examination attempt.
                </div>
            </td>
            <td class="score-panel">
                <div class="score-ring" style="--score-angle:<?= $scoreAngle; ?>deg;">
                    <div class="score-ring-inner">
                        <div class="score-small">FINAL SCORE</div>
                        <div class="score-big"><?= pdf_number($percentage); ?>%</div>
                        <div class="score-status" style="color:<?= $isPassed ? '#E5F0D4' : '#F7D8D2'; ?>">
                            <?= $statusText; ?> · Grade <?= pdf_escape($result['grade']); ?>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="section-card profile-card">
        <div class="section-bar">
            <span class="section-id">01</span>
            <span>STUDENT INFORMATION</span>
        </div>
        <table class="profile-table">
            <tr>
                <td><div class="label">STUDENT NAME</div><div class="value"><?= pdf_escape($result['full_name']); ?></div></td>
                <td><div class="label">STUDENT CODE</div><div class="value"><?= pdf_escape($result['student_code']); ?></div></td>
                <td><div class="label">EMAIL</div><div class="value"><?= pdf_escape($result['email']); ?></div></td>
            </tr>
            <tr>
                <td><div class="label">SUBJECT</div><div class="value"><?= pdf_escape($result['subject_name'] ?: 'General'); ?></div></td>
                <td><div class="label">EXAM TYPE</div><div class="value"><?= pdf_escape($result['exam_type']); ?></div></td>
                <td><div class="label">RESULT DATE</div><div class="value"><?= pdf_escape($formattedDate); ?></div></td>
            </tr>
        </table>
    </div>

    <table class="two-col-row">
        <tr>
            <td>
                <div class="section-card compact">
                    <div class="section-bar"><span class="section-id">02</span><span>SCORE OVERVIEW</span></div>
                    <table class="metric-grid">
                        <tr>
                            <td><div class="metric olive"><div class="metric-label">OBTAINED</div><div class="metric-value"><?= pdf_number($obtainedMarks); ?><span>/<?= pdf_number($totalMarks); ?></span></div></div></td>
                            <td><div class="metric green"><div class="metric-label">CORRECT</div><div class="metric-value"><?= $correctAnswers; ?></div></div></td>
                        </tr>
                        <tr>
                            <td><div class="metric red"><div class="metric-label">WRONG</div><div class="metric-value"><?= $wrongAnswers; ?></div></div></td>
                            <td><div class="metric gold"><div class="metric-label">ACCURACY</div><div class="metric-value dark"><?= pdf_number($accuracy); ?>%</div></div></td>
                        </tr>
                    </table>
                </div>
            </td>
            <td>
                <div class="section-card compact">
                    <div class="section-bar"><span class="section-id">03</span><span>QUESTION ANALYSIS</span></div>
                    <table class="analysis-table">
                        <tr><td>Attempted</td><td><div class="track"><span style="width:<?= $attemptedPercent; ?>%;background:#71874D;"></span></div></td><td><?= $attemptedQuestions; ?><small>(<?= $attemptedPercent; ?>%)</small></td></tr>
                        <tr><td>Correct</td><td><div class="track"><span style="width:<?= $correctPercent; ?>%;background:#557233;"></span></div></td><td><?= $correctAnswers; ?><small>(<?= $correctPercent; ?>%)</small></td></tr>
                        <tr><td>Wrong</td><td><div class="track"><span style="width:<?= $wrongPercent; ?>%;background:#A23E32;"></span></div></td><td><?= $wrongAnswers; ?><small>(<?= $wrongPercent; ?>%)</small></td></tr>
                        <tr><td>Unanswered</td><td><div class="track"><span style="width:<?= $unansweredPercent; ?>%;background:#B79C68;"></span></div></td><td><?= $unansweredQuestions; ?><small>(<?= $unansweredPercent; ?>%)</small></td></tr>
                    </table>
                    <div class="total-line"><span>Total Questions</span><strong><?= $totalQuestions; ?></strong></div>
                </div>
            </td>
        </tr>
    </table>

    <div class="section-card detail-card">
        <div class="section-bar"><span class="section-id">04</span><span>EXAM & MARKING DETAILS</span></div>
        <table class="detail-grid">
            <tr>
                <td><div class="label">TOTAL MARKS</div><div class="value"><?= pdf_number($totalMarks); ?></div></td>
                <td><div class="label">OBTAINED MARKS</div><div class="value accent"><?= pdf_number($obtainedMarks); ?></div></td>
                <td><div class="label">PASSING MARKS</div><div class="value"><?= pdf_number($passingMarks); ?></div></td>
                <td><div class="label">NEGATIVE MARKING</div><div class="value"><?= $negativeMarking ? 'Enabled' : 'Disabled'; ?></div></td>
            </tr>
            <tr>
                <td><div class="label">EXAM DURATION</div><div class="value"><?= (int)$result['duration_minutes']; ?> min</div></td>
                <td><div class="label">TIME TAKEN</div><div class="value"><?= pdf_escape($timeTakenText); ?></div></td>
                <td><div class="label">RESULT</div><div class="value" style="color:<?= $statusColor; ?>;"><?= $statusText; ?></div></td>
                <td><div class="label">GRADE</div><div class="value accent"><?= pdf_escape($result['grade']); ?></div></td>
            </tr>
        </table>
    </div>

    <div class="section-card review-card">
        <div class="section-bar"><span class="section-id">05</span><span>PERFORMANCE SUMMARY</span></div>
        <table class="review-table">
            <tr>
                <td class="review-score-cell">
                    <div class="review-score-label">YOUR RESULT</div>
                    <div class="review-score"><?= pdf_number($percentage); ?>%</div>
                    <div class="review-grade">Grade <?= pdf_escape($result['grade']); ?></div>
                </td>
                <td class="review-copy-cell">
                    <div class="status-chip" style="color:<?= $statusColor; ?>;background:<?= $statusBg; ?>;">
                        <?= $statusText; ?>
                    </div>
                    <div class="review-title"><?= pdf_escape($remark); ?></div>
                    <div class="review-copy"><?= pdf_escape($remarkText); ?></div>
                </td>
            </tr>
        </table>
    </div>

    <?php if (!empty($result['exam_description'])): ?>
        <div class="section-card about-card">
            <div class="section-bar"><span class="section-id">06</span><span>ABOUT THIS EXAM</span></div>
            <div class="about-copy"><?= pdf_escape($result['exam_description']); ?></div>
        </div>
    <?php endif; ?>

    <div class="verification-banner">
        <table>
            <tr>
                <td class="verify-mark">✓</td>
                <td>
                    <div class="verify-title">Official ExamSphere Result</div>
                    <div class="verify-copy">This report is generated from the completed examination result stored for the authenticated student account.</div>
                </td>
                <td class="verify-id">
                    RESULT #<?= (int)$result['result_id']; ?><br>
                    ATTEMPT #<?= (int)$result['attempt_id']; ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <div class="footer-brand">ExamSphere</div>
        <div>Secure Examination Platform · Smart Assessment · Real Results</div>
        <div class="footer-note">Generated for authenticated student use · <?= pdf_escape($formattedDate); ?></div>
    </div>

</div>
