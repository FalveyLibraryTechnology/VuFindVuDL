<?php

/**
 * IIIF manifest generator
 *
 * PHP Version 7
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  IIIF
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   David Lacy <david.lacy@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace DigLib\IIIF;

use Laminas\Config\Config;

use function count;
use function in_array;
use function intval;

/**
 * IIIF manifest generator
 *
 * @category VuFind
 * @package  IIIF
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   David Lacy <david.lacy@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ManifestGenerator
{
    public const IIIF_CONTEXT = 'http://iiif.io/api/presentation/3/context.json';

    /**
     * Server URL helper.
     *
     * @var object
     */
    protected $serverHelper;

    /**
     * Route URL helper.
     *
     * @var object
     */
    protected $urlHelper;

    /**
     * VuDL connector.
     *
     * @var object
     */
    protected $connector;

    /**
     * Configuration.
     *
     * @var Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param object $serverHelper Server URL helper
     * @param object $urlHelper    Route URL helper
     * @param object $connector    VuDL connector
     * @param Config $config       Configuration
     */
    public function __construct($serverHelper, $urlHelper, $connector, $config)
    {
        $this->serverHelper = $serverHelper;
        $this->urlHelper = $urlHelper;
        $this->connector = $connector;
        $this->config = $config;
    }

    /**
     * Get VuDL Licenses.
     *
     * @return array
     */
    protected function getLicenses()
    {
        return isset($this->config->Licenses)
            ? $this->config->Licenses->toArray() : [];
    }

    /**
     * Get a URI for a route/params.
     *
     * @param string $route  Route
     * @param array  $params Extra parameters
     *
     * @return string
     */
    protected function getUri($route, $params = [])
    {
        return $this->serverHelper->__invoke(
            $this->urlHelper->fromRoute($route, $params)
        );
    }

    /**
     * Format a single metadata value.
     *
     * @param string $key   Key (used to determine handling of data).
     * @param string $value Input value.
     *
     * @return string
     */
    protected function formatManifestMetadataValue($key, $value)
    {
        switch ($key) {
            case 'first_indexed':
                return ($date = date_create($value))
                    ? htmlspecialchars($date->format('j F Y')) : '';
            case 'series':
            case 'topic':
                $parts = explode(' -- ', $value);
                $filter = '';
                $retVal = '';
                foreach ($parts as $i => $p) {
                    $filter .= ' ' . $p;
                    if ($i > 0) {
                        $retVal .= ' &gt; ';
                    }
                    $retVal .= '<a href="'
                        . $this->getUri('search-results')
                        . '?type=' . urlencode($key == 'topic' ? 'Subject' : 'Series')
                        . '&lookfor=' . urlencode('"' . trim($filter) . '"')
                        . '">' . htmlspecialchars($p) . '</a>';
                }
                return $retVal;
            case 'title':
                return htmlspecialchars($value);
            case 'author':
            case 'dc_contributor_str_mv':
                $query = 'author_facet:"' . $value . '"';
                return '<a href="' . $this->getUri('search-results')
                    . '?lookfor=' . urlencode($query) . '">' . htmlspecialchars($value)
                    . '</a>';
            default:
                return '<a href="'
                    . $this->getUri('search-results')
                    . '?filter[]=' . urlencode($key) . ':' . urlencode($value)
                    . '">' . htmlspecialchars($value) . '</a>';
        }
    }

    /**
     * Format a set of metadata values.
     *
     * @param string $key   Key (used to determine handling of data).
     * @param array  $value Input value.
     *
     * @return string
     */
    protected function formatManifestMetadataValues($key, $value)
    {
        $parts = [];
        foreach ($value as $current) {
            $parts[] = $this->formatManifestMetadataValue($key, $current);
        }
        return '<span>' . implode('<br />', $parts) . '</span>';
    }

    /**
     * Extract metadata for use in manifest JSON.
     *
     * @param string $id      Record ID
     * @param array  $details Raw details
     *
     * @return array
     */
    protected function extractManifestMetadata($id, $details)
    {
        $skip = ['description'];
        $retVal = [];
        foreach ($details as $key => $current) {
            if (!in_array($key, $skip)) {
                $value = (array)$current['value'];
                $formatValues = $this->formatManifestMetadataValues($key, $value);
                $retVal[$key] = [
                    'label' => ['en' => [$current['title']]],
                    'value' => ['en' => [$formatValues]],
                ];
            }
        }
        // Push title to the top:
        $sortedRetVal = [];
        if (isset($retVal['title'])) {
            $sortedRetVal[] = $retVal['title'];
            unset($retVal['title']);
        }
        foreach ($retVal as $current) {
            $sortedRetVal[] = $current;
        }
        // Add some useful links to the bottom:
        $recordUrl = $this->getUri('record', ['id' => $id]);
        $persistUrl = $this->getUri('vudl-record', ['id' => $id]);
        $value = '<span>'
                . '<a href="' . htmlspecialchars($recordUrl)
                . '">More Details</a><br />'
                . '<a href="' . htmlspecialchars($persistUrl)
                . '">Permanent Link</a>'
                . '</span>';
        return [
            'label' => ['en' => ['About']],
            'value' => ['en' => [$value]],
        ];
    }

    /**
     * Get image dimensions and large URL for use in getSingleCanvas.
     *
     * @param array  $raw             Raw data about the image
     * @param string $imageServerBase Base address for image server
     *
     * @return array [width, height, large URL]
     */
    protected function getImageDetailsForCanvas($raw, $imageServerBase)
    {
        // Default dimensions to use if others are missing....
        static $defaultWidth = 0;
        static $defaultHeight = 0;

        $imageUrl = $this->getUri('files', ['type' => 'LARGE', 'id' => $raw['id']]);

        // Try to load dimensions from master metadata; fail back to large
        // image only if data is missing.
        $width = isset($raw['width_str'])
            ? intval($raw['width_str']) : false;
        $height = isset($raw['height_str'])
            ? intval($raw['height_str']) : false;
        if (!($width && $height) && in_array('MASTER-MD', $raw['datastreams'])) {
            $metadataUrl = $this->getUri(
                'files',
                ['type' => 'MASTER-MD', 'id' => $raw['id']]
            );
            $xml = @simplexml_load_file($metadataUrl);
            if ($xml) {
                $width = isset($xml->metadata->image->imageWidth)
                    ? intval($xml->metadata->image->imageWidth) : false;
                $height = isset($xml->metadata->image->imageHeight)
                    ? intval($xml->metadata->image->imageHeight) : false;
            } else {
                error_log("Problem parsing MASTER-MD XML from $metadataUrl");
                $width = $height = false;
            }
        }
        if (empty($width) || empty($height)) {
            // In theory, if we got here, it means dimension data was missing
            // from both the Solr index and the MASTER-MD stream. We will grab
            // dimensions from the image server for the first image we encounter
            // and then reuse them in future. This may result in some aspect ratio
            // distortion in thumbnail display, but since this scenario should never
            // happen in theory (though it does in practice occasionally due to bad
            // or missing data) it seems like a fair trade-off between speed and
            // accuracy.
            if (empty($defaultWidth) || empty($defaultHeight)) {
                $data = json_decode(
                    @file_get_contents(
                        $imageServerBase . urlencode($raw['id']) . '/info.json'
                    )
                );
                if (isset($data->width) && isset($data->height)) {
                    $defaultWidth = $data->width;
                    $defaultHeight = $data->height;
                }
            }
            $width = $defaultWidth;
            $height = $defaultHeight;
        }

        return [$width, $height, $imageUrl, 'image/jpeg'];
    }

    /**
     * Build JSON data for a non-image canvas.
     *
     * @param string $id      Record ID
     * @param int    $i       Position of canvas in overall array (used for canvas ID generation)
     * @param array  $raw     Raw data to format into canvas
     * @param string $type    Type of list ('image' or 'audio')
     * @param array  $list    List to check
     * @param array  $outline Outline data
     *
     * @return array
     */
    protected function getNonImageCanvas($id, $i, $raw, $type, $list, $outline)
    {
        $canvasUrl = $this->getUri(
            'vudl-record-canvas',
            ['id' => $id, 'canvas' => 'p' . $i]
        );

        if ($type == 'audio') {
            $preferredType = 'Sound';
            $preferredRendering = 'audio/mp3';
        } elseif ($type == 'video') {
            $preferredType = 'MovingImage';
            $preferredRendering = 'video/mp4';
        } else {
            // Format as a generic download:
            $preferredType = 'foaf:Document';
            $preferredRendering = 'application/pdf';
        }
        $url = $this->getUri(
            'files',
            ['type' => 'MASTER', 'id' => $raw['id']]
        );
        $description = isset($raw['sizebytes_str'])
            ? ($raw['sizebytes_str'] / 1024) . 'k'
            : '';
        $content = [
            'height' => 600,
            'width' => 800,
            'items' => [
                [
                    'id' => $canvasUrl . '#content',
                    'type' => 'AnnotationPage',
                    'items' => [
                        [
                            'id' => $canvasUrl . '#item' . $i,
                            'type' => 'Annotation',
                            'motivation' => 'painting',
                            'body' => [
                                'id' => $url,
                                'type' => $preferredType,
                                'format' => $preferredRendering ?? '',
                                'label' => ['en' => [$raw['label']]],
                                'description' => $description,
                            ],
                            'target' => $canvasUrl,
                            'thumbnail' => $this->getThumbnail($raw, $type),
                        ],
                    ],
                ],
            ],
        ];

        return [
            'type' => 'Canvas',
            'id' => $canvasUrl,
            'label' => ['en' => [$raw['label'] ]] ?? '-',
            'rendering' => $this->getSequenceRenderingData($outline, $list, $raw),
            'thumbnail' => $this->getThumbnail($raw, $type),
        ] + $content;
    }

    /**
     * Build JSON data for an image canvas.
     *
     * @param string $id      Record ID
     * @param int    $i       Position of canvas in overall array (used for canvas ID generation)
     * @param array  $raw     Raw data to format into canvas
     * @param string $type    Type of list ('image' or 'audio')
     * @param array  $list    List to check
     * @param array  $outline Outline data
     *
     * @return array
     */
    protected function getImageCanvas($id, $i, $raw, $type, $list, $outline)
    {
        $canvasUrl = $this->getUri(
            'vudl-record-canvas',
            ['id' => $id, 'canvas' => 'p' . $i]
        );

        $imageServerBase = $this->config->Images->serverUrl ?? false;
        if (!$imageServerBase) {
            throw new \Exception('Must set image server base URL.');
        }
        [$width, $height, $imageUrl, $mimeType]
            = $this->getImageDetailsForCanvas($raw, $imageServerBase);
        $content = [
            'height' => $height,
            'width' => $width,
            'items' => [
                [
                    'id' => $this->getUri('record', ['id' => $id]),
                    'type' => 'AnnotationPage',
                    'items' => [
                        [
                            'id' => $imageUrl,
                            'type' => 'Annotation',
                            'motivation' => 'painting',
                            'body' => [
                                'id' => $imageServerBase . urlencode($raw['id']),
                                'type' => 'Image',
                                'format' => $mimeType,
                                'service' => [
                                    [
                                    'id' => $imageServerBase . urlencode($raw['id']),
                                    'type' => 'ImageService3',
                                    'profile' => 'level1',
                                    ],
                                ],
                            ],
                            'height' => $height,
                            'width' => $width,
                            'target' => $canvasUrl,
                            'thumbnail' => $this->getThumbnail($raw, $type),
                        ],
                    ],
                ],
            ],
        ];

        return [
            'type' => 'Canvas',
            'id' => $canvasUrl,
            'label' => ['en' => [$raw['label'] ]] ?? '-',
            'rendering' => $this->getSequenceRenderingData($outline, $list, $raw),
            'thumbnail' => $this->getThumbnail($raw, $type),
        ] + $content;
    }

    /**
     * Find and arrange thumbnail data for canvas.
     *
     * @param string $raw  Raw data to format into canvas
     * @param string $type Type of list ('image' or 'audio')
     *
     * @return array
     */
    protected function getThumbnail($raw, $type)
    {
        if ($type == 'audio') {
            $elementType = 'Sound';
            $preferredRendering = 'audio/mp3';
        } elseif ($type == 'video') {
            $elementType = 'dctypes:MovingImage';
            $preferredRendering = 'video/mp4';
        } else {
            $elementType = 'foaf:Document';
            $preferredRendering = 'application/pdf';
        }
        $renderings = $this->getCanvasRenderingData($raw);
        $foundMatch = false;
        foreach ($renderings as $i => $current) {
            if ($current['format'] === $preferredRendering) {
                $foundMatch = true;
                break;
            }
        }
        $bestRendering = $foundMatch ? $i : 0;
        $id = $renderings[$bestRendering]['id'] . '#element';
        $format = $renderings[$bestRendering]['format'];

        if (in_array('THUMBNAIL', $raw['datastreams'])) {
            $getThumb = $this->getUri(
                'files',
                ['type' => 'THUMBNAIL', 'id' => $raw['id']]
            );
        } else {
            if ($type == 'audio') {
                $thumbFilename = 'audio.png';
            } elseif ($format == 'application/pdf') {
                $thumbFilename = 'pdf.png';
            } else {
                $thumbFilename = 'default.png';
            }
            $getThumb = $this->getUri('home')
                . 'themes/vudiglib/images/vudl/' . $thumbFilename;
        }

        return [
            [
                'id' => $getThumb,
                'type' => 'Image',
                'format' => 'image/png',
            ],
        ];
    }

    /**
     * Get the list type
     *
     * @param array $list List to check
     *
     * @return string
     */
    protected function getListType($list)
    {
        if ($this->isImageList($list)) {
            return 'image';
        } elseif ($this->isAudioList($list)) {
            return 'audio';
        } elseif ($this->isVideoList($list)) {
            return 'video';
        } elseif ($this->isPdfList($list)) {
            return 'pdf';
        } else {
            return 'unknown';
        }
    }

    /**
     * Is this an image list?
     *
     * @param array $list List to check
     *
     * @return bool
     */
    protected function isImageList($list)
    {
        return in_array('LARGE', $list[0]['datastreams'] ?? []);
    }

    /**
     * Is this an audio list?
     *
     * @param array $list List to check
     *
     * @return bool
     */
    protected function isAudioList($list)
    {
        foreach ($list as $current) {
            if (in_array('MP3', $current['datastreams'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is this a video list?
     *
     * @param array $list List to check
     *
     * @return bool
     */
    protected function isVideoList($list)
    {
        foreach ($list as $current) {
            if (
                in_array('MP4', $current['datastreams'])
                || in_array('video/mp4', $current['mimetypes'])
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Given a list containing audio, sort audio from non-audio content.
     *
     * @param array $list List to filter
     *
     * @return array
     */
    protected function filterAudioList($list)
    {
        $results = ['audio' => [], 'other' => []];
        foreach ($list as $current) {
            $key = $this->isAudioList([$current]) ? 'audio' : 'other';
            $results[$key][] = $current;
        }
        return $results;
    }

    /**
     * Is this a PDF list?
     *
     * @param array $list List to check
     *
     * @return bool
     */
    protected function isPdfList($list)
    {
        foreach ($list as $current) {
            if (in_array('application/pdf', $current['mimetypes'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Given a list containing PDFs, sort PDF from non-PDF content.
     *
     * @param array $list List to filter
     *
     * @return array
     */
    protected function filterPdfList($list)
    {
        $results = ['pdf' => [], 'other' => []];
        foreach ($list as $current) {
            $key = $this->isPdfList([$current]) ? 'pdf' : 'other';
            $results[$key][] = $current;
        }
        return $results;
    }

    /**
     * Extract a list of pages for use in canvas building (for now, we ignore
     * non-image lists).
     *
     * @param array $outline Outline data
     *
     * @return array
     */
    protected function getListForCanvas($outline)
    {
        // First try to find images...
        foreach ($outline['lists'] as $list) {
            if ($this->isImageList($list)) {
                return $list;
            }
        }
        // Next try audio...
        foreach ($outline['lists'] as $list) {
            if ($this->isAudioList($list)) {
                $filtered = $this->filterAudioList($list);
                return $filtered['audio'];
            }
        }
        // Finally resort to PDF...
        foreach ($outline['lists'] as $list) {
            if ($this->isPdfList($list)) {
                $filtered = $this->filterPdfList($list);
                return $filtered['pdf'];
            }
        }
        // If nothing specific was found, just grab the first available list.
        // Use an empty array if there's nothing at all.
        return current($outline['lists']) ?: [];
    }

    /**
     * Get the array representing a single rendering.
     *
     * @param string $url   URL of rendering
     * @param string $mime  MIME type
     * @param string $label Label for rendering
     *
     * @return array
     */
    protected function getSingleRendering($url, $mime, $label = '')
    {
        return [
            'type' => 'rendering',
            'id' => $url,
            'format' => $mime,
            'label' => ['en' => [empty($label) ? "Download as {$mime}" : $label]],
        ];
    }

    /**
     * Construct a list of alternate renderings of the canvas.
     * Used for (for example) OCR and original images.
     *
     * @param array $raw Raw data.
     *
     * @return array
     */
    protected function getCanvasRenderingData($raw)
    {
        $data = [];
        $masterIndex = array_search('MASTER', $raw['datastreams']);
        if ($masterIndex !== false) {
            $masterUrl = $this->getUri(
                'files',
                ['type' => 'MASTER', 'id' => $raw['id']]
            );
            $mimeType = $raw['mimetypes'][$masterIndex];
            $label = 'Original source file';
            if (isset($raw['sizebytes_str'])) {
                $kb = $raw['sizebytes_str'] / 1024;
                if ($kb < 1024) {
                    $label .= ' - ' . number_format($kb, 2) . ' KB';
                } else {
                    $mb = $kb / 1024;
                    $label .= ' - ' . number_format($mb, 2) . ' MB';
                }
            }
            $data[] = $this->getSingleRendering($masterUrl, $mimeType, $label);
        }
        $formatsToCheck = [
            'OCR-DIRTY' => ['text/plain', 'Raw OCR Data'],
            'MP3' => ['audio/mp3', 'MP3 Audio'],
            'MP4' => ['video/mp4', 'MP4 Video'],
            'OGG' => ['audio/ogg', 'OGG Audio'],
            'MASTER-MD' => ['application/xml', 'Technical Metadata'],
            'TEXT-TRANSCRIPT' => ['text/plain', 'Text Transcript'],
            'VTT' => ['text/vtt', 'VTT Transcript'],
        ];
        foreach ($formatsToCheck as $format => $details) {
            if (in_array($format, $raw['datastreams'])) {
                $url = $this->getUri(
                    'files',
                    ['type' => $format, 'id' => $raw['id']]
                );
                $data[] = $this->getSingleRendering($url, $details[0], $details[1]);
            }
        }
        return $data;
    }

    /**
     * Extract MASTER rendering data from a list of objects.
     *
     * @param array $list List of objects
     *
     * @return array
     */
    protected function getMasterRenderings($list)
    {
        $renderings = [];
        foreach ($list as $current) {
            $masterIndex = array_search('MASTER', $current['datastreams']);
            if ($masterIndex !== false) {
                $mimeType = $current['mimetypes'][$masterIndex];
                $masterUrl = $this->getUri(
                    'files',
                    ['type' => 'MASTER', 'id' => $current['id']]
                );
            } else {
                $masterUrl = $mimeType = false;
            }
            if ($mimeType && $masterUrl) {
                $renderings[] = $this->getSingleRendering(
                    $masterUrl,
                    $mimeType,
                    $current['label']
                );
            }
        }
        return $renderings;
    }

    /**
     * Construct a list of alternate renderings of the full sequence of pages.
     * Used for (for example) PDF versions of documents.
     *
     * @param array $outline    Outline data.
     * @param array $canvasList List chosen as primary canvas list for manifest
     * @param array $raw        Raw data.
     *
     * @return array
     */
    protected function getSequenceRenderingData($outline, $canvasList, $raw)
    {
        $renderings = [];
        foreach ($outline['lists'] as $list) {
            if ($this->isAudioList($list) && $this->isAudioList($canvasList)) {
                $filtered = $this->filterAudioList($list);
                if (count($filtered['other']) > 0) {
                    $renderings = array_merge(
                        $renderings,
                        $this->getMasterRenderings($filtered['other'])
                    );
                }
            } elseif ($this->isPdfList($list) && $this->isPdfList($canvasList)) {
                $filtered = $this->filterPdfList($list);
                if (count($filtered['other']) > 0) {
                    $renderings = array_merge(
                        $renderings,
                        $this->getMasterRenderings($filtered['other'])
                    );
                }
            } elseif (!$this->isImageList($list)) {
                $renderings = array_merge(
                    $renderings,
                    $this->getMasterRenderings($list)
                );
            }
        }
        $canvasRenderings = $this->getCanvasRenderingData($raw);
        $retVal = array_merge($canvasRenderings, $renderings);
        return $retVal;
    }

    /**
     * Get fake placeholder canvas.
     *
     * @param string $id Record ID.
     *
     * @return array
     */
    protected function getPlaceholderCanvas($id)
    {
        $canvasUrl = $this->getUri(
            'vudl-record-canvas',
            ['id' => $id, 'canvas' => 'Placeholder']
        );
        $placeholderImage = 'http://digital.library.villanova.edu/placeholder.jpg';
        return [
            'type' => 'Canvas',
            'id' => $canvasUrl,
            'label' => ['en' => ['Placeholder image']],
            'height' => 600,
            'width' => 600,
            'images' => [
                [
                    'type' => 'AnnotationPage',
                    'motivation' => 'painting',
                    'items' => [
                        'id' => $placeholderImage,
                        'type' => 'dcTypes:Image',
                        'height' => 600,
                        'width' => 600,
                    ],
                    'on' => $canvasUrl,
                ],
            ],
        ];
    }

    /**
     * Get fake sequence data for IIIF viewers that do not understand IXIF data.
     * (Used for non-image sequences -- audio/video/PDF/etc.)
     *
     * @param string $id Record ID.
     *
     * @return array
     */
    protected function getPlaceholderSequence($id)
    {
        $label = 'Unsupported extension. This manifest is being used as a wrapper '
            . 'for non-IIIF content (e.g., audio, video) and is unfortunately '
            . 'incompatible with IIIF viewers.';
        return [
            'type' => 'item',
            'label' => ['en' => [$label]],
            'compatibilityHint' => 'displayIfContentUnsupported',
            'canvases' => [ $this->getPlaceholderCanvas($id) ],
        ];
    }

    /**
     * Build JSON items data.
     *
     * @param string $id      Record ID
     * @param array  $outline Outline data
     *
     * @return array
     */
    protected function getItems($id, $outline)
    {
        $list = $this->getListForCanvas($outline);
        $type = $this->getListType($list);

        $items = [];
        foreach ($list as $i => $current) {
            if ($type === 'image') {
                $items[] = $this->getImageCanvas(
                    $id,
                    $i,
                    $current,
                    $type,
                    $list,
                    $outline
                );
            } else {
                $items[] = $this->getNonImageCanvas(
                    $id,
                    $i,
                    $current,
                    $type,
                    $list,
                    $outline
                );
            }
        }

        return $items;
    }

    /**
     * Get the context to apply to the manifest.
     *
     * @param array $outline Outline data
     *
     * @return string
     */
    protected function getManifestContext($outline)
    {
        $list = $this->getListForCanvas($outline);
        // Non-image lists need to load the additional IXIF context.
        if ($this->isImageList($list)) {
            return self::IIIF_CONTEXT;
        } else {
            return [
                self::IIIF_CONTEXT, 'http://wellcomelibrary.org/ixif/0/context.json',
            ];
        }
    }

    /**
     * Get "related link" information pointing to record view page.
     *
     * @param string $id Record ID
     *
     * @return array
     */
    protected function getRelated($id)
    {
        return [
            'id' => $this->getUri('vudl-record', compact('id')),
            'type' => 'Text',
            'format' => 'text/html',
            'label' => ['en' => ['More Details']],
        ];
    }

    /**
     * Get HTML for standard disclaimers.
     *
     * @return string
     */
    protected function getDisclaimers()
    {
        $copyright = 'https://digital.library.villanova.edu/copyright.html';
        return '<a href="' . $copyright . '#liability">Disclaimer of Liability</a>'
            . '<br /><a href="' . $copyright
            . '#endorsement">Disclaimer of Endorsement</a>';
    }

    /**
     * Get the license statement to display in the viewer.
     *
     * @param string $license URI of license
     *
     * @return string
     */
    protected function getRequiredStatement($license)
    {
        $imageBase = $this->getUri('home') . 'themes/vudiglib/images/license';
        $licenses = [
            'http://creativecommons.org/licenses/by/4.0/' => [
                'alt' =>
                    'Creative Commons Attribution 4.0 International (CC BY 4.0)',
                'icon' => $imageBase . '/by.png',
            ],
            'http://creativecommons.org/licenses/by-nc/4.0/' => [
                'alt' => 'Creative Commons Attribution-NonCommercial 4.0 '
                    . 'International (CC BY-NC 4.0)',
                'icon' => $imageBase . '/by-nc.png',
            ],
            'http://creativecommons.org/licenses/by-nc-nd/4.0/' => [
                'alt' => 'Creative Commons Attribution-NonCommercial-NoDerivs 4.0 '
                    . 'International (CC BY-NC-ND 4.0)',
                'icon' => $imageBase . '/by-nc-nd.png',
            ],
            'http://creativecommons.org/licenses/by-nd/4.0/' => [
                'alt' => 'Creative Commons Attribution-NoDerivs 4.0 International '
                    . '(CC BY-ND 4.0)',
                'icon' => $imageBase . '/by-nd.png',
            ],
            'http://creativecommons.org/licenses/by-nc-sa/4.0/' => [
                'alt' => 'Creative Commons Attribution-NonCommercial-ShareAlike 4.0 '
                    . 'International (CC BY-NC-SA 4.0)',
                'icon' => $imageBase . '/by-nc-sa.png',
            ],
            'http://creativecommons.org/licenses/by-sa/4.0/' => [
                'alt' => 'Creative Commons Attribution-ShareAlike 4.0 '
                    . 'International (CC BY-SA 4.0)',
                'icon' => $imageBase . '/by-sa.png',
            ],
            'https://creativecommons.org/publicdomain/mark/1.0/' => [
                'alt' => 'CC0 1.0 Universal (CC0 1.0)',
                'icon' => $imageBase . '/pdm.png',
            ],
            'https://creativecommons.org/publicdomain/zero/1.0/' => [
                'alt' => 'CC0 1.0 Universal (CC0 1.0)',
                'icon' => $imageBase . '/cc-zero.png',
            ],
            'http://digital.library.villanova.edu/copyright.html' => [
                'text' => 'Copyright',
            ],
            'http://digital.library.villanova.edu/copyright.html#passthrough' => [
                'text' => 'Copyright',
            ],
            'https://digital.library.villanova.edu/rights.html' => [
                'text' => 'Rights Information',
            ],
        ];
        $licenseData = $licenses[$license] ?? ['text' => $license];
        $linkContent = isset($licenseData['icon'])
            ? '<img src="' . htmlspecialchars($licenseData['icon'])
            . '" alt="' . htmlspecialchars($licenseData['alt']) . '">'
            : htmlspecialchars($licenseData['text']);
        $value = '<span>Digital Library@Villanova University'
            . '<br /><br /><b>Disclaimers</b>: <br />'
            . $this->getDisclaimers() . '<br /><br />'
            . '<b>License</b>: <br />'
            . '<a href="' . htmlspecialchars($license) . '">' . $linkContent . '</a>'
            . '</span>';
        return [
            'label' => ['en' => ['ATTRIBUTION']],
            'value' => ['en' => [$value]],
        ];
    }

    /**
     * Get data to build an IIIF manifest.
     *
     * @param string $id      Record ID
     * @param array  $outline Outline data
     * @param array  $parents Collection ID(s) containing this manifest
     *
     * @return array
     */
    public function getManifestData($id, $outline, $parents = [])
    {
        $uri = $this->getUri('vudl-record-manifest', ['id' => $id]);
        $details = $this->connector->getDetails($id, true);
        [$license] = $this->connector->getCopyright($id, $this->getLicenses());
        return [
            '@context' => $this->getManifestContext($outline),
            'type' => 'Manifest',
            'id' => $uri,
            'label' =>  [
                'en' => [
                    $details['title']['value'] ?? 'Untitled',
                ],
            ],
            'metadata' => $this->extractManifestMetadata($id, $details),
            'summary' => ['en' => [
                isset($details['description']['value'])
                ? '<p>' . str_replace(
                    ['<div', '</div'],
                    ['<p', '</p'],
                    $details['description']['value']
                ) . '</p>'
                : '',
                ],
            ],
            'requiredStatement' => $this->getRequiredStatement($license),
            'seeAlso' => [$this->getRelated($id)],
            'items' => $this->getItems($id, $outline),
        ];
    }
}
