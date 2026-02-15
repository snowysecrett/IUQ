<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Media Disk
    |--------------------------------------------------------------------------
    |
    | Dedicated disk used for uploaded logos/icons.
    | Keep this as "public" unless you intentionally move media to another
    | filesystem (for example, "s3").
    |
    */
    'disk' => env('MEDIA_DISK', 'public'),
];
