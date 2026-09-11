<?php
require_once 'config/config.php';

function public_count(PDO $connection, string $table): int
{
    $allowedTables = [
        'students',
        'exams',
        'questions'
    ];

    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    try {
        return (int) $connection
            ->query("SELECT COUNT(*) FROM {$table}")
            ->fetchColumn();
    } catch (Throwable $exception) {
        error_log(
            'ExamSphere public count failed: ' .
            $exception->getMessage()
        );

        return 0;
    }
}

$studentCount = public_count($conn, 'students');
$examCount = public_count($conn, 'exams');
$questionCount = public_count($conn, 'questions');
?>

<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<main id="premiumLanding">

    <!-- =====================================================
         HERO
    ====================================================== -->
    <section class="landing-hero" id="home">

        <div class="landing-orb orb-one"></div>
        <div class="landing-orb orb-two"></div>

        <div class="container landing-hero-grid">

            <div class="hero-copy reveal">

                <span class="landing-kicker">
                    <i class="fa-solid fa-sparkles"></i>
                    SMART • SECURE • INSTANT
                </span>

                <h1>
                    Make every exam
                    <br>
                    <em>your next success.</em>
                </h1>

                <p>
                    ExamSphere brings unlimited practice, scheduled live exams,
                    instant results and study materials into one elegant learning platform.
                </p>

                <div class="landing-actions">

                    <a
                        class="landing-btn primary"
                        href="auth/register.php"
                    >
                        <i class="fa-solid fa-rocket"></i>
                        Start practising
                    </a>

                    <a
                        class="landing-btn secondary"
                        href="#how-it-works"
                    >
                        <i class="fa-regular fa-circle-play"></i>
                        How it works
                    </a>

                </div>

                <div class="hero-trust">

                    <span>
                        <i class="fa-solid fa-circle-check"></i>
                        Free practice access
                    </span>

                    <span>
                        <i class="fa-solid fa-circle-check"></i>
                        Instant evaluation
                    </span>

                </div>

            </div>


            <div class="hero-visual reveal">

                <div class="visual-glow"></div>

                <div class="exam-orbit orbit-a"></div>
                <div class="exam-orbit orbit-b"></div>

                <div class="hero-logo-plate">

                    <img
                        src="assets/images/exam_logo.png"
                        alt="ExamSphere"
                    >

                </div>

                <div class="floating-score">

                    <i class="fa-solid fa-trophy"></i>

                    <span>
                        <b>92%</b>
                        <small>Best score</small>
                    </span>

                </div>

                <div class="floating-result">

                    <i class="fa-solid fa-circle-check"></i>

                    <span>
                        <b>Result ready</b>
                        <small>Instantly evaluated</small>
                    </span>

                </div>

                <div class="hero-dashboard-card">

                    <div>

                        <span>Today’s preparation</span>

                        <b>Keep moving forward</b>

                    </div>

                    <div class="mini-bars">
                        <i></i>
                        <i></i>
                        <i></i>
                        <i></i>
                        <i></i>
                    </div>

                    <p>
                        <i class="fa-solid fa-chart-line"></i>
                        Your learning journey, clearly organised.
                    </p>

                </div>

            </div>

        </div>

    </section>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->
    <section class="landing-stats">

        <div class="container glass-stat-strip reveal">

            <div>

                <i class="fa-solid fa-user-graduate"></i>

                <span>

                    <b
                        class="counter"
                        data-target="<?= $studentCount ?>"
                    >
                        0
                    </b>

                    <small>
                        Registered learners
                    </small>

                </span>

            </div>


            <div>

                <i class="fa-solid fa-file-circle-check"></i>

                <span>

                    <b
                        class="counter"
                        data-target="<?= $examCount ?>"
                    >
                        0
                    </b>

                    <small>
                        Available exams
                    </small>

                </span>

            </div>


            <div>

                <i class="fa-solid fa-circle-question"></i>

                <span>

                    <b
                        class="counter"
                        data-target="<?= $questionCount ?>"
                    >
                        0
                    </b>

                    <small>
                        Practice questions
                    </small>

                </span>

            </div>


            <div>

                <i class="fa-solid fa-bolt"></i>

                <span>

                    <b>Instant</b>

                    <small>
                        Result generation
                    </small>

                </span>

            </div>

        </div>

    </section>


    <!-- =====================================================
         WHY EXAMSPHERE
    ====================================================== -->
    <section
        class="landing-section feature-section"
        id="why-choose"
    >

        <div class="container">

            <div class="landing-heading reveal">

                <span>
                    WHY EXAMSPHERE
                </span>

                <h2>
                    Everything you need to learn with confidence.
                </h2>

                <p>
                    A focused platform for students, teachers and administrators—
                    designed to stay simple, secure and easy to use.
                </p>

            </div>


            <div class="premium-feature-grid">

                <article class="premium-feature reveal">

                    <i class="fa-solid fa-infinity"></i>

                    <h3>
                        Unlimited practice
                    </h3>

                    <p>
                        Take practice exams as many times as you need,
                        without a subscription.
                    </p>

                </article>


                <article class="premium-feature reveal">

                    <i class="fa-solid fa-calendar-check"></i>

                    <h3>
                        Live scheduled exams
                    </h3>

                    <p>
                        Join upcoming tests with clear schedule,
                        eligibility and access details.
                    </p>

                </article>


                <article class="premium-feature reveal">

                    <i class="fa-solid fa-bolt"></i>

                    <h3>
                        Instant results
                    </h3>

                    <p>
                        Receive score, percentage, grade and
                        pass/fail status immediately.
                    </p>

                </article>


                <article class="premium-feature reveal">

                    <i class="fa-solid fa-book-open"></i>

                    <h3>
                        Study materials
                    </h3>

                    <p>
                        Keep essential notes and preparation material
                        within easy reach.
                    </p>

                </article>


                <article class="premium-feature reveal">

                    <i class="fa-solid fa-chart-pie"></i>

                    <h3>
                        Performance tracking
                    </h3>

                    <p>
                        See your history, subject performance
                        and leaderboard progress.
                    </p>

                </article>


                <article class="premium-feature reveal">

                    <i class="fa-solid fa-shield-halved"></i>

                    <h3>
                        Secure experience
                    </h3>

                    <p>
                        Role-based access and carefully managed
                        exam attempts protect your work.
                    </p>

                </article>

            </div>

        </div>

    </section>


    <!-- =====================================================
         HOW IT WORKS
    ====================================================== -->
    <section
        class="landing-section process-section"
        id="how-it-works"
    >

        <div class="container process-wrap">

            <div class="process-copy reveal">

                <span class="landing-kicker">
                    HOW IT WORKS
                </span>

                <h2>
                    A clear path from registration to result.
                </h2>

                <p>
                    Get started in minutes, practise freely,
                    then unlock additional learning benefits whenever you need them.
                </p>

                <a
                    class="text-link"
                    href="auth/register.php"
                >
                    Create your student account
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

            </div>


            <div class="process-steps">

                <article class="process-step reveal">

                    <b>01</b>

                    <i class="fa-solid fa-user-plus"></i>

                    <h3>
                        Register
                    </h3>

                    <p>
                        Create your student account securely.
                    </p>

                </article>


                <article class="process-step reveal">

                    <b>02</b>

                    <i class="fa-solid fa-pen-to-square"></i>

                    <h3>
                        Practice
                    </h3>

                    <p>
                        Build confidence with free practice exams.
                    </p>

                </article>


                <article class="process-step reveal">

                    <b>03</b>

                    <i class="fa-solid fa-credit-card"></i>

                    <h3>
                        Unlock access
                    </h3>

                    <p>
                        Subscribe or pay only when required.
                    </p>

                </article>


                <article class="process-step reveal">

                    <b>04</b>

                    <i class="fa-solid fa-award"></i>

                    <h3>
                        Get results
                    </h3>

                    <p>
                        Review your instant result and progress.
                    </p>

                </article>

            </div>

        </div>

    </section>


    <!-- =====================================================
         DYNAMIC CATEGORIES
    ====================================================== -->
    <?php include 'includes/categories.php'; ?>


    <!-- =====================================================
         PRACTICE EXAMS
    ====================================================== -->
    <?php include 'includes/practice_exams.php'; ?>


    <!-- =====================================================
         LIVE EXAMS
    ====================================================== -->
    <?php include 'includes/live_exams.php'; ?>


    <!-- =====================================================
         MEMBERSHIP
    ====================================================== -->
    <?php include 'includes/plans.php'; ?>


    <!-- =====================================================
         STUDY MATERIALS
    ====================================================== -->
    <?php include 'includes/materials.php'; ?>


    <!-- =====================================================
         FAQ
    ====================================================== -->
    <?php include 'includes/faq.php'; ?>


    <!-- =====================================================
         CONTACT
    ====================================================== -->
   <?php include 'includes/contact.php'; ?>

</main>


<!-- =========================================================
     FOOTER
========================================================== -->
<footer class="premium-footer">

    <div class="container footer-grid">

        <div>

            <a
                class="footer-brand"
                href="index.php"
            >

                <img
                    src="assets/images/exam_logo.png"
                    alt="ExamSphere"
                >

                <span>

                    Exam<span>Sphere</span>

                    <small>
                        Smart • Secure • Instant
                    </small>

                </span>

            </a>

            <p>
                A modern online examination platform for focused learning and clear results.
            </p>

        </div>


        <div>

            <h4>
                Explore
            </h4>

            <a href="#practice-exams">
                Practice exams
            </a>

            <a href="#live-exams">
                Live exams
            </a>

            <a href="#categories">
                Categories
            </a>

            <a href="#plans">
                Subscription plans
            </a>

        </div>


        <div>

            <h4>
                Student
            </h4>

            <a href="auth/register.php">
                Create account
            </a>

            <a href="auth/login.php">
                Student login
            </a>

            <a href="#materials">
                Study materials
            </a>

        </div>


        <div>

            <h4>
                Contact
            </h4>

            <a href="mailto:support@examsphere.local">
                support@examsphere.local
            </a>

            <span>
                Demo payments only
            </span>

            <span>
                © <?= date('Y') ?> ExamSphere
            </span>

        </div>

    </div>

</footer>


<script src="https://cdn.jsdelivr.net/npm/@studio-freight/lenis@1.0.42/bundled/lenis.min.js"></script>

<script src="assets/js/main.js"></script>

</body>
</html>