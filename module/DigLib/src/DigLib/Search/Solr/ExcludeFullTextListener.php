<?php

/**
 * "Exclude fulltext" listener
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
 * @link     http://vufind.org   Main Site
 */

namespace DigLib\Search\Solr;

use Laminas\EventManager\EventInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use VuFindSearch\Backend\BackendInterface;

use function is_array;
use function is_callable;

/**
 * "Exclude fulltext" listener
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ExcludeFullTextListener
{
    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Constructor.
     *
     * @param BackendInterface $backend Backend
     *
     * @return void
     */
    public function __construct(BackendInterface $backend)
    {
        $this->backend = $backend;
    }

    /**
     * Attach listener to shared event manager.
     *
     * @param SharedEventManagerInterface $manager Shared event manager
     *
     * @return void
     */
    public function attach(SharedEventManagerInterface $manager)
    {
        $manager->attach('VuFind\Search', 'pre', [$this, 'onSearchPre']);
    }

    /**
     * Set up full text handling.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        $command = $event->getParam('command');
        if (
            $command->getTargetIdentifier() === $this->backend->getIdentifier()
            && $command instanceof \VuFindSearch\Command\SearchCommand
        ) {
            $params = $command->getSearchParameters();
            $query = $command->getQuery();
            if ($params && $query) {
                $filters = $params->get('fq');
                $newFilters = [];
                $foundFilter = false;
                if (is_array($filters)) {
                    foreach ($filters as $filter) {
                        if ($filter == 'disable_fulltext:"true"') {
                            $foundFilter = true;
                        } else {
                            $newFilters[] = $filter;
                        }
                    }
                }
                if ($query instanceof \VuFindSearch\Query\Query) {
                    if ($foundFilter) {
                        $query->setHandler('NoFullText');
                    } elseif (
                        is_callable([$query, 'getHandler'])
                        && $query->getHandler() == 'NoFullText'
                    ) {
                        $query->setHandler('AllFields');
                    }
                }
                $params->set('fq', $newFilters);
            }
        }
        return $event;
    }
}
