<?php

declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/auth.php';

header(
    'Content-Type: application/json; charset=UTF-8'
);

if (
    current_user_id() <= 0 ||
    current_user_role() !== 'student'
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Access Denied',

        'message' =>
            'Please login again.'

    ]);

    exit;
}

if (
    !verify_csrf_token(
        $_POST['csrf_token'] ?? null
    )
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Security Check Failed',

        'message' =>
            'Your session security token is invalid. Please refresh the page.'

    ]);

    exit;
}

$studentId =
    current_user_id();

$fullName =
    trim(
        (string)(
            $_POST['full_name'] ?? ''
        )
    );

$mobile =
    trim(
        (string)(
            $_POST['mobile'] ?? ''
        )
    );

$gender =
    trim(
        (string)(
            $_POST['gender'] ?? ''
        )
    );

$dob =
    trim(
        (string)(
            $_POST['dob'] ?? ''
        )
    );

$address =
    trim(
        (string)(
            $_POST['address'] ?? ''
        )
    );

$city =
    trim(
        (string)(
            $_POST['city'] ?? ''
        )
    );

$state =
    trim(
        (string)(
            $_POST['state'] ?? ''
        )
    );

$pincode =
    trim(
        (string)(
            $_POST['pincode'] ?? ''
        )
    );

if (
    $fullName === '' ||
    mb_strlen($fullName) > 100 ||
    !preg_match(
        "/^[\p{L}][\p{L}\s.'-]*$/u",
        $fullName
    )
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Invalid Name',

        'message' =>
            'Enter a valid full name.'

    ]);

    exit;
}

if (
    !preg_match(
        '/^[0-9]{10,15}$/',
        $mobile
    )
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Invalid Mobile',

        'message' =>
            'Enter a valid 10 to 15 digit mobile number.'

    ]);

    exit;
}

if (
    $gender !== '' &&
    !in_array(
        $gender,
        [
            'Male',
            'Female',
            'Other'
        ],
        true
    )
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Invalid Gender',

        'message' =>
            'Please select a valid gender.'

    ]);

    exit;
}

if (
    $dob !== ''
) {

    $date =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $dob
        );

    $errors =
        DateTimeImmutable::getLastErrors();

    $hasErrors =
        is_array($errors) &&
        (
            $errors['warning_count'] > 0 ||
            $errors['error_count'] > 0
        );

    if (
        !$date ||
        $hasErrors ||
        $date->format('Y-m-d') !== $dob ||
        $date >
            new DateTimeImmutable('today')
    ) {

        echo json_encode([

            'status' =>
                'error',

            'title' =>
                'Invalid Date',

            'message' =>
                'Enter a valid date of birth.'

        ]);

        exit;
    }
}

if (
    $address !== '' &&
    mb_strlen($address) > 2000
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Invalid Address',

        'message' =>
            'Address is too long.'

    ]);

    exit;
}

if (
    $city !== '' &&
    (
        mb_strlen($city) < 2 ||
        mb_strlen($city) > 80
    )
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Invalid City',

        'message' =>
            'City must contain between 2 and 80 characters.'

    ]);

    exit;
}

if (
    $state !== '' &&
    (
        mb_strlen($state) < 2 ||
        mb_strlen($state) > 80
    )
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Invalid State',

        'message' =>
            'State must contain between 2 and 80 characters.'

    ]);

    exit;
}

if (
    $pincode !== '' &&
    !preg_match(
        '/^[0-9]{4,10}$/',
        $pincode
    )
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Invalid Pincode',

        'message' =>
            'Enter a valid pincode.'

    ]);

    exit;
}

try {

    $check =
        $conn->prepare(
            'SELECT id
             FROM students
             WHERE id = ?
             LIMIT 1'
        );

    $check->execute([
        $studentId
    ]);

    if (
        !$check->fetchColumn()
    ) {

        echo json_encode([

            'status' =>
                'error',

            'title' =>
                'Student Not Found',

            'message' =>
                'Unable to find your account.'

        ]);

        exit;
    }

    $mobileCheck =
        $conn->prepare(
            'SELECT id
             FROM students
             WHERE mobile = ?
             AND id != ?
             LIMIT 1'
        );

    $mobileCheck->execute([

        $mobile,

        $studentId

    ]);

    if (
        $mobileCheck->fetchColumn()
    ) {

        echo json_encode([

            'status' =>
                'error',

            'title' =>
                'Duplicate Mobile',

            'message' =>
                'This mobile number is already registered.'

        ]);

        exit;
    }

    $update =
        $conn->prepare(
            'UPDATE students
             SET
                full_name = ?,
                mobile = ?,
                gender = ?,
                dob = ?,
                address = ?,
                city = ?,
                state = ?,
                pincode = ?
             WHERE id = ?'
        );

    $update->execute([

        $fullName,

        $mobile,

        $gender !== ''
            ? $gender
            : null,

        $dob !== ''
            ? $dob
            : null,

        $address !== ''
            ? $address
            : null,

        $city !== ''
            ? $city
            : null,

        $state !== ''
            ? $state
            : null,

        $pincode !== ''
            ? $pincode
            : null,

        $studentId

    ]);

    $_SESSION['user_name'] =
        $fullName;

    echo json_encode([

        'status' =>
            'success',

        'title' =>
            'Profile Updated',

        'message' =>
            'Your profile has been updated successfully.'

    ]);

} catch (
    Throwable $e
) {

    error_log(
        'Student profile update failed: ' .
        $e->getMessage()
    );

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Update Failed',

        'message' =>
            'Unable to update your profile right now.'

    ]);

}