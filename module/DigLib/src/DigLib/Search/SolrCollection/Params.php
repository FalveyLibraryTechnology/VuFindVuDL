<?php

/**
 * Solr Collection aspect of the Search Multi-class (Params)
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
 * @package  Search_SolrAuthor
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace DigLib\Search\SolrCollection;

use function is_object;

/**
 * Solr Collection aspect of the Search Multi-class (Params)
 *
 * @category VuFind
 * @package  Search_SolrAuthor
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Params extends \VuFind\Search\SolrCollection\Params
{
    /**
     * Record driver representing the collection
     *
     * @var \VuFind\RecordDriver\AbstractBase
     */
    protected $collectionRecord = null;

    /**
     * Record loader
     *
     * @var \VuFind\Record\Loader
     */
    protected $recordLoader;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Options  $options      Options to use
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     * @param \VuFind\Record\Loader        $recordLoader Record loader
     */
    public function __construct($options, $configLoader, $recordLoader)
    {
        parent::__construct($options, $configLoader);
        $this->recordLoader = $recordLoader;
    }

    /**
     * Pull the search parameters from the query and set up additional options using
     * a record driver representing a collection.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver    Record driver
     * @param bool                              $hasSearch Is the user performing a search?
     *
     * @return void
     */
    public function initFromRecordDriver($driver, bool $hasSearch = false)
    {
        $this->collectionRecord = $driver;
        parent::initFromRecordDriver($driver, $hasSearch);
    }

    /**
     * Create search backend parameters for advanced features.
     *
     * @return ParamBag
     */
    public function getBackendParameters()
    {
        $params = parent::getBackendParameters();
        // Do some magic to use the collection-specific sort field in place of the
        // generic one:
        $sort = $params->get('sort');
        if (
            isset($sort[0])
            && is_object($this->collectionRecord)
            && str_contains($sort[0], 'hierarchy_sequence_sort_str')
        ) {
            $sortField = 'sequence_'
                . str_replace(':', '_', $this->collectionRecord->getUniqueId())
                . '_str';
            $sort[0]
                = str_replace('hierarchy_sequence_sort_str', $sortField, $sort[0]);
            $params->set('sort', $sort);
        }
        return $params;
    }

    /**
     * Get record driver representing the collection
     *
     * @return \VuFind\RecordDriver\AbstractBase
     */
    protected function getCollectionRecord()
    {
        if (null === $this->collectionRecord && !empty($this->collectionID)) {
            $this->collectionRecord = $this->recordLoader->load($this->collectionID);
        }
        return $this->collectionRecord;
    }

    /**
     * Return the default sorting value
     *
     * @return string
     */
    public function getDefaultSort()
    {
        $record = $this->getCollectionRecord();
        $fields = null === $record ? [] : $record->getRawData();
        if (isset($fields['has_order_str']) && $fields['has_order_str'] == 'yes') {
            return $this->getOptions()
                ->getDefaultSortByHandler($this->getSearchHandler());
        }
        return 'title';
    }
}
