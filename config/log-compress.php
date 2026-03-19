<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Compression Password
    |--------------------------------------------------------------------------
    |
    | When set, the tar.gz archive will be encrypted using AES-256-CBC via
    | OpenSSL. Set to null to create unencrypted archives.
    |
    */
    'password' => env('LOG_COMPRESS_PASSWORD', null),

    /*
    |--------------------------------------------------------------------------
    | Delete After Compress
    |--------------------------------------------------------------------------
    |
    | Whether to delete the original log file after successful compression.
    |
    */
    'delete_after_compress' => true,

    /*
    |--------------------------------------------------------------------------
    | Older Than Days
    |--------------------------------------------------------------------------
    |
    | Only compress log files older than this many days. Set to 1 to skip
    | today's log file (recommended, as it may still be written to).
    |
    */
    'older_than_days' => 1,

];
