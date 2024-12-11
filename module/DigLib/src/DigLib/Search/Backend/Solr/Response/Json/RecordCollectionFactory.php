<?php

/**
 * Simple JSON-based factory for record collection.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace DigLib\Search\Backend\Solr\Response\Json;

use VuFind\Record\Loader as RecordLoader;
use VuFindSearch\Exception\InvalidArgumentException;

use function call_user_func;
use function gettype;
use function is_array;

/**
 * Simple JSON-based factory for record collection.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class RecordCollectionFactory extends \VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory
{
    /**
     * Record loader
     *
     * @var RecordLoader
     */
    protected $recordLoader;

    /**
     * Constructor.
     *
     * @param Callable     $recordFactory   Callback to construct records
     * @param string       $collectionClass Class of collection
     * @param RecordLoader $recordLoader    Record loader
     *
     * @return void
     */
    public function __construct(
        $recordFactory,
        $collectionClass,
        RecordLoader $recordLoader
    ) {
        parent::__construct($recordFactory, $collectionClass);
        $this->recordLoader = $recordLoader;
    }

    /**
     * Return record collection.
     *
     * @param array $response Deserialized JSON response
     *
     * @return RecordCollection
     */
    public function factory($response)
    {
        if (!is_array($response)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unexpected type of value: Expected array, got %s',
                    gettype($response)
                )
            );
        }
        // If we have a non-grouped response, we can fall back on the legacy behavior:
        if (!isset($response['grouped'])) {
            return parent::factory($response);
        }
        // If we have a grouped response, we need to do some reformatting:
        $response['response']['numFound']
            = $response['grouped']['hierarchy_first_parent_id_str']['ngroups'] ?? 0;
        $collection = new $this->collectionClass($response);
        $groups = $response['grouped']['hierarchy_first_parent_id_str']['groups']
            ?? [];
        foreach ($groups as $group) {
            if ($group['groupValue'] == null) {
                continue;
            }
            $matchedDoc = false;
            $subPages = [];
            foreach ($group['doclist']['docs'] as $doc) {
                if ($doc['id'] == $group['groupValue']) {
                    $matchedDoc = $doc;
                } else {
                    $subRecord = call_user_func($this->recordFactory, $doc);
                    /**
                     * Blocked subpages that aren't images until UV support direct to
                     * download parameters
                     *
                     * - Chris (11 Feb 2016)
                     */
                    $type = str_replace(
                        'vudl-system:',
                        '',
                        implode($doc['modeltype_str_mv'])
                    );
                    if (!str_contains($type, 'ImageData')) {
                        continue;
                    }
                    $subPages[] = [
                        'id'    => $subRecord->getUniqueId(),
                        'thumb' => $subRecord->getThumbnail('small'),
                        'title' => $subRecord->getShortTitle(),
                        'type'  => $type,
                    ];
                }
            }
            if ($matchedDoc) {
                $record = call_user_func($this->recordFactory, $matchedDoc);
            } else {
                try {
                    $record = $this->recordLoader->load($group['groupValue']);
                } catch (\Exception $e) {
                    continue;
                }
            }
            $record->setSubPages($subPages);
            $collection->add($record);
        }
        return $collection;
    }
}
