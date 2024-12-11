<?php

/**
 * VuDL outline generator
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  VuDL
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */

namespace DigLib;

use Laminas\Mvc\Controller\Plugin\Url as UrlHelper;

use function count;
use function in_array;

/**
 * VuDL outline generator
 *
 * @category VuFind
 * @package  VuDL
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class OutlineGenerator
{
    /**
     * VuDL connection manager
     *
     * @var connector
     */
    protected $connector;

    /**
     * URL helper
     *
     * @var UrlHelper
     */
    protected $url;

    /**
     * VuDL route configuration
     *
     * @var array
     */
    protected $routes;

    /**
     * Cache object
     *
     * @var object
     */
    protected $cache;

    /**
     * Queues
     *
     * @var array
     */
    protected $queue;

    /**
     * Modification date information
     *
     * @var array
     */
    protected $moddate;

    /**
     * Outline currently under construction
     *
     * @var array
     */
    protected $outline;

    /**
     * Full outline, or abbreviated
     *
     * @var boolean
     */
    protected $full;

    /**
     * Constructor
     *
     * @param Connector   $connector VuDL connection manager
     * @param UrlHelper   $url       URL helper
     * @param array       $routes    VuDL route configuration
     * @param object|bool $cache     Cache object (or false to disable caching)
     */
    public function __construct(
        Connection\Manager $connector,
        UrlHelper $url,
        $routes = [],
        $cache = false
    ) {
        $this->connector = $connector;
        $this->url = $url;
        $this->routes = $routes;
        $this->cache = $cache;
    }

    /**
     * Compares the cache date against a given date. If given date is newer,
     * return false in order to refresh cache. Else return cache!
     *
     * @param string      $key     Unique key of cache object
     * @param string|Date $moddate Date to test cache time freshness
     *
     * @return cache object or false
     */
    protected function getCache($key, $moddate = null)
    {
        if ($this->cache && $cache_item = $this->cache->getItem($key)) {
            if (
                $moddate == null || (isset($cache_item['moddate'])
                && date_create($cache_item['moddate']) >= date_create($moddate))
            ) {
                return $cache_item['outline'];
            }
        }
        return false;
    }

    /**
     * Save cache object with date to test for freshness
     *
     * @param string $key  Unique key of cache object
     * @param object $data Object to save
     *
     * @return cache object or false
     */
    protected function setCache($key, $data)
    {
        if ($this->cache) {
            $this->cache->setItem(
                $key,
                [
                    'moddate' => date(DATE_ATOM),
                    'outline' => $data,
                ]
            );
            return $data;
        }
        return false;
    }

    /**
     * Load information on lists within the specified record.
     *
     * @param string $root  record ID
     * @param array  $extra extra settings
     *
     * @return void
     */
    protected function loadLists($root, $extra)
    {
        // Reset the state of the class:
        $this->queue = $this->moddate = [];
        $this->outline = [
            'counts' => [],
            'names' => [],
            'lists' => [],
        ];

        // Check modification date
        $rootModDate = $this->connector->getModDate($root);
        // Get lists - CUSTOM SORT FOR DOC UNDER PAGE LIST - 11 APRIL 2014
        $lists = $this->connector->getOrderedMembers(
            $root,
            ['title_sort desc']
        );
        // Get list items
        foreach ($lists as $i => $list_id) {
            // Get list name
            $this->outline['names'][] = $this->connector->getLabel($list_id);
            $this->moddate[$i] = $this->connector->getModDate($list_id);
            $items = $this->connector->getDetailedMembers(
                $list_id,
                ['title_sort desc'],
                $extra
            );
            if (null === $items) {
                $items = $this->connector->getOrderedMembers(
                    $list_id,
                    ['title_sort desc']
                );
                $this->queue[$i] = $items;
            } else {
                $this->outline['counts'][$i] = count($items);
                $this->outline['lists'][$i] = $items;
            }
        }
    }

    /**
     * Build a single item within the outline.
     *
     * @param string $id ID of record to retrieve
     *
     * @return array
     */
    protected function buildFullItem($id)
    {
        // Get the file type
        $list = $this->connector->getDatastreamLists($id);
        $datastreams = $list[1] ?? [];
        $mimetypes = $list[2] ?? [];
        $masterIndex = array_search('MASTER', $datastreams);
        $mimetype = $masterIndex !== false ? $mimetypes[$masterIndex] : 'N/A';
        if ($masterIndex === false || empty($mimetypes[$masterIndex]) || $mimetypes[$masterIndex] === 'N/A') {
            $type = 'download';
        } elseif (in_array('MP4', $datastreams)) {
            $type = 'video';
        } else {
            $type = substr(
                $mimetypes[$masterIndex],
                strpos($mimetypes[$masterIndex], '/') + 1
            );
        }
        $details = $this->connector->getDetails($id, false);
        return [
            'id' => $id,
            'fulltype' => $type,
            'mimetype' => $mimetype,
            'filetype' => $this->routes[$type]
                ?? $type,
            'label' => $details['title']
                ?? $id,
            'datastreams' => $datastreams,
            'mimetypes' => $mimetypes,
        ];
    }

    /**
     * Build a single item within the outline.
     *
     * @return void
     */
    protected function formatRecords()
    {
        foreach ($this->outline['lists'] as $l => $list) {
            foreach ($list as $i => $item) {
                $item->mimetype = $item->mimetype[0]
                    ?? '';
                if (!isset($item->mimetypes)) {
                    $item->mimetypes = [];
                }
                $type = 'page';
                foreach ($item->datastreams as $d => $ds) {
                    if ('MASTER' == $ds) {
                        $item->mimetypes[] = $item->mimetype;
                        $type = substr(
                            $item->mimetype,
                            strpos($item->mimetype, '/') + 1
                        );
                    } elseif ('MP4' == $ds) {
                        $item->mimetypes[] = 'video/mp4';
                    } elseif (!isset($item->mimetypes[$d])) {
                        $item->mimetypes[] = 'N/A';
                    }
                }
                if (empty($type)) {
                    $type = 'page';
                }
                $item->fulltype = $type;
                $item->filetype = $this->routes[$type]
                    ?? $type;
                $arrItem = [];
                foreach ($item as $key => $value) {
                    $arrItem[$key] = $value;
                }
                $this->outline['lists'][$l][$i] = $arrItem;
            }
        }
    }

    /**
     * Load all pages and docs.
     *
     * @return void
     */
    protected function loadPagesAndDocs()
    {
        // Get data on all pages and docs
        foreach ($this->queue as $parent => $items) {
            $this->outline['counts'][$parent] = count($items);
            $this->outline['lists'][$parent] = [];
            /* Why?
            $params = new \VuFindSearch\ParamBag([
                'id' => '',
                'fl' => 'id, width:width_str, height:height_str,'
                    . 'datastreams:datastream_str_mv',
                'wt' => 'json'
            ]);
            $response = $this->connector->search($params);
            */
            for ($i = 0; $i < count($items); $i++) {
                if ($i >= count($items)) {
                    break;
                }
                $id = $items[$i];
                // If there's a cache of this page...
                $item = $this->getCache(md5($id), $this->moddate[$parent]);
                if (!$item) {
                    $item = $this->buildFullItem($id);
                    $this->setCache(md5($id), $item);
                }
                $this->outline['lists'][$parent][$i] = $item;
            }
        }
    }

    /**
     * Generate an array of all child pages and their information/images
     *
     * @param string $root  record id to search under
     * @param string $id    page/doc to start with for the return
     * @param array  $extra extra settings
     *
     * @return associative array of the lists with their members
     */
    public function getOutline($root, $id, $extra = [])
    {
        $cache = $this->getCache(md5($root), $this->connector->getModDate($id));
        if ($cache) {
            return $cache;
        } else {
            $this->loadLists($root, $extra);
            $firstList = -1;
            foreach ($this->outline['lists'] as $index => $list) {
                if (!empty($list)) {
                    $firstList = $index;
                    break;
                }
            }
            if ($firstList == -1) {
                $this->setCache(md5($root), $this->outline);
                return $this->outline;
            }
            if (isset($this->outline['lists'][$firstList])) {
                $this->formatRecords();
            } else {
                $this->loadPagesAndDocs();
            }
            if (isset($this->outline['lists'][$firstList])) {
                $currentID = $root == $id
                    ? $this->outline['lists'][$firstList][0]['id'] : $id;
            } else {
                $this->setCache(md5($root), $this->outline);
                return $this->outline;
            }
        }
        // Get details for current item
        $this->outline['current'] = $this->buildFullItem($currentID);
        $this->setCache(md5($root), $this->outline);
        return $this->outline;
    }
}
