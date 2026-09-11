<?php

declare(strict_types=1);

require_once "../config/config.php";
require_once "../config/session.php";


/*
|--------------------------------------------------------------------------
| POST ONLY
|--------------------------------------------------------------------------
*/

if (
    strtoupper(
        (string) (
            $_SERVER["REQUEST_METHOD"]
            ?? "GET"
        )
    ) !== "POST"
) {

    header(
        "Location: login.php"
    );

    exit();
}


/*
|--------------------------------------------------------------------------
| FORM DATA
|--------------------------------------------------------------------------
*/

$role =
    strtolower(
        trim(
            (string) (
                $_POST["role"]
                ?? ""
            )
        )
    );

$email =
    trim(
        (string) (
            $_POST["email"]
            ?? ""
        )
    );

$password =
    (string) (
        $_POST["password"]
        ?? ""
    );


/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $role === "" ||
    $email === "" ||
    $password === ""
) {

    $_SESSION["error"] =
        "Please fill all fields.";

    header(
        "Location: login.php"
    );

    exit();
}


if (
    !in_array(
        $role,
        [
            "student",
            "teacher",
            "admin"
        ],
        true
    )
) {

    $_SESSION["error"] =
        "Invalid Login Role.";

    header(
        "Location: login.php"
    );

    exit();
}


if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {

    $_SESSION["error"] =
        "Invalid Email Address.";

    header(
        "Location: login.php"
    );

    exit();
}


/*
|--------------------------------------------------------------------------
| TABLE / DASHBOARD
|--------------------------------------------------------------------------
*/

$tableMap = [

    "student" => [
        "table" =>
            "students",

        "dashboard" =>
            "../student/dashboard.php"
    ],

    "teacher" => [
        "table" =>
            "teachers",

        "dashboard" =>
            "../teacher/dashboard.php"
    ],

    "admin" => [
        "table" =>
            "admins",

        "dashboard" =>
            "../admin/dashboard.php"
    ]

];


$table =
    $tableMap[$role]["table"];

$dashboard =
    $tableMap[$role]["dashboard"];


/*
|--------------------------------------------------------------------------
| LOAD USER
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare(
        "SELECT *
         FROM {$table}
         WHERE email = ?
         LIMIT 1"
    );

$stmt->execute([
    $email
]);

$user =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (
    !$user
) {

    $_SESSION["error"] =
        "Email not found.";

    header(
        "Location: login.php"
    );

    exit();
}


/*
|--------------------------------------------------------------------------
| ACCOUNT STATUS
|--------------------------------------------------------------------------
*/

if (
    !isset(
        $user["status"]
    )
    ||
    strcasecmp(
        trim(
            (string) $user["status"]
        ),
        "Active"
    ) !== 0
) {

    $_SESSION["error"] =
        "Your account is inactive.";

    header(
        "Location: login.php"
    );

    exit();
}


/*
|--------------------------------------------------------------------------
| PASSWORD
|--------------------------------------------------------------------------
*/

$userPassword =
    (string) (
        $user["password"]
        ?? ""
    );

$passwordValid = false;


/*
|--------------------------------------------------------------------------
| Normal password_hash() verification
|--------------------------------------------------------------------------
*/

if (
    $userPassword !== ""
) {

    $passwordValid =
        password_verify(
            $password,
            $userPassword
        );


    /*
    |--------------------------------------------------------------------------
    | Backward-compatible plain-password check
    |--------------------------------------------------------------------------
    |
    | Only used when the stored value is not a password_hash format.
    | This lets an existing local installation keep working while accounts
    | are migrated to password_hash().
    |
    */

    if (
        !$passwordValid &&
        !str_starts_with(
            $userPassword,
            '$2y$'
        ) &&
        !str_starts_with(
            $userPassword,
            '$2a$'
        ) &&
        !str_starts_with(
            $userPassword,
            '$2b$'
        )
    ) {

        $passwordValid =
            hash_equals(
                $userPassword,
                $password
            );
    }
}


if (
    !$passwordValid
) {

    $_SESSION["error"] =
        "Incorrect Password.";

    header(
        "Location: login.php"
    );

    exit();
}


/*
|--------------------------------------------------------------------------
| REGULAR LAST LOGIN UPDATE
|--------------------------------------------------------------------------
*/

if (
    array_key_exists(
        "last_login",
        $user
    )
) {

    $update =
        $conn->prepare(
            "UPDATE {$table}
             SET last_login = NOW()
             WHERE id = ?"
        );

    $update->execute([
        $user["id"]
    ]);
}


/*
|--------------------------------------------------------------------------
| CREATE SECURE SESSION
|--------------------------------------------------------------------------
*/

session_regenerate_id(
    true
);


$_SESSION[
    "user_id"
] =
    (int) $user["id"];

$_SESSION[
    "user_name"
] =
    (string) (
        $user["full_name"]
        ?? "User"
    );

$_SESSION[
    "user_email"
] =
    (string) (
        $user["email"]
        ?? $email
    );

$_SESSION[
    "user_role"
] =
    $role;


/*
|--------------------------------------------------------------------------
| Remove legacy role/session values
|--------------------------------------------------------------------------
*/

unset(
    $_SESSION["role"],
    $_SESSION["student_id"]
);


/*
|--------------------------------------------------------------------------
| REDIRECT
|--------------------------------------------------------------------------
*/

header(
    "Location: " .
    $dashboard
);

exit();

?>
