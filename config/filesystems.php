<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        // Craft 5 "Images" filesystem (project config fs.images), used by the
        // Images asset volume. Uploads live in public/images (was web/images).
        'images' => [
            'driver' => 'local',
            'root' => public_path('images'),
            'url' => rtrim((string) env('PRIMARY_SITE_URL'), '/').'/images',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
            // Keep transforms out of the uploads root, as Craft 5's
            // transformFs: imageTransforms did. Craft 6 nests them under the
            // volume UID: public/images/transforms/<volumeUid>/_WxH_.../file.
            'asset_transformer' => [
                'driver' => 'craft',
                'output_disk' => 'imageTransforms',
                'has_urls' => true,
            ],
        ],

        // Craft 5 "Image Transforms" filesystem (fs.imageTransforms), the
        // Images volume's transform filesystem.
        'imageTransforms' => [
            'driver' => 'local',
            'root' => public_path('images/transforms'),
            'url' => rtrim((string) env('PRIMARY_SITE_URL'), '/').'/images/transforms',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
