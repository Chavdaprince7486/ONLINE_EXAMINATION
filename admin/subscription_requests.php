<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';


if (
    empty(
        $_SESSION['user_id']
    )
    ||
    (
        $_SESSION['user_role']
        ??
        ''
    )
    !==
    'admin'
) {

    header(
        'Location: ../auth/login.php'
    );

    exit;
}


$page_title =
    'Payment Requests | ExamSphere';


$requests = [];

$pendingCount = 0;

$approvedCount = 0;

$rejectedCount = 0;


function sr_e(
    mixed $value
): string {

    return htmlspecialchars(
        (string)(
            $value
            ??
            ''
        ),
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    );

}


function sr_date(
    mixed $value,
    string $format = 'd M Y'
): string {

    if (!$value) {
        return '—';
    }


    try {

        return (
            new DateTimeImmutable(
                (string)$value
            )
        )->format(
            $format
        );

    } catch (
        Throwable
    ) {

        return '—';

    }

}


function sr_status(
    string $status
): string {

    return match (
        $status
    ) {

        'Approved' =>
            'approved',

        'Rejected' =>
            'rejected',

        'Cancelled' =>
            'cancelled',

        default =>
            'pending'

    };

}


try {


    /*
    |--------------------------------------------------------------------------
    | REQUESTS
    |--------------------------------------------------------------------------
    */

    $requestStatement =
        $conn->query(
            "
            SELECT

                r.id,
                r.student_id,
                r.plan_id,
                r.amount,
                r.request_type,
                r.payment_screenshot,
                r.status,
                r.admin_note,
                r.submitted_at,
                r.reviewed_at,

                st.full_name,
                st.email,

                p.name AS plan_name,
                p.duration_months

            FROM subscription_requests r

            INNER JOIN students st
                ON st.id = r.student_id

            INNER JOIN subscription_plans p
                ON p.id = r.plan_id

            ORDER BY

                FIELD(
                    r.status,
                    'Pending',
                    'Approved',
                    'Rejected',
                    'Cancelled'
                ),

                r.submitted_at DESC,

                r.id DESC
            "
        );


    $requests =
        $requestStatement
            ->fetchAll(
                PDO::FETCH_ASSOC
            );


    /*
    |--------------------------------------------------------------------------
    | COUNTS
    |--------------------------------------------------------------------------
    */

    $pendingCount =
        (int)(
            $conn
                ->query(
                    "
                    SELECT
                        COUNT(*)

                    FROM subscription_requests

                    WHERE
                        status = 'Pending'
                    "
                )
                ->fetchColumn()
        );


    $approvedCount =
        (int)(
            $conn
                ->query(
                    "
                    SELECT
                        COUNT(*)

                    FROM subscription_requests

                    WHERE
                        status = 'Approved'
                    "
                )
                ->fetchColumn()
        );


    $rejectedCount =
        (int)(
            $conn
                ->query(
                    "
                    SELECT
                        COUNT(*)

                    FROM subscription_requests

                    WHERE
                        status = 'Rejected'
                    "
                )
                ->fetchColumn()
        );


} catch (
    Throwable $exception
) {

    error_log(
        'Admin subscription requests failed: ' .
        $exception->getMessage()
    );
}


require_once 'includes/header.php';

?>


<style>

.sr-page{

    padding:
        22px !important;

    background:
        linear-gradient(
            135deg,
            #F5F5DC,
            #FBFAF4
        );

    min-height:
        calc(
            100vh -
            70px
        );

}


.sr-head{

    display:flex;

    align-items:end;

    justify-content:space-between;

    gap:
        15px;

    margin-bottom:
        14px;

}


.sr-kicker{

    color:
        #556B2F;

    font-size:
        8px;

    font-weight:
        900;

    letter-spacing:
        1.15px;

}


.sr-head h1{

    margin:
        6px 0 4px;

    color:
        #3E2723;

    font-size:
        31px;

    font-weight:
        900;

    letter-spacing:
        -.035em;

}


.sr-head p{

    margin:0;

    color:
        #766E69;

    font-size:
        9px;

}


.sr-back{

    min-height:
        40px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:7px;

    padding:
        0 12px;

    border-radius:
        10px;

    color:#fff;

    background:
        #5D4037;

    font-size:
        7px;

    font-weight:
        900;

    text-decoration:none;

}


.sr-back:hover{

    color:#fff;

    background:
        #3E2723;

}


/* STATS */

.sr-stats{

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
        9px;

    margin-bottom:
        11px;

}


.sr-stat{

    padding:
        13px;

    border:
        1px solid
        #E3DDD2;

    border-radius:
        13px;

    background:
        #fff;

}


.sr-stat small{

    display:block;

    color:
        #8C837B;

    font-size:
        6px;

    text-transform:
        uppercase;

    font-weight:
        800;

}


.sr-stat strong{

    display:block;

    margin-top:
        3px;

    color:
        #3E2723;

    font-size:
        19px;

    font-weight:
        900;

}


/* TABLE */

.sr-table-card{

    overflow:hidden;

    border:
        1px solid
        #E3DDD2;

    border-radius:
        17px;

    background:
        #fff;

    box-shadow:
        0 14px 36px
        rgba(
            62,
            39,
            35,
            .05
        );

}


.sr-table-wrap{

    overflow:auto;

}


.sr-table{

    width:100%;

    min-width:
        1000px;

    border-collapse:
        collapse;

}


.sr-table th{

    padding:
        10px;

    color:
        #7F756D;

    background:
        #FAF7F1;

    border-bottom:
        1px solid
        #E3DDD2;

    font-size:
        6px;

    font-weight:
        900;

    letter-spacing:
        .6px;

    text-align:left;

    text-transform:
        uppercase;

}


.sr-table td{

    padding:
        11px 10px;

    color:
        #766E69;

    border-bottom:
        1px solid
        #EEE8E0;

    font-size:
        7px;

    vertical-align:
        middle;

}


.sr-table tr:last-child td{

    border-bottom:
        0;

}


.sr-student strong{

    display:block;

    color:
        #3E2723;

    font-size:
        8px;

    font-weight:
        900;

}


.sr-student small{

    display:block;

    margin-top:
        2px;

    color:
        #968C84;

    font-size:
        6px;

}


.sr-badge{

    display:inline-flex;

    align-items:center;

    min-height:
        22px;

    padding:
        0 7px;

    border-radius:
        999px;

    font-size:
        6px;

    font-weight:
        900;

}


.sr-badge.pending{

    color:
        #8A651E;

    background:
        #FFF3D8;

}


.sr-badge.approved{

    color:
        #556B2F;

    background:
        #ECF2E2;

}


.sr-badge.rejected{

    color:
        #A84538;

    background:
        #FBEAE7;

}


.sr-proof{

    display:inline-flex;

    align-items:center;

    gap:5px;

    color:
        #556B2F;

    font-size:
        7px;

    font-weight:
        900;

    text-decoration:none;

}


.sr-proof:hover{

    color:
        #3E2723;

}


.sr-actions{

    display:flex;

    gap:
        5px;

    flex-wrap:
        wrap;

}


.sr-btn{

    min-height:
        27px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:
        5px;

    padding:
        0 8px;

    border:
        0;

    border-radius:
        7px;

    font-size:
        6px;

    font-weight:
        900;

    cursor:pointer;

}


.sr-approve{

    color:#fff;

    background:
        #556B2F;

}


.sr-approve:hover{

    background:
        #465923;

}


.sr-reject{

    color:
        #A84538;

    background:
        #FBEAE7;

}


.sr-reject:hover{

    background:
        #F4DCD8;

}


.sr-empty{

    padding:
        35px;

    color:
        #766E69;

    font-size:
        8px;

    text-align:center;

}


@media(
    max-width:760px
){

    .sr-head{

        align-items:
            flex-start;

        flex-direction:
            column;

    }


    .sr-back{

        width:100%;

    }


    .sr-stats{

        grid-template-columns:
            1fr;

    }

}

</style>


<div
    class="
        dashboard-wrapper
    "
>


<?php include
    'includes/sidebar.php';
?>


<div
    class="
        main-content
    "
>


<?php include
    'includes/navbar.php';
?>


<main
    class="
        dashboard-content
        sr-page
    "
>


<?php if (
    !empty(
        $_SESSION['success']
    )
): ?>


<div
    style="
        margin-bottom:12px;
        padding:11px 13px;
        border:1px solid #DDE8CE;
        border-radius:11px;
        color:#556B2F;
        background:#ECF2E2;
        font-size:7.5px;
        font-weight:800;
    "
>

<?= sr_e(
    $_SESSION[
        'success'
    ]
) ?>


</div>


<?php

unset(
    $_SESSION[
        'success'
    ]
);

?>


<?php endif; ?>


<?php if (
    !empty(
        $_SESSION['error']
    )
): ?>


<div
    style="
        margin-bottom:12px;
        padding:11px 13px;
        border:1px solid #EDD0CB;
        border-radius:11px;
        color:#A84538;
        background:#FBEAE7;
        font-size:7.5px;
        font-weight:800;
    "
>

<?= sr_e(
    $_SESSION[
        'error'
    ]
) ?>


</div>


<?php

unset(
    $_SESSION[
        'error'
    ]
);

?>


<?php endif; ?>


<div
    class="
        sr-head
    "
>


<div>


<div
    class="
        sr-kicker
    "
>

<i
    class="
        fa-solid
        fa-file-invoice
    "
></i>

SUBSCRIPTION REQUESTS

</div>


<h1>
    Payment Approvals
</h1>


<p>
    Review payment proofs and approve eligible memberships.
</p>


</div>


<a
    href="subscriptions.php"
    class="
        sr-back
    "
>

<i
    class="
        fa-solid
        fa-arrow-left
    "
></i>

Subscription Management

</a>


</div>


<div
    class="
        sr-stats
    "
>


<div class="sr-stat">

<small>
    Pending approval
</small>

<strong>
    <?= $pendingCount ?>
</strong>

</div>


<div class="sr-stat">

<small>
    Approved requests
</small>

<strong>
    <?= $approvedCount ?>
</strong>

</div>


<div class="sr-stat">

<small>
    Rejected requests
</small>

<strong>
    <?= $rejectedCount ?>
</strong>

</div>


</div>


<div
    class="
        sr-table-card
    "
>


<div
    class="
        sr-table-wrap
    "
>


<table
    class="
        sr-table
    "
>


<thead>

<tr>

<th>
    Student
</th>

<th>
    Plan
</th>

<th>
    Amount
</th>

<th>
    Type
</th>

<th>
    Payment Proof
</th>

<th>
    Status
</th>

<th>
    Submitted
</th>

<th>
    Action
</th>

</tr>

</thead>


<tbody>


<?php if (
    empty(
        $requests
    )
): ?>


<tr>

<td
    colspan="8"
    class="sr-empty"
>

No subscription activation requests found.

</td>

</tr>


<?php else: ?>


<?php foreach (
    $requests
    as $request
): ?>


<tr>


<td
    class="
        sr-student
    "
>

<strong>

<?= sr_e(
    $request[
        'full_name'
    ]
) ?>

</strong>


<small>

<?= sr_e(
    $request[
        'email'
    ]
) ?>

</small>

</td>


<td>

<strong
    style="
        color:#3E2723;
        font-size:8px;
        font-weight:900;
    "
>

<?= sr_e(
    $request[
        'plan_name'
    ]
) ?>

</strong>


<br>


<small
    style="
        color:#968C84;
        font-size:6px;
    "
>

<?= (int)$request[
    'duration_months'
] ?>

month(s)

</small>

</td>


<td>

<?= (
    (float)$request[
        'amount'
    ] <= 0
)

    ? 'FREE'

    :

    '₹' .
    number_format(
        (float)$request[
            'amount'
        ],
        2
    )

?>

</td>


<td>

<?= sr_e(
    $request[
        'request_type'
    ]
) ?>

</td>


<td>


<?php if (
    !empty(
        $request[
            'payment_screenshot'
        ]
    )
): ?>


<a
    class="
        sr-proof
    "
    target="_blank"
    rel="noopener"
    href="../<?= sr_e(
        $request[
            'payment_screenshot'
        ]
    ) ?>"
>

<i
    class="
        fa-solid
        fa-image
    "
></i>

View proof

</a>


<?php else: ?>


—

<?php endif; ?>


</td>


<td>

<span
    class="
        sr-badge
        <?= sr_status(
            (string)$request[
                'status'
            ]
        ) ?>
    "
>

<?= sr_e(
    $request[
        'status'
    ]
) ?>

</span>

</td>


<td>

<?= sr_date(
    $request[
        'submitted_at'
    ],
    'd M Y, h:i A'
) ?>

</td>


<td>


<?php if (
    $request[
        'status'
    ]
    ===
    'Pending'
): ?>


<div
    class="
        sr-actions
    "
>


<form
    method="POST"
    action="
        subscription-requests/approve.php
    "
>


<input
    type="hidden"
    name="csrf_token"
    value="<?= sr_e(
        csrf_token()
    ) ?>"
>


<input
    type="hidden"
    name="id"
    value="<?= (int)$request[
        'id'
    ] ?>"
>


<button
    class="
        sr-btn
        sr-approve
    "
    type="submit"
>

<i
    class="
        fa-solid
        fa-check
    "
></i>

Approve

</button>


</form>


<form
    method="POST"
    action="
        subscription-requests/reject.php
    "
>


<input
    type="hidden"
    name="csrf_token"
    value="<?= sr_e(
        csrf_token()
    ) ?>"
>


<input
    type="hidden"
    name="id"
    value="<?= (int)$request[
        'id'
    ] ?>"
>


<button
    class="
        sr-btn
        sr-reject
    "
    type="submit"
>

<i
    class="
        fa-solid
        fa-xmark
    "
></i>

Reject

</button>


</form>


</div>


<?php else: ?>


—

<?php endif; ?>


</td>


</tr>


<?php endforeach; ?>


<?php endif; ?>


</tbody>


</table>


</div>


</div>


</main>


</div>


</div>