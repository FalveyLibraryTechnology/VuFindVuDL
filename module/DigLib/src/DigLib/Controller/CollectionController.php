<?php

/**
 * Collection controller
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace DigLib\Controller;

use function count;

/**
 * Collection controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class CollectionController extends \VuFind\Controller\CollectionController
{
    use Feature\ParentArrayTrait;

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
     * Given a record ID, find the IDs of all immediate parent collections.
     *
     * @param string $id ID to find parents for
     *
     * @return array
     */
    protected function getImmediateParentIds($id)
    {
        $connector = $this->serviceLocator->get(\DigLib\Connection\Manager::class);
        $parents = $connector->getParentList($id);
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
     * Format parent IDs into URIs for 'within' section.
     *
     * @param string $id ID to find parents for
     *
     * @return array
     */
    protected function getParentData($id)
    {
        $serverHelper = $this->getViewRenderer()->plugin('serverurl');
        $uris = [];
        foreach ($this->getImmediateParentIds($id) as $current) {
            $uri = $this->url()
                ->fromRoute('collection', ['id' => $current, 'tab' => 'IIIF']);
            $uris[] = $serverHelper($uri);
        }
        return count($uris) == 1 ? $uris[0] : $uris;
    }

    /**
     * Get children for a particular Solr ID.
     *
     * @param string $id ID to retrieve.
     *
     * @return array
     */
    protected function getDataForId($id)
    {
        $solr = $this->serviceLocator->get(\VuFind\Search\BackendManager::class)
            ->get('Solr')->getConnector();
        $solrMap = $solr->getMap();
        $solrMap->setParameters('select', 'defaults', []);
        $solrMap->setParameters('select', 'appends', []);

        $query = new \VuFindSearch\ParamBag(
            [
                'q' => '(id:"' . $id . '" OR hierarchy_parent_id:"'
                    . $id . '") AND fgs.state_txt_mv:Active',
                'fl' => 'id, is_hierarchy_title, title',
                'rows' => '100000',
                'sort' => 'title_sort asc',
                'wt' => 'json',
            ]
        );
        $response = json_decode($solr->search($query));
        $result = [
            '@context' => 'http://iiif.io/api/presentation/2/context.json',
            '@id' => 'dummy',
            '@type' => 'sc:Collection',
            'label' => 'dummy',
            'collections' => [],
            'manifests' => [],
        ];
        $serverHelper = $this->getViewRenderer()->plugin('serverurl');
        $myUri = $myLabel = false;
        foreach ($response->response->docs as $current) {
            $type = isset($current->is_hierarchy_title)
                ? 'sc:Collection' : 'sc:Manifest';
            if ($type === 'sc:Collection') {
                $uri = $this->url()->fromRoute(
                    'collection',
                    ['tab' => 'IIIF', 'id' => $current->id]
                );
                $target = 'collections';
            } else {
                $uri = $this->url()->fromRoute(
                    'vudl-record-manifest',
                    ['id' => $current->id]
                );
                $target = 'manifests';
            }
            if ($id == $current->id) {
                $myUri = $serverHelper($uri);
                $myLabel = $current->title;
            } else {
                $result[$target][] = [
                    'label' => $current->title,
                    '@id' =>  $serverHelper($uri),
                    '@type' => $type,
                ];
            }
        }
        foreach (['collections', 'manifests'] as $type) {
            if (empty($result[$type])) {
                unset($result[$type]);
            }
        }
        if ($myUri) {
            $result['@id'] = $myUri;
        }
        if ($myLabel) {
            $result['label'] = $myLabel;
        }
        $within = $this->getParentData($id);
        if (!empty($within)) {
            $result['within'] = $within;
        }
        return $result;
    }

    /**
     * Build an IIIF collection array.
     *
     * @return array
     */
    protected function getIiifCollection()
    {
        return $this->getDataForId(
            $this->params()->fromRoute('id')
        );
    }

    /**
     * Display a particular tab.
     *
     * @param string $tab  Name of tab to display
     * @param bool   $ajax Are we in AJAX mode?
     *
     * @return mixed
     */
    protected function showTab($tab, $ajax = false)
    {
        if (strtolower($tab) === 'iiif') {
            return $this->buildJsonResponse($this->getIiifCollection());
        }
        $result = parent::showTab($tab, $ajax);
        if (!$ajax && $result instanceof \Laminas\View\Model\ViewModel) {
            $result->setTemplate('collection/view');
        }
        $result->parents = $this->getParentArray();
        return $result;
    }
}
