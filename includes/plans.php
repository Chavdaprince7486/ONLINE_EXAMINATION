<?php

$homepagePlans = [];

try {

    /*
     * Load only active subscription plans.
     *
     * The database already provides:
     *
     * name
     * duration_months
     * price
     * description
     * benefits
     * status
     */
    $planStatement = $conn->query("
        SELECT
            id,
            name,
            duration_months,
            price,
            description,
            benefits,
            status
        FROM subscription_plans
        WHERE status = 'Active'
        ORDER BY duration_months ASC, id ASC
    ");

    $homepagePlans =
        $planStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $exception) {

    error_log(
        'ExamSphere homepage subscription plans failed: ' .
        $exception->getMessage()
    );

    $homepagePlans = [];
}


/*
 * Find the longest active plan.
 *
 * Used only for the visual "Best Value" badge.
 * We do NOT assume that 6 months is always best.
 */
$bestPlanId = null;

if (!empty($homepagePlans)) {

    $bestPlan = null;

    foreach ($homepagePlans as $plan) {

        $duration =
            (int) ($plan['duration_months'] ?? 0);

        $price =
            (float) ($plan['price'] ?? 0);


        /*
         * Prefer longer duration.
         * If duration is equal, prefer the lower
         * price per month.
         */
        if ($bestPlan === null) {

            $bestPlan = $plan;
            continue;
        }


        $bestDuration =
            (int) ($bestPlan['duration_months'] ?? 0);

        $bestPrice =
            (float) ($bestPlan['price'] ?? 0);


        $currentMonthlyPrice =
            $duration > 0
                ? $price / $duration
                : PHP_FLOAT_MAX;

        $bestMonthlyPrice =
            $bestDuration > 0
                ? $bestPrice / $bestDuration
                : PHP_FLOAT_MAX;


        if (
            $duration > $bestDuration ||
            (
                $duration === $bestDuration &&
                $currentMonthlyPrice < $bestMonthlyPrice
            )
        ) {

            $bestPlan = $plan;
        }
    }


    if ($bestPlan !== null) {

        $bestPlanId =
            (int) $bestPlan['id'];
    }
}

?>


<section
    class="landing-section dynamic-plans-section"
    id="plans"
>

    <div class="container">

        <div class="dynamic-plans-heading reveal">

            <div>

                <span class="dynamic-plans-kicker">

                    <i class="fa-solid fa-gem"></i>

                    MEMBERSHIP PLANS

                </span>


                <h2>
                    Choose the plan that
                    <em>fits your preparation.</em>
                </h2>


                <p>
                    Practice exams remain free. An active membership
                    unlocks subscription-only study materials and eligible
                    live examinations.
                </p>

            </div>


            <div class="dynamic-plans-note">

                <i class="fa-solid fa-shield-halved"></i>

                <span>
                    Secure demo payment flow
                </span>

            </div>

        </div>


        <?php if (!empty($homepagePlans)): ?>

            <div class="dynamic-plans-grid">

                <?php foreach (
                    $homepagePlans
                    as $index => $plan
                ): ?>

                    <?php

                    $planId =
                        (int) $plan['id'];

                    $name =
                        trim(
                            (string) $plan['name']
                        );

                    $duration =
                        (int) (
                            $plan['duration_months'] ?? 0
                        );

                    $price =
                        (float) (
                            $plan['price'] ?? 0
                        );

                    $description =
                        trim(
                            (string) (
                                $plan['description'] ?? ''
                            )
                        );

                    $benefits =
                        trim(
                            (string) (
                                $plan['benefits'] ?? ''
                            )
                        );


                    if ($description === '') {

                        $description =
                            'Flexible ExamSphere membership access.';
                    }


                    if ($benefits === '') {

                        $benefits =
                            'Access subscription-enabled resources.';
                    }


                    /*
                     * Benefits are stored in TEXT.
                     *
                     * Existing database seed uses semicolon-separated
                     * benefits, so support that format.
                     */
                    $benefitItems =
                        preg_split(
                            '/[;\r\n]+/',
                            $benefits
                        );


                    $benefitItems =
                        array_values(
                            array_filter(
                                array_map(
                                    'trim',
                                    $benefitItems
                                ),
                                static function ($value) {
                                    return $value !== '';
                                }
                            )
                        );


                    /*
                     * Keep the homepage compact.
                     */
                    $benefitItems =
                        array_slice(
                            $benefitItems,
                            0,
                            4
                        );


                    $isBestValue =
                        $bestPlanId === $planId;


                    $pricePerMonth =
                        $duration > 0
                            ? $price / $duration
                            : $price;

                    ?>

                    <article
                        class="dynamic-plan-card <?= $isBestValue ? 'best-value' : '' ?> reveal"
                    >

                        <?php if ($isBestValue): ?>

                            <span class="dynamic-plan-badge">

                                <i class="fa-solid fa-crown"></i>

                                BEST VALUE

                            </span>

                        <?php endif; ?>


                        <div class="dynamic-plan-number">

                            <?= str_pad(
                                (string) ($index + 1),
                                2,
                                '0',
                                STR_PAD_LEFT
                            ) ?>

                        </div>


                        <div class="dynamic-plan-icon">

                            <?php if ($duration >= 6): ?>

                                <i class="fa-solid fa-crown"></i>

                            <?php elseif ($duration >= 3): ?>

                                <i class="fa-solid fa-bolt"></i>

                            <?php else: ?>

                                <i class="fa-solid fa-rocket"></i>

                            <?php endif; ?>

                        </div>


                        <div class="dynamic-plan-content">

                            <h3>

                                <?= htmlspecialchars(
                                    $name,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </h3>


                            <div class="dynamic-plan-price">

                                <span>
                                    ₹
                                </span>

                                <strong>

                                    <?= number_format(
                                        $price,
                                        0,
                                        '.',
                                        ','
                                    ) ?>

                                </strong>

                                <?php if ($duration > 0): ?>

                                    <small>
                                        / <?= $duration ?>
                                        <?= $duration === 1
                                            ? 'month'
                                            : 'months'
                                        ?>
                                    </small>

                                <?php endif; ?>

                            </div>


                            <p class="dynamic-plan-description">

                                <?= htmlspecialchars(
                                    $description,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </p>


                            <div class="dynamic-plan-rate">

                                <i class="fa-solid fa-calculator"></i>

                                Approx.
                                ₹<?= number_format(
                                    $pricePerMonth,
                                    2
                                ) ?>

                                / month

                            </div>


                            <div class="dynamic-plan-benefits">

                                <?php foreach (
                                    $benefitItems
                                    as $benefit
                                ): ?>

                                    <span>

                                        <i
                                            class="fa-solid fa-circle-check"
                                        ></i>

                                        <?= htmlspecialchars(
                                            $benefit,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </span>

                                <?php endforeach; ?>

                            </div>

                        </div>


                        <a
                            href="auth/login.php?redirect=plans"
                            class="dynamic-plan-action"
                        >

                            View membership

                            <i
                                class="fa-solid fa-arrow-right"
                            ></i>

                        </a>

                    </article>

                <?php endforeach; ?>

            </div>


            <div class="dynamic-plans-footer reveal">

                <div>

                    <span>

                        <i class="fa-solid fa-circle-info"></i>

                        MEMBERSHIP INFORMATION

                    </span>


                    <strong>
                        Plans and pricing are controlled from the Admin panel.
                    </strong>


                    <p>
                        Practice exams remain available without membership.
                        Subscription access applies only where the platform requires it.
                    </p>

                </div>


                <a
                    href="auth/register.php"
                    class="landing-btn primary"
                >

                    Get started

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>


        <?php else: ?>

            <div class="dynamic-plans-empty reveal">

                <div class="dynamic-plans-empty-icon">

                    <i class="fa-solid fa-gem"></i>

                </div>


                <h3>
                    Membership plans are currently unavailable.
                </h3>


                <p>
                    Active subscription plans will appear here automatically
                    after they are published by the administrator.
                </p>

            </div>

        <?php endif; ?>

    </div>

</section>