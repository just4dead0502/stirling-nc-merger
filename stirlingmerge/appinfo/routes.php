<?php

return [
    'ocs' => [
        [
            'name' => 'Merge#merge',
            'url'  => '/api/merge',
            'verb' => 'POST',
        ],
    ],
    'routes' => [
        [
            'name' => 'Settings#save',
            'url'  => '/admin/save',
            'verb' => 'POST',
        ],
        [
            'name' => 'Settings#test',
            'url'  => '/admin/test',
            'verb' => 'GET',
        ],
        [
            'name' => 'PublicMerge#merge',
            'url'  => '/public/merge',
            'verb' => 'POST',
        ],
    ],
];
