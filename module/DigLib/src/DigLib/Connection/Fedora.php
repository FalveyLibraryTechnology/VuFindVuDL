<?php

/**
 * VuDL to Fedora connection class (defines some methods to talk to Fedora)
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

use function count;
use function in_array;
use function intval;

/**
 * VuDL-Fedora connection class
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org/wiki/
 */
class Fedora extends AbstractBase
{
    /**
     * Get Fedora Base URL.
     *
     * @return string
     */
    public function getBase()
    {
        return $this->config->Fedora->api_base ?? null;
    }

    /**
     * Return the content of a datastream.
     *
     * @param string $id         Record id
     * @param string $stream     Name of stream to retrieve
     * @param bool   $justStream Get metadata about stream instead of content
     *
     * @return string
     */
    public function getDatastreamContent($id, $stream, $justStream = false)
    {
        $url = $this->getBase() . $id . '/' . $stream;
        if ($justStream) {
            $client = $this->getHttpClient($url);
            $client->setHeaders(['Accept: application/rdf+xml']);
            $response = $client->send();
            return $response->getBody();
        }
        return file_get_contents($url);
    }

    /**
     * Return the headers of a datastream.
     *
     * @param string $id     Record id
     * @param string $stream Name of stream to retrieve
     *
     * @return string
     */
    public function getDatastreamHeaders($id, $stream)
    {
        return get_headers($this->getBase() . $id . '/' . $stream);
    }

    /**
     * Get details for the sidebar on a record.
     *
     * @param string $id     ID to retrieve
     * @param bool   $format Send result through formatDetails?
     *
     * @return string
     */
    public function getDetails($id, $format = false)
    {
        $dc = [];
        preg_match_all(
            '/<[^\/]*dc:([^ >]+)>([^<]+)/',
            $this->getDatastreamContent($id, 'DC'),
            $dc
        );
        $details = [];
        foreach ($dc[2] as $i => $detail) {
            $details[$dc[1][$i]] = $detail;
        }
        if ($format) {
            return $this->formatDetails($details);
        }
        return $details;
    }

    /**
     * Get an HTTP client
     *
     * @param string $url URL for client to access
     *
     * @return \Laminas\Http\Client
     */
    public function getHttpClient($url)
    {
        if ($this->httpService) {
            return $this->httpService->createClient($url);
        }
        return new \Laminas\Http\Client($url);
    }

    /**
     * Get collapsable XML for an id
     *
     * @param object        $record   Record data
     * @param View\Renderer $renderer View renderer to get techinfo template
     *
     * @return html string
     */
    public function getTechInfo($record = null, $renderer = null)
    {
        if ($record == null) {
            return false;
        }
        $ret = [];
        // OCR
        if (in_array('OCR-DIRTY', $record['datastreams'])) {
            $ret['ocr-dirty'] = htmlentities(
                $this->getDatastreamContent($record['id'], 'OCR-DIRTY')
            );
        }
        // Technical Information
        if (in_array('MASTER-MD', $record['datastreams'])) {
            $record['techinfo'] = $this->getDatastreamContent(
                $record['id'],
                'MASTER-MD'
            );
            $info = $this->getSizeAndTypeInfo($record['techinfo']);
            $ret['size'] = $info['size'];
            $ret['type'] = $info['type'];
        }
        // OCR
        if (in_array('OCR-DIRTY', $record['datastreams'])) {
            $record['ocr-dirty'] = $this->getDatastreamContent(
                $record['id'],
                'OCR-DIRTY'
            );
        }
        if ($renderer != null) {
            $ret['div'] = iconv(
                'UTF-8',
                'ISO-8859-1//TRANSLIT',
                $renderer->render('vudl/techinfo.phtml', ['record' => $record])
            );
        }
        return $ret;
    }

    /**
     * Get size/type information out of the technical metadata.
     *
     * @param string $techInfo Technical metadata
     *
     * @return array
     */
    protected function getSizeAndTypeInfo($techInfo)
    {
        $data = $type = [];
        preg_match('/<size[^>]*>([^<]*)/', $techInfo, $data);
        preg_match('/mimetype="([^"]*)/', $techInfo, $type);
        $size_index = 0;
        if (count($data) > 1) {
            $bytes = intval($data[1]);
            $sizes = ['bytes','KB','MB'];
            while ($size_index < count($sizes) - 1 && $bytes > 1024) {
                $bytes /= 1024;
                $size_index++;
            }
            return [
                'size' => round($bytes, 1) . ' ' . $sizes[$size_index],
                'type' => $type[1],
            ];
        }
        return [];
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
        $check = $this->getDatastreamHeaders($id, 'LICENSE');
        if (!strpos($check[0], '401') && !strpos($check[0], '404')) {
            $xml = $this->getDatastreamContent($id, 'LICENSE');
            preg_match('/xlink:href="(.*?)"/', $xml, $license);
            $license = $license[1] ?? '';
            foreach ($setLicenses as $tell => $value) {
                if (strpos($license, $tell)) {
                    return [$license, $value];
                }
            }
            return [$license, false];
        }
        return null;
    }
}
