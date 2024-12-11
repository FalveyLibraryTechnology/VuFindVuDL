<?php

/**
 * Factory for the default SOLR backend.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2013.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace DigLib\Search\Factory;

use DigLib\Search\Backend\Solr\Response\Json\RecordCollectionFactory;
use DigLib\Search\Solr\ExcludeFullTextListener;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\Connector;

/**
 * Factory for the default SOLR backend.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class SolrDefaultBackendFactory extends \VuFind\Search\Factory\SolrDefaultBackendFactory
{
    /**
     * Create the SOLR backend.
     *
     * @param Connector $connector Connector
     *
     * @return Backend
     */
    protected function createBackend(Connector $connector)
    {
        $backend = parent::createBackend($connector);
        $manager = $this->serviceLocator
            ->get(\VuFind\RecordDriver\PluginManager::class);
        $factory = new RecordCollectionFactory(
            [$manager, 'getSolrRecord'],
            \VuFindSearch\Backend\Solr\Response\Json\RecordCollection::class,
            $this->serviceLocator->get(\VuFind\Record\Loader::class)
        );
        $backend->setRecordCollectionFactory($factory);
        return $backend;
    }

    /**
     * Create the SOLR connector.
     *
     * @return Connector
     */
    protected function createConnector()
    {
        $connector = parent::createConnector();
        $map = $connector->getMap()->getParameters('select', 'defaults');
        $map->add('group', 'true');
        $map->add('group.field', 'hierarchy_first_parent_id_str');
        $map->add('group.limit', 10);
        $map->add('group.ngroups', 'true');

        return $connector;
    }

    /**
     * Create listeners.
     *
     * @param Backend $backend Backend
     *
     * @return void
     */
    protected function createListeners(Backend $backend)
    {
        // Set up standard listeners:
        parent::createListeners($backend);

        // Get event manager:
        $events = $this->serviceLocator->get('SharedEventManager');

        // Full text:
        $l = new ExcludeFullTextListener($backend);
        $l->attach($events);
    }
}
