<?php
return [
    'extends' => 'bootprint3',
    'css' => [
        'vendor/modern-normalize.css',
        'vu-common-base.css',
        'vu-common-wide.css:(min-width: 768px)',
        'fonts.css',
        'diglib-2023.css',
        'shared-nav-style.css',
        // .prose class for optimal reading
        'vendor/tailwind-prose.spacing.base.min.css',
    ],
    'js' => [
        'shared-nav-new.js'
    ],
    'helpers' => [
        'factories' => [
            'DigLib\View\Helper\VuDL' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Root\RecordDataFormatter' => 'DigLib\View\Helper\RecordDataFormatterFactory',
        ],
        'aliases' => [
            'vudl' => 'DigLib\View\Helper\VuDL',
        ],
    ],
];
