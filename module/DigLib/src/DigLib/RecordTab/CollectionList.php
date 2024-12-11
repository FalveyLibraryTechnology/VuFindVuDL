<?php

/**
 * Collection list tab
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace DigLib\RecordTab;

use VuFind\Search\RecommendListener;

/**
 * Collection list tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class CollectionList extends \VuFind\RecordTab\CollectionList
{
    /**
     * Get the processed search results.
     *
     * @return \VuFind\Search\SolrCollection\Results
     */
    public function getResults()
    {
        if (null === $this->results) {
            $driver = $this->getRecordDriver();
            $request = $this->getRequest()->getQuery()->toArray()
                + $this->getRequest()->getPost()->toArray();
            $rManager = $this->recommendManager;
            $cb = function ($runner, $params, $searchId) use ($driver, $rManager, $request) {
                $params->initFromRecordDriver($driver, '' !== ($request['lookfor'] ?? ''));
                // VU customization: ensure the default sort is set correctly
                if (empty($request['sort'])) {
                    $params->setSort($params->getDefaultSort());
                }
                // end VU customization
                $listener = new RecommendListener($rManager, $searchId);
                $listener->setConfig(
                    $params->getOptions()->getRecommendationSettings()
                );
                $listener->attach($runner->getEventManager()->getSharedManager());
            };
            $this->results
                = $this->runner->run($request, $this->searchClassId, $cb);
        }
        return $this->results;
    }
}
