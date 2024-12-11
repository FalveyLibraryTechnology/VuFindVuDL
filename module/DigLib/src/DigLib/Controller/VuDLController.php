<?php

/**
 * VuDLController Module Controller
 *
 * PHP Version 7
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   David Lacy <david.lacy@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace DigLib\Controller;

use Laminas\View\Model\ViewModel;

use function count;
use function in_array;
use function is_array;

/**
 * This controller is for the viewing of the digital library files.
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VuDLController extends \VuFind\Controller\AbstractBase
{
    /**
     * VuDL config
     *
     * @var \Laminas\Config\Config
     */
    protected $vuDLConfig = null;

    /**
     * Create a new ViewModel with title.
     *
     * @param array $params Parameters to pass to ViewModel constructor.
     *
     * @return ViewModel
     */
    protected function createViewModel($params = null)
    {
        $view = parent::createViewModel($params);
        $view->title = $this->getVuDLConfig()->General->title;
        return $view;
    }

    /**
     * Get the VuDL configuration object.
     *
     * @return \Laminas\Config\Config
     */
    protected function getVuDLConfig()
    {
        if (null === $this->vuDLConfig) {
            $this->vuDLConfig = $this->serviceLocator
                ->get(\VuFind\Config\PluginManager::class)->get('VuDL');
        }
        return $this->vuDLConfig;
    }

    /**
     * Get VuDL Licenses.
     *
     * @return object
     */
    protected function getConnector()
    {
        return $this->serviceLocator->get(\DigLib\Connection\Manager::class);
    }

    /**
     * Get VuDL Licenses.
     *
     * @return array
     */
    protected function getLicenses()
    {
        $cfg = $this->getVuDLConfig();
        return isset($cfg->Licenses) ? $cfg->Licenses->toArray() : [];
    }

    /**
     * Get VuDL Routes.
     *
     * @return array
     */
    protected function getVuDLRoutes()
    {
        $cfg = $this->getVuDLConfig();
        return isset($cfg->Routes) ? $cfg->Routes->toArray() : [];
    }

    /**
     * Retrieve the object cache.
     *
     * @return object
     */
    protected function getCache()
    {
        return $this->serviceLocator->get(\VuFind\Cache\Manager::class)
            ->getCache('object');
    }

    /**
     * Returns the root id for any parent this item may have
     * ie. If we're requesting a specific page, return the book
     *
     * @param string $id record id
     *
     * @return string $id
     */
    protected function getRoot($id)
    {
        $parents = array_reverse($this->getConnector()->getParentList($id));
        if (empty($parents)) {
            return $id;
        }
        foreach (array_keys($parents[0]) as $i) {
            $classes = $this->getConnector()->getClasses($i);
            if (in_array('ResourceCollection', $classes)) {
                return $i;
            }
        }
        return $id;
    }

    /**
     * Returns the page number of the child in a parent
     *
     * @param string $parent parent record id
     * @param string $child  child record id
     *
     * @return array
     */
    protected function getPage($parent, $child)
    {
        // GET LISTS
        $lists = $this->getConnector()->getOrderedMembers($parent);
        // GET LIST ITEMS
        foreach ($lists as $list => $list_data) {
            $items = $this->getConnector()->getOrderedMembers($list_data);
            foreach ($items as $i => $id) {
                if ($id == $child) {
                    return [$list, $i];
                }
            }
        }
        return [null, null];
    }

    /**
     * Get the technical metadata for a record from POST
     *
     * @return array
     */
    protected function getTechInfo()
    {
        return $this->getConnector()->getTechInfo(
            $this->params()->fromPost(),
            $this->getViewRenderer()
        );
    }

    /**
     * Ajax function for the VuDL view
     * Return JSON encoding of pages
     *
     * @return json string of pages
     */
    public function ajaxAction()
    {
        $method = (string)$this->params()->fromQuery('method');
        $legalMethods = [
            'pageAjax', 'getTechInfo', 'viewLoad', 'getOutline',
        ];
        return $this->jsonReturn(
            in_array($method, $legalMethods) ? $this->$method() : []
        );
    }

    /**
     * Format data for return as JSON
     *
     * @param string $data Data to be encoded
     *
     * @return json string
     */
    protected function jsonReturn($data)
    {
        $output = ['data' => $data, 'status' => 'OK'];
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine(
            'Content-type',
            'application/javascript'
        );
        $headers->addHeaderLine(
            'Cache-Control',
            'no-cache, must-revalidate'
        );
        $headers->addHeaderLine(
            'Expires',
            'Mon, 26 Jul 1997 05:00:00 GMT'
        );
        $response->setContent(json_encode($output));
        return $response;
    }

    /**
     * Returns the outline for the next offset block of records
     *
     * @return associative array
     */
    public function pageAjax()
    {
        $id = $this->params()->fromQuery('record');
        $start = $this->params()->fromQuery('start');
        $end = $this->params()->fromQuery('end');
        $data = [
            'outline' => $this->getOutline($id),
            'start'  => (int)$start,
        ];
        $data['outline'] = current($data['outline']['lists']);
        if (isset($data['outline'])) {
            $data['length'] = count($data['outline']);
        } else {
            $data['length'] = 0;
        }
        return $data;
    }

    /**
     * Template view swapping, this function returns the html from the template
     *
     * @return json string of page
     */
    public function viewLoad()
    {
        $renderer = $this->getViewRenderer();
        $outline = [];
        $data = $this->params()->fromPost();
        if ($data == null) {
            $id = $this->params()->fromQuery('id');
            $outline = $this->getOutline($id);
            $data = $outline['current'];
        }
        $data['keys'] = array_keys($data);
        try {
            $view = $renderer->render(
                'vudl/views/' . $data['filetype'] . '.phtml',
                $data + compact('outline')
            );
        } catch (\Exception $e) {
            $view = $renderer->render(
                'vudl/views/download.phtml',
                $data
            );
        }
        return $view;
    }

    /**
     * Display record in VuDL from Fedora
     *
     * @return View Object
     */
    protected function getLegacyRecordView()
    {
        // Target id
        $id = $this->params()->fromRoute('id');
        if ($id == null) {
            return $this->forwardTo('VuDL', 'Home');
        }

        $classes = $this->getConnector()->getClasses($id);
        if (in_array('FolderCollection', $classes)) {
            return $this->forwardTo('Collection', 'Home', ['id' => $id]);
        }
        $view = $this->createViewModel();

        // Check if we're a ResourceObject || find parent
        $root = $this->getRoot($id);
        [$currList, $currPage] = $this->getPage($root, $id);
        $view->initPage = $root == $id ? 0 : $currPage;
        $view->initList = $currList ?: 0;
        $view->id = $root;

        try {
            $driver = $this->getRecordLoader()->load($root, 'VuFind');
        } catch (\Exception $e) {
        }
        if (isset($driver) && $driver->isProtected()) {
            return $this->forwardTo('VuDL', 'Denied', ['id' => $id]);
        }

        // File information / description
        $fileDetails = $this->getConnector()->getDetails($root, true);

        // Copyright information
        [$fileDetails['license'], $fileDetails['special_license']]
            = $this->getConnector()->getCopyright($root, $this->getLicenses());

        $view->details = $fileDetails;

        // Get ids for all files
        $outline = $this->getOutline($root, $id);

        // Send the data for the first pages
        // (Original, Large, Medium, Thumbnail srcs) and THE DOCUMENTS
        $view->outline = $outline;
        $parents = $this->getConnector()->getParentList($root);
        //$keys = array_keys($parents);
        //$view->hierarchyID = end($keys);
        $view->parents = $parents;
        if ($id != $root) {
            $view->parentID = $root;
            $view->breadcrumbEnd = $outline['current']['label'];
        }
        $view->pagelength = $this->getConnector()->getPageLength();
        $view->openTab = $this->params()->fromRoute('view', 'medium');
        return $view;
    }

    /**
     * Forward home action to browse
     *
     * @return forward
     */
    public function homeAction()
    {
        $view = $this->createViewModel();
        $config = $this->getVuDLConfig();
        $cache = $this->getCache();
        if ($cache_item = $cache->getItem('vudl_home_thumbnails')) {
            $view->thumbnails = $cache_item;
        } else {
            $children = $this->getConnector()->getMemberList(
                $config->General->root_id,
                true
            );
            $thumbnails = [];
            foreach ($children as $item) {
                $localSrc = APPLICATION_PATH . '/themes/vudiglib/images/' .
                    'vudl-home/' . explode(':', $item['id'])[1] . '.jpg';
                $thumbnails[] = [
                    'id' => $item['id'],
                    'img' => file_exists($localSrc)
                        ? str_replace(
                            APPLICATION_PATH . '/',
                            $this->url()->fromRoute('home'),
                            $localSrc
                        )
                        : $this->url()->fromRoute(
                            'files',
                            [
                                'id'   => $item['id'],
                                'type' => 'THUMBNAIL',
                            ]
                        ),
                    'label' => $item['title'][0],
                ];
            }
            $view->thumbnails = $thumbnails;
            $cache->setItem('vudl_home_thumbnails', $thumbnails);
        }
        // RSS feed
        $rss_cache = $cache->getItem('vudl_home_rss');
        $rssItems = null;
        if (!$rss_cache || (time() - $rss_cache['time']) > 7200) { // two hours
            $rss = 'https://blog.library.villanova.edu/category/blue-electrode/rss';

            try {
                $feed = file_get_contents($rss);

                if ($feed === false) {
                    throw new \Exception("RSS couldn't be reached");
                }

                // massage to allow access to <content:encoded>
                $feed = str_replace('<content:encoded>', '<contentEncoded>', $feed);
                $feed = str_replace('</content:encoded>', '</contentEncoded>', $feed);
                $xml = simplexml_load_string($feed);

                $items = [];
                for ($i = 0; $i < 5; $i++) {
                    $item = $xml->channel->item[$i];

                    $imageRegExp = '/src="([^ "]+)/';
                    preg_match($imageRegExp, $item->description, $match);

                    if (!$match) {
                        preg_match($imageRegExp, $item->contentEncoded, $match);
                    }

                    $items[] = [
                        'title' => (string)$item->title,
                        'date' => (string)$item->pubDate,
                        'link' => (string)$item->link,
                        'image' => $match ? $match[1] : null,
                    ];
                }

                $view->rss = $items;

                $cache->setItem(
                    'vudl_home_rss',
                    [
                        'time' => time(),
                        'rss' => $items,
                    ]
                );
            } catch (\Throwable $e) { // For PHP 7
                // pass
            } catch (\Exception $e) { // For PHP 5
                // pass
            }
        }

        if ($rssItems === null) {
            $rssItems = $rss_cache['rss'] ?? [];
        }

        $view->rss = $rssItems;

        return $view;
    }

    /**
     * Display record in VuDL from Fedora as a grid
     *
     * @return View Object
     */
    public function gridAction()
    {
        $view = $this->createViewModel();
        // Target id
        $id = $this->params()->fromRoute('id');

        // Check if we're a ResourceObject || find parent
        $root = $this->getRoot($id);
        $view->page = $root == $id ? 0 : $this->getPage($root, $id);
        $view->id = $root;

        // File information / description
        $fileDetails = $this->getConnector()->getDetails($root, true);
        $view->details = $fileDetails;

        // Get ids for all files
        $outline = $this->getOutline($root);

        // Send the data for the first pages
        // (Original, Large, Medium, Thumbnail srcs) and THE DOCUMENTS
        $view->outline = $outline;
        $parents = $this->getConnector()->getParentList($root);
        //$keys = array_keys($parents);
        //$view->hierarchyID = end($keys);
        $view->parents = $parents;
        return $view;
    }

    /**
     * About page
     *
     * @return View Object
     */
    public function aboutAction()
    {
        $connector = $this->serviceLocator->get(\VuFind\Search\BackendManager::class)
            ->get('Solr')->getConnector();
        $queries = [
            'folders' => 'modeltype_str_mv:"vudl-system:FolderCollection"',
            'resources' => 'modeltype_str_mv:"vudl-system:ResourceCollection"',
            'images' => 'modeltype_str_mv:"vudl-system:ImageData"',
            'pdfs' => 'modeltype_str_mv:"vudl-system:PDFData"',
        ];
        $response = '';
        $totals = [];
        foreach ($queries as $type => $q) {
            $params = new \VuFindSearch\ParamBag(
                ['q' => $q, 'group' => 'false', 'limit' => 0]
            );
            $response = json_decode($connector->search($params));
            $totals[$type] = $response->response->numFound ?? 0;
        }
        return $this->createViewModel(compact('totals'));
    }

    /**
     * Partners page
     *
     * @return View Object
     */
    public function partnersAction()
    {
        return $this->createViewModel();
    }

    /**
     * Rights page
     *
     * @return View Object
     */
    public function rightsAction()
    {
        return $this->createViewModel();
    }

    /**
     * Copyright page
     *
     * @return ViewModel
     */
    public function copyrightAction()
    {
        return $this->createViewModel();
    }

    /**
     * Access denied screen.
     *
     * @return ViewModel
     */
    protected function deniedAction()
    {
        $view = $this->createViewModel();
        $view->id = $this->params()->fromRoute('id');
        return $view;
    }

    /**
     * Redirect to the appropriate sibling.
     *
     * @return mixed
     */
    protected function siblingAction()
    {
        $params = $this->params()->fromQuery();
        try {
            $members = $this->getConnector()->getOrderedMembers($params['trail']);
        } catch (\VuFindSearch\Backend\Exception\RequestErrorException $e) {
            $response = $this->getResponse();
            $response->setStatusCode($e->getCode());
            $response->setContent($e->getMessage());
            return $response;
        }
        if (count($members) < 2) {
            //return $this->redirect()
            //->toRoute('Collection', 'Home', array('id'=>$params['trail']));
        }
        $index = -1;
        foreach ($members as $i => $member) {
            if ($member == $params['id']) {
                $index = $i + count($members);
                break;
            }
        }
        if ($index == -1) {
            return $this->redirect()
                ->toRoute('collection', ['id' => $params['trail']]);
        } elseif (isset($params['prev'])) {
            return $this->redirect()->toRoute(
                'vudl-record',
                ['id' => $members[($index - 1) % count($members)]]
            );
        } else {
            return $this->redirect()->toRoute(
                'vudl-record',
                ['id' => $members[($index + 1) % count($members)]]
            );
        }
    }

    /**
     * Generate an array of all child pages and their information/images
     *
     * @param string $root record id to search under
     * @param string $id   id to look up
     *
     * @return associative array of the lists with their members
     */
    protected function getOutline($root = null, $id = null)
    {
        $cache = (strtolower($this->params()->fromQuery('cache')) == 'no');
        if (null === $root) {
            $root = $this->params()->fromQuery('root');
        }
        if (null === $id) {
            $id = $this->params()->fromQuery('id', $root);
        }

        // Need to use DigLib OutlineGenerator to solve custom page list sorting
        $generator = new \DigLib\OutlineGenerator(
            $this->getConnector(),
            $this->url(),
            $this->getVuDLRoutes(),
            $cache ? $this->getCache() : false
        );
        return $generator->getOutline(
            $root,
            $id,
            ['width_str', 'height_str', 'sizebytes_str']
        );
    }

    /**
     * Check active state of record.
     *
     * @param string $id Record ID
     *
     * @return bool
     */
    protected function recordIsVisible($id)
    {
        $config = $this->getVuDLConfig();
        $onlyShowActive = isset($config->Access->only_show_active)
            && $config->Access->only_show_active;

        // If all records are visible, no need to do further checking!
        if (!$onlyShowActive) {
            return true;
        }

        // Check visibility setting of object....
        // We use Solr to determine visibility, though this means there may be
        // a delay between items being deactivated and becoming inaccessible.
        // This can be avoided by forcing Solr replication.
        $record = $this->serviceLocator->get(\VuFind\Record\Loader::class)
            ->load($id);
        $data = $record->getRawData();
        return in_array('Active', $data['fgs.state_txt_mv'] ?? []);
    }

    /**
     * Is the provided record information compatible with the universal viewer?
     *
     * @param array $outline Outline
     *
     * @return bool
     */
    protected function universalViewerSupportsOutline($outline)
    {
        if (isset($outline['lists']) && is_array($outline['lists'])) {
            foreach ($outline['lists'] as $list) {
                if (in_array('LARGE', $list[0]['datastreams'])) {
                    return true;
                }
                foreach ($list as $current) {
                    if (
                        in_array('application/pdf', $current['mimetypes'])
                        || in_array('video/mp4', $current['mimetypes'])
                        || in_array('MP4', $current['datastreams'])
                        || in_array('MP3', $current['datastreams'])
                        || in_array('OGG', $current['datastreams'])
                    ) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Display record in VuDL from Fedora using legacy viewer
     *
     * @return ViewModel
     */
    public function legacyrecordAction()
    {
        $id = $this->params()->fromRoute('id');
        if (!$this->recordIsVisible($id)) {
            return $this->forwardTo('VuDL', 'Denied', ['id' => $id]);
        }
        $view = $this->getLegacyRecordView();
        $view->beta_ready = $this->universalViewerSupportsOutline($view->outline)
            ? $id : false;
        return $view;
    }

    /**
     * Format an array into a JSON response.
     *
     * @param array $data Data to format as JSON
     *
     * @return object
     */
    public function buildJsonResponse($data)
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'application/json');
        $headers->addHeaderLine('Access-Control-Allow-Origin', '*');
        $response->setContent(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        return $response;
    }

    /**
     * Display canvas data
     *
     * @return mixed
     */
    public function canvasAction()
    {
        $id = $this->params()->fromRoute('id');
        if (!$this->recordIsVisible($id)) {
            $data = [];
        } else {
            $canvas = str_replace('p', '', $this->params()->fromRoute('canvas'));
            $rootId = $this->getRoot($id);
            $outline = $this->getOutline($rootId);
            $data = $this->getManifestGenerator()
                ->getCanvasData($rootId, $canvas, $outline);
        }
        return $this->buildJsonResponse($data);
    }

    /**
     * Construct a manifest generator.
     *
     * @return \DigLib\IIIF\ManifestGenerator
     */
    protected function getManifestGenerator()
    {
        return new \DigLib\IIIF\ManifestGenerator(
            $this->getViewRenderer()->plugin('serverurl'),
            $this->url(),
            $this->getConnector(),
            $this->getVuDLConfig()
        );
    }

    /**
     * Given a record ID, find the IDs of all immediate parent collections.
     *
     * @param string $id ID to find parents for
     *
     * @return array
     */
    protected function getImmediateParentIds($id)
    {
        $parents = $this->getConnector()->getParentList($id);
        $parentIds = [];
        foreach ($parents as $parent) {
            if (!empty($parent)) {
                $keys = array_keys($parent);
                $parentIds[] = $keys[count($parent) - 1];
            }
        }
        return array_unique($parentIds);
    }

    /**
     * Display record manifest
     *
     * @return mixed
     */
    public function manifestAction()
    {
        // If the request is coming through EZProxy, we need to adjust ourselves to
        // output appropriate URLs. It would be much better if EZProxy could handle
        // this itself, but for some reason it does not appear to support the
        // necessary configuration.
        if ($_SERVER['REMOTE_ADDR'] == '153.104.6.4') {
            $this->getViewRenderer()->plugin('serverurl')->setUseProxy(true);
            $_SERVER['HTTP_X_FORWARDED_HOST'] = str_replace(
                '.library.villanova.edu',
                '-library-villanova-edu.ezp1.villanova.edu',
                $_SERVER['HTTP_HOST']
            );
        }
        $id = $this->params()->fromRoute('id');
        $config = $this->getVuDLConfig();
        $onlyShowActive = isset($config->Access->only_show_active)
            && $config->Access->only_show_active;
        try {
            $driver = $this->getRecordLoader()
                ->load($id, 'VuFind', !$onlyShowActive);
        } catch (\Exception $e) {
            $driver = null;
        }
        if (!isset($driver) || $driver->tryMethod('isProtected')) {
            $data = [];
        } else {
            $rootId = $this->getRoot($id);
            $outline = $this->getOutline($rootId);
            $parents = $this->getImmediateParentIds($id);
            $data = $this->getManifestGenerator()
                ->getManifestData($rootId, $outline, $parents);
        }
        return $this->buildJsonResponse($data);
    }

    /**
     * Secure file passthrough
     *
     * @return mixed
     */
    public function passthroughAction()
    {
        $config = $this->getVuDLConfig();
        $proxy = new \DigLib\DatastreamProxy($config);

        $id = $this->params()->fromRoute('id');
        $stream = $this->params()->fromRoute('stream');

        // Special case for thumbnails; we don't need to protect these like other
        // content, and we want to do something reasonable if they are missing.
        try {
            if ($stream === 'THUMBNAIL') {
                if (!$proxy->inMemoryProxy($id, $stream)) {
                    return $this->forwardTo('Cover', 'Unavailable');
                }
            } else {
                $onlyShowActive = $config->Access->only_show_active ?? false;
                $driver = $this->getRecordLoader()
                    ->load($this->getRoot($id), 'VuFind', !$onlyShowActive);
                if ($driver->tryMethod('isProtected')) {
                    return $this->forwardTo('VuDL', 'Denied', ['id' => $id]);
                }
                $proxy->passthruProxy($id, $stream);
            }
        } catch (\Throwable $e) {
            // Handle 404 errors more gracefully:
            if (str_contains($e->getMessage(), '404 Not Found')) {
                $response = $this->getResponse();
                $response->setStatusCode(404);
                $response->setContent('404 Not Found');
                return $response;
            }
            throw $e;
        }
        die();
    }

    /**
     * Extract IDs from manifest data.
     *
     * @param array $data Manifest data
     *
     * @return array
     */
    protected function getIdsFromManifest($data)
    {
        $ids = [];
        if (isset($data['sequences'][0]['canvases'])) {
            foreach ($data['sequences'][0]['canvases'] as $canvas) {
                preg_match(
                    '/vudl:[0-9]+/',
                    $canvas['rendering'][0]['@id'] ?? '',
                    $matches
                );
                if (isset($matches[0])) {
                    $ids[] = $matches[0];
                }
            }
        }
        return $ids;
    }

    /**
     * Extract OCR data from a set of renderings (if available).
     *
     * @param array $data Manifest data.
     *
     * @return string|bool
     */
    protected function getOcr($data)
    {
        foreach ($data as $current) {
            if (stristr($current['@id'], 'ocr-dirty')) {
                return file_get_contents($current['@id']);
            }
        }
        return false;
    }

    /**
     * Display record in VuDL from Fedora using Universal Viewer
     *
     * @return View Object
     */
    public function recordAction()
    {
        $id = $this->params()->fromRoute('id');
        $rootId = $this->getRoot($id);
        try {
            $outline = $this->getOutline($rootId);
        } catch (\VuFindSearch\Backend\Exception\RequestErrorException $e) {
            $response = $this->getResponse();
            $response->setStatusCode($e->getCode());
            $response->setContent($e->getMessage());
            return $response;
        }
        // Check record exists
        if (empty($outline['counts'])) {
            $this->getResponse()->setStatusCode(404);
            return;
        }
        if (
            !$this->universalViewerSupportsOutline($outline)
            || $this->params()->fromQuery('viewer') === 'legacy'
        ) {
            return $this->legacyrecordAction();
        }

        $config = $this->getVuDLConfig();
        $onlyShowActive = isset($config->Access->only_show_active)
            && $config->Access->only_show_active;
        $driver = $this->getRecordLoader()->load($id, 'VuFind', !$onlyShowActive);
        if ($driver?->tryMethod('isProtected')) {
            return $this->forwardTo('VuDL', 'Denied', ['id' => $id]);
        }

        $view = $this->createViewModel();
        $view->id = $driver->getUniqueId();
        $view->itemTitle = $driver->getShortTitle();
        $view->parents = $this->getConnector()->getParentList($view->id);
        $view->driver = $driver;

        $data = $this->getManifestGenerator()
            ->getManifestData($rootId, $outline);
        $view->metadata = $data['metadata'];
        $orderedIds = $this->getIdsFromManifest($data);
        $idIndex = array_search($id, $orderedIds);
        $idIndex = ($idIndex === false) ? 0 : $idIndex;
        $view->currentId = $orderedIds[$idIndex] ?? null;
        $view->currentIndex = $idIndex;
        $view->ocr = $this->getOcr(
            $data['sequences'][0]['canvases'][$idIndex]['rendering'] ?? []
        );
        $view->prevId = $orderedIds[$idIndex - 1] ?? false;
        $view->nextId = $orderedIds[$idIndex + 1] ?? false;
        $view->setTemplate('vudl/viewer');
        return $view;
    }

    /**
     * Redirect legacy URLs from Universal Viewer beta period.
     *
     * @return View Object
     */
    public function viewerAction()
    {
        $id = $this->params()->fromRoute('id');
        return $this->redirect()->toRoute('vudl-record', ['id' => $id]);
    }
}
