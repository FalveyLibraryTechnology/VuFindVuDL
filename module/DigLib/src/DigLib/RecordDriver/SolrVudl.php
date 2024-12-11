<?php

/**
 * Model for VuDL records in Solr.
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */

namespace DigLib\RecordDriver;

use function in_array;

/**
 * Model for VuDL records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrVudl extends \VuFind\RecordDriver\SolrDefault
{
    /**
     * Full VuDL record
     *
     * @var \SimpleXML
     */
    protected $fullRecord = null;

    /**
     * VuDL config
     *
     * @var \Laminas\Config\Config
     */
    protected $vuDLConfig = null;

    /**
     * View helper manager
     *
     * @var object
     */
    protected $viewHelpers = null;

    /**
     * Set a view helper manager
     *
     * @param object $helpers View helper manager
     *
     * @return void
     */
    public function setViewHelperManager($helpers)
    {
        $this->viewHelpers = $helpers;
    }

    /**
     * Set module.config.php
     *
     * @param \Laminas\Config\Config $vc Configuration
     *
     * @return void
     */
    public function setVuDLConfig(\Laminas\Config\Config $vc)
    {
        $this->vuDLConfig = $vc;
    }

    /**
     * Parse the full record, if required, and return it.
     *
     * @return object
     */
    public function getFullRecord()
    {
        if (null === $this->fullRecord) {
            $xml = $this->fields['fullrecord'];
            $this->fullRecord = simplexml_load_string($xml);
        }
        return $this->fullRecord;
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        if ($this->isCollection()) {
            return [];
        } else {
            $retval = [
                [
                    'route' => 'vudl-record',
                    'routeParams' => [
                        'id' => $this->getUniqueID(),
                    ],
                ],
            ];
            if ($this->isProtected()) {
                $retval[0]['prefix'] = $this->getProxyUrl();
            }
            return $retval;
        }
    }

    /**
     * Does this record need to be protected from public access?
     *
     * @return bool
     */
    public function isProtected()
    {
        // Is license_str set?
        if (!isset($this->fields['license_str'])) {
            return false;
        }
        // Check IP range
        $userIP = $_SERVER['REMOTE_ADDR'];
        $range = isset($this->vuDLConfig->Access->ip_range)
            ? $this->vuDLConfig->Access->ip_range->toArray() : [];
        foreach ($range as $ip) {
            if (str_starts_with($userIP, $ip)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get the proxy prefix for protecting this record.
     *
     * @return string
     */
    protected function getProxyURL()
    {
        return $this->vuDLConfig->Access->proxy_url ?? '';
    }

    /**
     * Get the short title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        // Many titles have author information separated by a slash; trim it off
        // to improve citations and other brief title displays.
        $titleParts = explode(' / ', parent::getShortTitle());
        return trim($titleParts[0] ?? '');
    }

    /**
     * Returns one of three things: a full URL to a thumbnail preview of the record
     * if an image is available in an external system; an array of parameters to
     * send to VuFind's internal cover generator if no fixed URL exists; or false
     * if no thumbnail can be generated.
     *
     * @param string $size Size of thumbnail (small, medium or large -- small is
     * default).
     *
     * @return string|array|bool
     */
    public function getThumbnail($size = 'small')
    {
        // We can't do anything special without view helper access!
        if (!$this->viewHelpers) {
            return parent::getThumbnail($size);
        }
        $imageLink = $this->viewHelpers->get('imageLink');
        if (isset($this->fields['modeltype_str_mv'])) {
            foreach ($this->fields['modeltype_str_mv'] as $model) {
                if ($model == 'vudl-system:PDFData') {
                    return $imageLink('vudl/pdf.png');
                } elseif ($model == 'vudl-system:DOCData') {
                    return $imageLink('vudl/msword.png');
                } elseif ($model == 'vudl-system:XLSData') {
                    return $imageLink('vudl/msexcel.png');
                } elseif ($model == 'vudl-system:AudioData') {
                    return $imageLink('vudl/audio.png');
                }
            }
        }
        if (($this->fields['has_thumbnail_str'] ?? 'false') !== 'true') {
            return $imageLink('vudl/default.png');
        }
        $urlHelper = $this->viewHelpers->get('url');
        return $urlHelper(
            'files',
            ['id' => $this->getUniqueID(), 'type' => 'THUMBNAIL']
        );
    }

    /**
     * Return an XML representation of the record using the specified format.
     * Return false if the format is unsupported.
     *
     * @param string     $format     Name of format to use (corresponds with OAI-PMH
     * metadataPrefix parameter).
     * @param string     $baseUrl    Base URL of host containing VuFind (optional;
     * may be used to inject record URLs into XML when appropriate).
     * @param RecordLink $recordLink Record link helper (optional; may be used to
     * inject record URLs into XML when appropriate).
     *
     * @return mixed         XML, or false if format unsupported.
     */
    public function getXML($format, $baseUrl = null, $recordLink = null)
    {
        $result = parent::getXML($format, $baseUrl, $recordLink);
        if ($result && $format == 'oai_dc') {
            $dc = 'http://purl.org/dc/elements/1.1/';
            $xml = new \SimpleXMLElement($result);
            $xml->addChild(
                'rights',
                $this->fields['license.mdRef_str'] ?? '',
                $dc
            );
            $imageFormats = ['Image', 'Map', 'Realia'];
            $videoFormats = ['Video', 'Moving image'];
            $type = 'Text';
            foreach ($this->getFormats() as $format) {
                if (in_array($format, $imageFormats)) {
                    $type = 'Image';
                } elseif ($format == 'Sound') {
                    $type = 'Sound';
                } elseif (in_array($format, $videoFormats)) {
                    $type = 'Moving Image';
                }
            }
            $folderCollection = 'vudl-system:FolderCollection';
            if (
                isset($this->fields['modeltype_str_mv'])
                && in_array($folderCollection, $this->fields['modeltype_str_mv'])
            ) {
                $type = 'Collection';
            }
            $xml->addChild('type', $type, $dc);
            foreach ($xml->xpath('//dc:identifier') as $element) {
                $element[0] = str_replace(
                    'villanova.edu/Record/',
                    'villanova.edu/Item/',
                    $element[0]
                );
            }
            return $xml->asXml();
        }
        return $result;
    }

    /**
     * Set subpages
     *
     * @param array $subpages Array of subpages
     *
     * @return SolrVudl
     */
    public function setSubPages($subpages)
    {
        $this->fields['subpages'] = $subpages;
        return $this;
    }

    /**
     * Set subpages
     *
     * @return array
     */
    public function getSubPages()
    {
        return $this->fields['subpages']
            ?? [];
    }
}
