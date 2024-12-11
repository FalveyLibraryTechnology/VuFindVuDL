<?php

/**
 * Datastream proxy service
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2023.
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
 * @package  VuDL
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */

namespace DigLib;

use Laminas\Config\Config;

use function dirname;
use function in_array;
use function strlen;

/**
 * Datastream proxy service
 *
 * @category VuFind
 * @package  VuDL
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class DatastreamProxy
{
    /**
     * VuDL configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Fedora base URL
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Constructor
     *
     * @param Config $vudlConfig VuDL configuration
     */
    public function __construct(Config $vudlConfig)
    {
        $this->config = $vudlConfig;
        $credentials = implode(
            ':',
            [$this->config->Fedora->adminUser, $this->config->Fedora->adminPass]
        );
        $this->baseUrl = str_replace(
            '://',
            "://$credentials@",
            rtrim($this->config->Fedora->api_base, '/')
        );
    }

    /**
     * Get the content type from an array of HTTP response headers.
     *
     * @param string[] $headers
     *
     * @return string
     */
    protected function getContentTypeFromHeaders(array $headers): string
    {
        foreach ($headers as $header) {
            $parts = explode(': ', $header);
            if (strtolower($parts[0]) === 'content-type' && isset($parts[1])) {
                return $parts[1];
            }
        }
        return 'Unknown';
    }

    /**
     * Get a file extension from an HTTP content-type header value.
     *
     * @param string $type Content type value.
     *
     * @return string
     */
    protected function getExtensionFromContentType($type)
    {
        switch (strtolower($type)) {
            case 'application/msword':
                return 'doc';
            case 'application/vnd.ms-excel':
                return 'xls';
            case 'application/vnd.oasis.opendocument.text':
                return 'odt';
            case 'audio/mpeg':
                return 'mp3';
            case 'audio/x-flac':
                return 'flac';
        }
        // Default: take the part after the first slash and before any semicolon
        $parts = explode(';', $type);
        $subParts = explode('/', trim($parts[0]));
        return trim(array_pop($subParts));
    }

    /**
     * Filter and manipulate HTTP headers for the passthruProxy
     *
     * @param array $headers Headers to filter/manipulate.
     *
     * @return array
     */
    protected function processHttpHeaders($headers)
    {
        $contentType = $this->getContentTypeFromHeaders($headers);
        $passableHeaders = ['Last-Modified', 'Content-Type', 'Content-Length'];
        $filtered = [];
        foreach ($headers as $header) {
            $parts = explode(': ', $header);
            $passHeader = true;
            if ($parts[0] === 'Content-Disposition' && isset($parts[1])) {
                // Fedora's default content-disposition is "attachment" but we need
                // to convert this to inline for proper display within UV.
                $value = str_replace('attachment; ', 'inline; ', $parts[1]);
                $extension = $this->getExtensionFromContentType($contentType);
                $value = preg_replace(
                    '/filename="([^"]+)"/',
                    'filename="$1.' . $extension . '"',
                    $value
                );
                $header = $parts[0] . ': ' . $value;
            } elseif (!in_array($parts[0], $passableHeaders)) {
                $passHeader = false;
            }
            if ($passHeader) {
                $filtered[] = $header;
            }
        }
        return $filtered;
    }

    /**
     * Save datastream content and headers to the cache.
     *
     * @param string   $cacheFile  Content cache filename
     * @param resource $handle     File handle pointing at content
     * @param string   $headerFile Header cache filename
     * @param string[] $headers    HTTP headers to cache
     *
     * @return void
     */
    protected function writeStreamToCache(
        string $cacheFile,
        $handle,
        string $headerFile,
        array $headers
    ): void {
        $cacheDir = dirname($cacheFile);
        if (!file_exists(dirname($cacheDir))) {
            mkdir(dirname($cacheDir));
        }
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir);
        }
        $cacheHandle = fopen($cacheFile, 'w');
        stream_copy_to_stream($handle, $cacheHandle);
        file_put_contents($headerFile, implode("\n", $headers));
        fclose($cacheHandle);
    }

    /**
     * Should the provided combination of PID and datastream be cached?
     *
     * @param string $id     PID of object
     * @param string $stream Name of datastream to proxy
     *
     * @return bool
     */
    protected function shouldCacheStream(string $id, string $stream): bool
    {
        $cachedPids = isset($this->config->DatastreamCache->pid)
            ? $this->config->DatastreamCache->pid->toArray() : [];
        $cachedStreams = isset($this->config->DatastreamCache->stream)
            ? $this->config->DatastreamCache->stream->toArray() : [];
        // Only cache data that is on both the PID list AND the stream list.
        return in_array($id, $cachedPids) && in_array($stream, $cachedStreams);
    }

    /**
     * Proxy content by streaming it.
     *
     * @param string $id     PID of object
     * @param string $stream Name of datastream to proxy
     *
     * @return void
     */
    public function passthruProxy(string $id, string $stream): void
    {
        // We don't want to time out while passing data through:
        set_time_limit(0);
        // Turn off output buffering if it is on; otherwise we can run out of memory:
        if (ob_get_level()) {
            ob_end_clean();
        }
        $shouldCache = $this->shouldCacheStream($id, $stream);
        $cacheDir = LOCAL_CACHE_DIR . '/vudl-streams/' . md5("$id|$stream");
        $cacheFile = "$cacheDir/content";
        $headerFile = "$cacheDir/headers";

        // If the datastream is cached, load from local cache to reduce Fedora load:
        if ($shouldCache && file_exists($cacheFile) && file_exists($headerFile)) {
            $handle = fopen($cacheFile, 'r');
            array_map('header', file($headerFile));
        } else {
            $handle = fopen("{$this->baseUrl}/$id/$stream", 'r');
            if (!$handle) {
                $err = error_get_last();
                throw new \Exception($err['message'] ?? 'Could not open file');
            }
            $headers = $this->processHttpHeaders($http_response_header);
            array_map('header', $headers);
            // If this object should be cached but is not yet in the cache, save it now:
            if ($shouldCache) {
                $this->writeStreamToCache($cacheFile, $handle, $headerFile, $headers);
                $handle = fopen($cacheFile, 'r');
            }
        }
        header('Access-Control-Allow-Origin: *');
        fpassthru($handle);
        fclose($handle);
    }

    /**
     * Proxy content by loading it into memory (good for small content like
     * thumbnails).
     *
     * @param string $id           PID of object
     * @param string $stream       Name of datastream to proxy
     * @param string $expectedType Expected content-type prefix
     *
     * @return bool Was expected type found?
     */
    public function inMemoryProxy(string $id, string $stream, string $expectedType = 'image/'): bool
    {
        $content = @file_get_contents("{$this->baseUrl}/$id/$stream");
        $contentType = $this->getContentTypeFromHeaders($http_response_header);
        if (substr($contentType, 0, strlen($expectedType)) == $expectedType) {
            header("Content-Type: $contentType");
            echo $content;
            return true;
        }
        return false;
    }
}
