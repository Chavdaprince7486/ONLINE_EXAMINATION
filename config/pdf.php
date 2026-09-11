<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| ExamSphere PDF configuration
|--------------------------------------------------------------------------
|
| This secret is used only to create/verify short-lived signed
| URLs for the Chromium PDF renderer.
|
| IMPORTANT:
| Change this value to your own long random secret.
|
*/

$pdfAccessSecret =
    trim(
        (string) (
            getenv(
                'EXAMSPHERE_PDF_ACCESS_SECRET'
            )
            ?: ''
        )
    );


if (
    $pdfAccessSecret === ''
) {

    http_response_code(500);

    exit(
        'PDF signing is not configured.'
    );
}


if (
    !defined('PDF_ACCESS_SECRET')
) {

    define(
        'PDF_ACCESS_SECRET',
        $pdfAccessSecret
    );
}



/*
|--------------------------------------------------------------------------
| Base application URL
|--------------------------------------------------------------------------
*/

$pdfAppUrl =
    trim(
        (string) (
            getenv(
                'EXAMSPHERE_PDF_APP_URL'
            )
            ?: ''
        )
    );


if (
    $pdfAppUrl === ''
) {

    $pdfAppUrl =
        'http://localhost/ONLINE_EXAMINATION';
}


if (
    !defined('PDF_APP_URL')
) {

    define(
        'PDF_APP_URL',
        $pdfAppUrl
    );
}