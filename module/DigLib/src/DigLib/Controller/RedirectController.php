<?php

/**
 * Redirect controller
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

use function count;

/**
 * Redirect controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class RedirectController extends \VuFind\Controller\AbstractBase
{
    /**
     * Run a Solr query; retrieve a list of matching IDs.
     *
     * @param string $query Query to run
     *
     * @return string[]
     */
    protected function querySolr(string $query): array
    {
        $solr = $this->serviceLocator->get(\VuFind\Search\BackendManager::class)
            ->get('Solr')->getConnector();
        $solrMap = $solr->getMap();
        $solrMap->setParameters('select', 'defaults', []);
        $solrMap->setParameters('select', 'appends', []);

        $query = new \VuFindSearch\ParamBag(
            [
                'q' => $query,
                'fl' => 'id',
                'rows' => '2',
                'wt' => 'csv',
            ]
        );
        $results = array_filter(
            array_map('trim', explode("\n", $solr->search($query)))
        );
        array_shift($results);
        return $results;
    }

    /**
     * Redirect legacy (pre-Fedora) URLs to current URLs.
     *
     * @return mixed
     */
    public function redirectAction()
    {
        $collection = $this->params()->fromRoute('collection');
        $file = $this->params()->fromRoute('file');
        $url = "http://digital.library.villanova.edu/$collection/$file";
        // There are inconsistencies in URL encoding in the legacy path data; let's
        // be sure we account for all possibilities:
        $encode = function ($str) {
            return str_replace(['+', '%2F'], ['%20', '/'], urlencode($str));
        };
        $collectionEnc = $encode($collection);
        $fileEnc = $encode($file);
        $urlEnc = "http://digital.library.villanova.edu/$collectionEnc/$fileEnc";
        $query = 'relsext.hasLegacyURL_txt_mv:"' . addcslashes($url, '"') . '"'
            . ' OR relsext.hasLegacyURL_txt_mv:"' . addcslashes($urlEnc, '"') . '"';
        $ids = $this->querySolr($query);
        if (count($ids) > 0) {
            return $this->redirect()->toRoute('vudl-record', ['id' => $ids[0]]);
        }
        $this->flashMessenger()->addErrorMessage(
            'The record URL you requested (' . $url . ') is no longer valid. '
            . 'Please search for your desired record above.'
        );
        return $this->redirect()->toRoute('search-results');
        /*
        throw new \Exception(
            "Could not map legacy URL to ID using $url or {$urlEnc}. "
            . 'Please search for your desired record above.'
        );
         */
    }
}
