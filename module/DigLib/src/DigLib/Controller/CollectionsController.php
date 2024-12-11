<?php

/**
 * Collections controller
 *
 * PHP Version 7
 *
 * Copyright (C) Villanova University 2021.
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

/**
 * Collections controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class CollectionsController extends \VuFind\Controller\CollectionsController
{
    /**
     * Show the Browse Menu
     *
     * @return mixed
     */
    protected function showBrowseIndex()
    {
        $solr = $this->serviceLocator->get(\VuFind\Search\BackendManager::class)
            ->get('Solr')->getConnector();
        $solrMap = $solr->getMap();
        $solrMap->setParameters('select', 'defaults', []);
        $solrMap->setParameters('select', 'appends', []);

        $query = new \VuFindSearch\ParamBag(
            [
                'q' => 'hierarchy_parent_id:"vudl:3" AND fgs.state_txt_mv:Active',
                'fl' => 'id, is_hierarchy_title',
                'rows' => '1000',
                'sort' => 'title_sort asc',
                'wt' => 'json',
            ]
        );
        $response = json_decode($solr->search($query));
        $result = [];
        foreach ($response->response->docs as $current) {
            $result[] = [
                'displayText' => $current->is_hierarchy_title ?? '-',
                'value' =>  $current->id,
                'count' => 'view',
            ];
        }

        // Begin building view model:
        $view = $this->createViewModel();

        // Send other relevant values to the template:
        $view->from = '';
        $view->result = $result;
        $view->letters = [];
        $view->filters = [];

        // Display the page:
        return $view;
    }
}
