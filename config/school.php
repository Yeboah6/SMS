<?php

return [
    // Temporary password given to every new student account. Students are forced
    // to replace it the first time they sign in. Set STUDENT_DEFAULT_PASSWORD in .env.
    'student_default_password' => env('STUDENT_DEFAULT_PASSWORD'),
];
