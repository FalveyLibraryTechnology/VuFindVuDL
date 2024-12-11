<?php

return [
  'vufind' =>
  [
    'plugin_managers' =>
    [
      'search_backend' =>
      [
        'factories' =>
        [
          'Solr' => 'DigLib\\Search\\Factory\\SolrDefaultBackendFactory',
        ],
      ],
      'search_options' =>
      [
        'aliases' =>
        [
          'DigLib\\Search\\SolrCollection\\Options' => 'VuFind\\Search\\SolrCollection\\Options',
        ],
      ],
      'search_params' =>
      [
        'factories' =>
        [
          'DigLib\\Search\\SolrCollection\\Params' => 'DigLib\\Search\\SolrCollection\\ParamsFactory',
        ],
        'aliases' =>
        [
          'VuFind\\Search\\SolrCollection\\Params' => 'DigLib\\Search\\SolrCollection\\Params',
        ],
      ],
      'recorddriver' =>
      [
        'factories' =>
        [
          'DigLib\\RecordDriver\\SolrVudl' => 'DigLib\\RecordDriver\\SolrVudlFactory',
        ],
        'aliases' =>
        [
          'solrvudl' => 'DigLib\\RecordDriver\\SolrVudl',
        ],
      ],
      'recommend' =>
      [
        'factories' =>
        [
          'DigLib\\Recommend\\SideFacets' => 'VuFind\\Recommend\\SideFacetsFactory',
        ],
        'aliases' =>
        [
          'VuFind\\Recommend\\SideFacets' => 'DigLib\\Recommend\\SideFacets',
        ],
      ],
      'sitemap' =>
      [
        'factories' =>
        [
          'VuFind\\Sitemap\\Plugin\\Index' => 'DigLib\\Sitemap\\Plugin\\IndexFactory',
        ],
      ],
      'recordtab' =>
      [
        'factories' =>
        [
          'DigLib\\RecordTab\\CollectionList' => 'VuFind\\RecordTab\\CollectionListFactory',
        ],
        'aliases' =>
        [
          'VuFind\\RecordTab\\CollectionList' => 'DigLib\\RecordTab\\CollectionList',
        ],
      ],
    ],
  ],
  'controllers' =>
  [
    'factories' =>
    [
      'DigLib\\Controller\\VuDLController' => 'VuFind\\Controller\\AbstractBaseFactory',
      'DigLib\\Controller\\CollectionController' => 'VuFind\\Controller\\AbstractBaseWithConfigFactory',
      'DigLib\\Controller\\CollectionsController' => 'VuFind\\Controller\\AbstractBaseWithConfigFactory',
      'DigLib\\Controller\\RecordController' => 'VuFind\\Controller\\AbstractBaseWithConfigFactory',
      'DigLib\\Controller\\RedirectController' => 'VuFind\\Controller\\AbstractBaseFactory',
      'DigLib\\Controller\\XSLTController' => 'VuFind\\Controller\\AbstractBaseFactory',
    ],
    'aliases' =>
    [
      'Redirect' => 'DigLib\\Controller\\RedirectController',
      'redirect' => 'DigLib\\Controller\\RedirectController',
      'VuDL' => 'DigLib\\Controller\\VuDLController',
      'vudl' => 'DigLib\\Controller\\VuDLController',
      'VuFind\\Controller\\CollectionController' => 'DigLib\\Controller\\CollectionController',
      'VuFind\\Controller\\CollectionsController' => 'DigLib\\Controller\\CollectionsController',
      'VuFind\\Controller\\RecordController' => 'DigLib\\Controller\\RecordController',
      'XSLT' => 'DigLib\\Controller\\XSLTController',
      'xslt' => 'DigLib\\Controller\\XSLTController',
    ],
  ],
  'router' =>
  [
    'routes' =>
    [
      'files' =>
      [
        'type' => 'Laminas\\Router\\Http\\Segment',
        'options' =>
        [
          'route' => '/files/:id/:type',
        ],
      ],
      'vudl-about' =>
      [
        'type' => 'Laminas\\Router\\Http\\Literal',
        'options' =>
        [
          'route' => '/VuDL/About',
          'defaults' =>
          [
            'controller' => 'VuDL',
            'action' => 'About',
          ],
        ],
      ],
      'vudl-default-collection' =>
      [
        'type' => 'Laminas\\Router\\Http\\Segment',
        'options' =>
        [
          'route' => '/Collection[/]',
          'defaults' =>
          [
            'controller' => 'Collection',
            'action' => 'Home',
            'id' => 'vudl:3',
          ],
        ],
      ],
      'vudl-grid' =>
      [
        'type' => 'Laminas\\Router\\Http\\Segment',
        'options' =>
        [
          'route' => '/Grid/:id',
          'defaults' =>
          [
            'controller' => 'VuDL',
            'action' => 'Grid',
          ],
        ],
      ],
      'vudl-home' =>
      [
        'type' => 'Laminas\\Router\\Http\\Segment',
        'options' =>
        [
          'route' => '/VuDL/Home[/]',
          'defaults' =>
          [
            'controller' => 'VuDL',
            'action' => 'Home',
          ],
        ],
      ],
      'vudl-partners' =>
      [
        'type' => 'Laminas\\Router\\Http\\Segment',
        'options' =>
        [
          'route' => '/VuDL/Partners[/]',
          'defaults' =>
          [
            'controller' => 'VuDL',
            'action' => 'Partners',
          ],
        ],
      ],
      'vudl-record' =>
      [
        'type' => 'Laminas\\Router\\Http\\Segment',
        'options' =>
        [
          'route' => '/Item/:id[/:view]',
          'defaults' =>
          [
            'controller' => 'VuDL',
            'action' => 'Record',
          ],
        ],
      ],
      'vudl-sibling' =>
      [
        'type' => 'Laminas\\Router\\Http\\Segment',
        'options' =>
        [
          'route' => '/Vudl/Sibling/',
          'defaults' =>
          [
            'controller' => 'VuDL',
            'action' => 'Sibling',
          ],
        ],
      ],
      'vudl-ajax' =>
      [
        'type' => 'Laminas\\Router\\Http\\Literal',
        'options' =>
        [
          'route' => '/Vudl/Ajax/',
          'defaults' =>
          [
            'controller' => 'VuDL',
            'action' => 'Ajax',
          ],
        ],
      ],
      'vudl-redirect' =>
      [
        'type' => 'Laminas\\Router\\Http\\Segment',
        'options' =>
        [
          'route' => '/:collection[/:file]',
          'constraints' =>
          [
            'collection' => 'Americana|Autographed%20Books%20Collection|Catholica%20Collection|Contributions%20from%20Augustinian%20Theologians%20and%20Scholars|Cuala%20Press%20Broadside%20Collection|Flora,%20Fauna,%20and%20the%20Human%20Form|Flora%2C%20Fauna%2C%20and%20the%20Human%20Form|Hubbard%20Collection|Image%20Collection|Independence%20Seaport%20Museum|Joseph%20McGarrity%20Collection|La%20Salle%20University|Manuscript%20Collection|Pennsylvaniana|Philadelphia%20Ceili%20Group|Rambles,%20Travels,%20and%20Maps|Rambles%2C%20Travels%2C%20and%20Maps|Saint%20Augustine%20Reference%20Library|Sherman%20Thackara%20Collection|Villanova%20Digital%20Collection|World',
            'file' => '.*',
          ],
          'defaults' =>
          [
            'controller' => 'Redirect',
            'action' => 'redirect',
          ],
        ],
      ],
      'vudl-default-item' =>
      [
        'type' => 'Laminas\\Router\\Http\\Segment',
        'options' =>
        [
          'route' => '/Item[/]',
          'defaults' =>
          [
            'controller' => 'Collection',
            'action' => 'Home',
            'id' => 'vudl:3',
          ],
        ],
      ],
      'vudl-about-php' =>
      [
        'type' => 'Laminas\\Router\\Http\\Literal',
        'options' =>
        [
          'route' => '/about.php',
          'defaults' =>
          [
            'controller' => 'Redirect',
            'action' => 'About',
          ],
        ],
      ],
      'vudl-collection-link' =>
      [
        'type' => 'Laminas\\Router\\Http\\Literal',
        'options' =>
        [
          'route' => '/collections.php',
          'defaults' =>
          [
            'controller' => 'Redirect',
            'action' => 'Collection',
          ],
        ],
      ],
      'vudl-copyright' =>
      [
        'type' => 'Laminas\\Router\\Http\\Literal',
        'options' =>
        [
          'route' => '/copyright.html',
          'defaults' =>
          [
            'controller' => 'VuDL',
            'action' => 'Copyright',
          ],
        ],
      ],
      'vudl-rights' =>
      [
        'type' => 'Laminas\\Router\\Http\\Literal',
        'options' =>
        [
          'route' => '/rights.html',
          'defaults' =>
          [
            'controller' => 'VuDL',
            'action' => 'Rights',
          ],
        ],
      ],
      'vudl-secure-passthrough' =>
      [
        'type' => 'Laminas\\Router\\Http\\Segment',
        'options' =>
        [
          'route' => '/files/:id/:stream[/]',
          'defaults' =>
          [
            'controller' => 'VuDL',
            'action' => 'Passthrough',
          ],
        ],
      ],
      'vudl-record-viewer' =>
      [
        'type' => 'Laminas\\Router\\Http\\Segment',
        'options' =>
        [
          'route' => '/Item/:id/Viewer',
          'defaults' =>
          [
            'controller' => 'VuDL',
            'action' => 'Viewer',
          ],
        ],
      ],
      'vudl-record-manifest' =>
      [
        'type' => 'Laminas\\Router\\Http\\Segment',
        'options' =>
        [
          'route' => '/Item/:id/Manifest',
          'defaults' =>
          [
            'controller' => 'VuDL',
            'action' => 'Manifest',
          ],
        ],
      ],
      'vudl-record-canvas' =>
      [
        'type' => 'Laminas\\Router\\Http\\Segment',
        'options' =>
        [
          'route' => '/Item/:id/Canvas/:canvas',
          'defaults' =>
          [
            'controller' => 'VuDL',
            'action' => 'Canvas',
          ],
        ],
      ],
    ],
  ],
  'service_manager' =>
  [
    'factories' =>
    [
      'DigLib\\Connection\\Manager' => 'DigLib\\Connection\\ManagerFactory',
      'DigLib\\Connection\\Fedora' => 'DigLib\\Connection\\FedoraFactory',
      'DigLib\\Connection\\Solr' => 'DigLib\\Connection\\SolrFactory',
    ],
    'aliases' =>
    [
      'manager' => 'DigLib\\Connection\\Manager',
      'fedora' => 'DigLib\\Connection\\Fedora',
      'solr' => 'DigLib\\Connection\\Solr',
    ],
  ],
];
