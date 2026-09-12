<?php

declare(strict_types=1);

require_once '../config/mail.php';

header(
    'Content-Type: text/plain; charset=UTF-8'
);

echo "ExamSphere SMTP Diagnostic\n";
echo "==========================\n\n";

/*
|--------------------------------------------------------------------------
| Configuration test
|--------------------------------------------------------------------------
*/

echo "1. Mail configuration: ";

try {

    $configurationError =
        mail_configuration_error();

    if (
        $configurationError !== null
    ) {

        echo "FAILED\n";
        echo "Reason: " .
            $configurationError .
            "\n";

        exit;
    }

    echo "OK\n";

} catch (Throwable $e) {

    echo "FAILED\n";
    echo "Reason: " .
        $e->getMessage() .
        "\n";

    exit;
}

/*
|--------------------------------------------------------------------------
| PHPMailer test
|--------------------------------------------------------------------------
*/

echo "2. PHPMailer loading: ";

if (
    class_exists(
        \PHPMailer\PHPMailer\PHPMailer::class
    )
) {

    echo "OK\n";

} else {

    echo "FAILED\n";
    echo "PHPMailer class could not be loaded.\n";

    exit;
}

/*
|--------------------------------------------------------------------------
| SMTP connection/authentication test
|--------------------------------------------------------------------------
*/

echo "3. SMTP connection/authentication: ";

try {

    $mail =
        getMailer();

    $connected =
        $mail->smtpConnect();

    if (
        !$connected
    ) {

        echo "FAILED\n";

        echo "PHPMailer Error:\n";

        echo $mail->ErrorInfo .
            "\n";

        exit;
    }

    echo "OK\n";

    $mail->smtpClose();

    echo "\n";
    echo "SMTP is working correctly.\n";

} catch (Throwable $e) {

    echo "FAILED\n\n";

    echo "Exception:\n";
    echo $e->getMessage();
    echo "\n\n";

    if (
        isset($mail)
    ) {

        echo "PHPMailer Error:\n";
        echo $mail->ErrorInfo;
        echo "\n";

    }

}