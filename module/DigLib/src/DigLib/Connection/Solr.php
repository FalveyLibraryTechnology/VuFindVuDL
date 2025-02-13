<?php

/**
 * VuDL to Solr connection class (defines some methods to talk to Solr)
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
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org/wiki/
 */

namespace DigLib\Connection;

use VuFindSearch\ParamBag;

use function in_array;

/**
 * VuDL-Fedora connection class
 * * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org/wiki/
 */
class Solr extends AbstractBase
{
    /**
     * Connector class to Solr
     *
     * @var \VuFindSearch\Backend\Solr\Connector
     */
    protected $solr;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config        $config  config
     * @param \VuFind\Search\BackendManager $backend backend manager
     */
    public function __construct($config, $backend)
    {
        $this->config = $config;
        $this->solr = $backend->getConnector();
    }

    /**
     * Returns an array of classes for this object
     *
     * @param string $id record id
     *
     * @return array
     */
    public function getClasses($id)
    {
        // Get record
        $response = $this->search(
            new ParamBag(
                [
                    'q'  => 'id:"' . $id . '"',
                    'fl' => 'modeltype_str_mv',
                ]
            )
        );
        $record = json_decode($response);
        if ($record->response->numFound > 0) {
            return array_map(
                function ($op) {
                    return substr($op, 12);
                },
                $record->response->docs[0]->modeltype_str_mv
            );
        }
        return null;
    }

    /**
     * Returns file contents of the structmap, our most common call
     *
     * @param string $id Record id
     *
     * @return string|\SimpleXMLElement
     */
    public function getDatastreamLists($id)
    {
        // Get record
        $response = $this->search(
            new ParamBag(
                [
                    'q'  => 'id:"' . $id . '"',
                    'fl' => 'datastream_str_mv,mime_str_mv',
                ]
            )
        );
        $record = json_decode($response);
        if ($record->response->numFound > 0) {
            $datastreams = $record->response->docs[0]->datastream_str_mv ?? [];
            $types = [];
            foreach ($datastreams as $i => $stream) {
                if ($stream === 'MASTER') {
                    $types[$i] = $record->response->docs[0]->mime_str_mv[0] ?? 'N/A';
                } elseif ($stream === 'MP4') {
                    $types[$i] = 'video/mp4';
                } else {
                    $types[$i] = 'N/A';
                }
            }
            return [
                null,
                $record->response->docs[0]->datastream_str_mv,
                $types,
            ];
        }
        return null;
    }

    /**
     * Get details from Solr
     *
     * @param string  $id     ID to look up
     * @param boolean $format Run result through formatDetails?
     *
     * @return array
     * @throws \Exception
     */
    public function getDetails($id, $format)
    {
        if (
            $response = $this->search(
                new ParamBag(
                    [
                    'q' => 'id:"' . $id . '"',
                    ]
                )
            )
        ) {
            $record = json_decode($response);
            if ($format) {
                return $this->formatDetails((array)$record->response->docs[0]);
            }
            if (isset($record->response->docs[0])) {
                return (array)$record->response->docs[0];
            }
        }
        return null;
    }

    /**
     * Get an item's label
     *
     * @param string $id Record's id
     *
     * @return string
     */
    public function getLabel($id)
    {
        $labelField = 'dc_title_str';
        $response = $this->search(
            new ParamBag(
                [
                    'q'     => 'id:"' . $id . '"',
                    'fl'    => $labelField,
                ]
            )
        );
        $details = json_decode($response);
        // If we have results
        if ($details->response->numFound > 0) {
            return $details->response->docs[0]->$labelField ?? null;
        }
        return null;
    }

    /**
     * Tuple call to return and parse a list of members...
     *
     * @param string $root       ...for this id
     * @param bool   $activeOnly Filter to active items only?
     *
     * @return array of members in order
     */
    public function getMemberList($root, $activeOnly = false)
    {
        // Get members
        $params = [
            'q'  => 'fedora_parent_id_str_mv:"' . $root . '"',
            'fl' => 'id,hierarchy_top_title',
            'rows' => 100,
        ];
        if ($activeOnly) {
            $params['q'] .= ' AND fgs.state_txt_mv:"Active"';
        }
        $response = $this->search(new ParamBag($params));
        $children = json_decode($response);
        // If we have results
        if ($children->response->numFound > 0) {
            return array_map(
                function ($n) {
                    return [
                        'id' => $n->id,
                        'title' => $n->hierarchy_top_title,
                    ];
                },
                $children->response->docs
            );
        }
        return [];
    }

    /**
     * Get the last modified date from Solr
     *
     * @param string $id ID to look up
     *
     * @return array
     * @throws \Exception
     */
    public function getModDate($id)
    {
        $modfield = 'fgs.lastModifiedDate_txt_mv';
        if (
            $response = $this->search(
                new ParamBag(
                    [
                    'q'     => 'id:"' . $id . '"',
                    'fl'    => $modfield,
                    ]
                )
            )
        ) {
            $record = json_decode($response);
            if ($record->response->numFound > 0) {
                $date = $record->response->docs[0]->$modfield ?? [];
                return $date[0] ?? null;
            }
        }
        return null;
    }

    /**
     * Returns file contents of the structmap, our most common call
     *
     * @param string $id         record id
     * @param array  $extra_sort extra fields to sort on
     *
     * @return string $id
     */
    public function getOrderedMembers($id, $extra_sort = [])
    {
        // Try to find members in order
        $seqField = 'sequence_' . str_replace(':', '_', $id) . '_str';
        $sort = array_merge(
            [$seqField . ' asc'],
            $extra_sort,
            ['title_sort asc']
        );
        $response = $this->search(
            new ParamBag(
                [
                    'q'  => 'fedora_parent_id_str_mv:"' . $id . '"',
                    'sort'  => implode(',', array_unique($sort)),
                    'fl' => 'id',
                    'rows' => 99999,
                ]
            )
        );
        $data = json_decode($response);
        return array_map(
            function ($n) {
                return $n->id;
            },
            $data->response->docs ?? []
        );
    }

    /**
     * Returns file contents of the structmap, our most common call
     *
     * @param string $id            record id
     * @param array  $extra_sort    extra fields to sort on
     * @param array  $extra_details extra details
     *
     * @return string $id
     */
    public function getDetailedMembers($id, $extra_sort = [], $extra_details = [])
    {
        // Try to find members in order
        $seqField = 'sequence_' . str_replace(':', '_', $id) . '_str';
        $sort = [$seqField . ' asc'];
        foreach ($extra_sort as $sf) {
            $sort[] = $sf;
        }
        $fl = 'id,label:title,datastreams:datastream_str_mv,mimetype:mime_str_mv';
        if (!empty($extra_details)) {
            $fl .= ',' . implode(',', $extra_details);
        }
        $response = $this->search(
            new ParamBag(
                [
                    'q'  => 'fedora_parent_id_str_mv:"' . $id . '"',
                    'sort'  => implode(',', $sort),
                    'fl' => $fl,
                    'rows' => 99999,
                ]
            )
        );
        $data = json_decode($response);
        // If we didn't find anything, do a standard members search
        if ($data->response->numFound == 0) {
            return null;
        } else {
            return $data->response->docs;
        }
    }

    /**
     * Tuple call to return and parse a list of parents...
     *
     * @param string $id ...for this id
     *
     * @return array of parents in order from top-down
     */
    public function getParentList($id)
    {
        // Cache
        if (isset($this->parentLists[$id])) {
            return $this->parentLists[$id];
        }
        $queue = [$id];
        $tree = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            if ($current == $this->getRootId()) {
                continue;
            }
            $response = $this->search(
                new ParamBag(
                    [
                        'q'     => 'id:"' . $current . '"',
                        'fl'    => 'hierarchy_parent_id,hierarchy_parent_title',
                    ]
                )
            );
            $data = json_decode($response);
            if ($current == $id && $data->response->numFound == 0) {
                return [];
            }
            // Get info on our record
            $parents = $data->response->docs[0] ?? null;
            foreach ($parents->hierarchy_parent_id ?? [] as $i => $cid) {
                array_push($queue, $cid);
                if (!isset($tree[$cid])) {
                    $tree[$cid] = [
                        'children' => [],
                        'title' => $parents->hierarchy_parent_title[$i] ?? '',
                    ];
                }
                if (!in_array($current, $tree[$cid]['children'])) {
                    $tree[$cid]['children'][] = $current;
                }
            }
        }
        $ret = $this->traceParents($tree, $id);
        // Store in cache
        $this->parentLists[$id] = $ret;
        return $ret;
    }

    /**
     * Get copyright URL and compare it to special cases from VuDL.ini
     *
     * @param array $id          record id
     * @param array $setLicenses ids are strings to match urls to,
     *  the values are abbreviations. Parsed in details.phtml later.
     *
     * @return array
     */
    public function getCopyright($id, $setLicenses)
    {
        $licenseField = 'license.mdRef_str';
        $response = $this->search(
            new ParamBag(
                [
                    'q'     => 'id:"' . $id . '"',
                    'fl'    => $licenseField,
                ]
            )
        );
        $data = json_decode($response);
        $docs = $data->response->docs[0] ?? new \stdClass();
        if ($data->response->numFound == 0 || !isset($docs->$licenseField)) {
            return null;
        }
        $license = $docs->$licenseField;
        foreach ($setLicenses as $tell => $value) {
            if (strpos($license, $tell)) {
                return [$license, $value];
            }
        }
        return [$license, false];
    }

    /**
     * Perform a search with clean params
     *
     * @param ParamBag $paramBag The params you'd normally send to solr
     *
     * @return string
     */
    protected function search($paramBag)
    {
        // Remove global filters from the Solr connector
        $map = $this->solr->getMap();
        $params = $map->getParameters('select', 'appends');
        $map->setParameters('select', 'appends', []);
        // Turn off grouping
        $paramBag->set('group', 'false');
        $paramBag->add('wt', 'json');
        // Search
        $response = $this->solr->search($paramBag);
        // Reapply the global filters
        $map->setParameters('select', 'appends', $params->getArrayCopy());

        return $response;
    }
}
