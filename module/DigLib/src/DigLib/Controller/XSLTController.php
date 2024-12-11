<?php

/**
 * Exposed XSLT Controller Module
 *
 * PHP version 5
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
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */

namespace DigLib\Controller;

use function count;
use function func_get_args;
use function is_callable;

/**
 * Exposed XSLT Controller Module
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class XSLTController extends \VuFind\Controller\AbstractBase
{
    // define some status constants
    public const STATUS_OK = 'OK';                  // good
    public const STATUS_ERROR = 'ERROR';            // bad
    public const STATUS_NEED_AUTH = 'NEED_AUTH';    // must login first

    /**
     * Type of output to use
     *
     * @var string
     */
    protected $outputMode;

    /**
     * Array of PHP errors captured during execution
     *
     * @var array
     */
    protected static $php_errors = [];

    /**
     * Map of callable functions
     *
     * @var array
     */
    protected $functionMaps = [
        'getFirstIndexed' => ['core', 'id', 'date'],
        'getLastIndexed' => ['core', 'id', 'date'],
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Add notices to a key in the output
        set_error_handler([self::class, 'storeError']);
        parent::__construct(...func_get_args());
    }

    /**
     * Send output data and exit.
     *
     * @param mixed  $data The response data
     * @param string $tag  Top-level key of the response array
     *
     * @return \Laminas\Http\Response
     */
    protected function output($data, $tag)
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine(
            'Content-type',
            'application/json'
        );
        $headers->addHeaderLine(
            'Cache-Control',
            'no-cache, must-revalidate'
        );
        $headers->addHeaderLine(
            'Expires',
            'Mon, 26 Jul 1997 05:00:00 GMT'
        );
        $output = $data;
        if ('development' == APPLICATION_ENV && count(self::$php_errors) > 0) {
            $output = self::$php_errors;
        }
        $response->setContent(json_encode([$tag => $output]));
        return $response;
    }

    /**
     * Store the errors for later, to be added to the output
     *
     * @param string $errno   Error code number
     * @param string $errstr  Error message
     * @param string $errfile File where error occured
     * @param string $errline Line number of error
     *
     * @return bool           Always true to cancel default error handling
     */
    public static function storeError($errno, $errstr, $errfile, $errline)
    {
        self::$php_errors[] = "ERROR [$errno] - " . $errstr . "<br />\n"
            . ' Occurred in ' . $errfile . ' on line ' . $errline . '.';
        return true;
    }

    /**
     * Call a method and format the response from the results.
     *
     * @param string $method Method name
     * @param array  $params Method arguments
     *
     * @return array
     */
    protected function getMethodResponse($method, $params)
    {
        if (!isset($this->functionMaps[$method])) {
            return ['error' => $this->translate('Invalid function')];
        }
        $ordered_params = [];
        foreach ($this->functionMaps[$method] as $p) {
            if (!isset($params[$p])) {
                return ['error' => "Missing parameter $p", 'error'];
            }
            $ordered_params[] = $params[$p];
        }
        $class = \VuFind\XSLT\Import\VuFind::class;
        $callback = [$class, $method];
        if (is_callable($callback)) {
            try {
                if (method_exists($class, 'setServiceLocator')) {
                    $class::setServiceLocator($this->serviceLocator);
                }
                return $callback(...$ordered_params);
            } catch (\Exception $e) {
                $debugMsg = ('development' == APPLICATION_ENV)
                    ? ': ' . $e->getMessage() : '';
                return [
                    'error' => $this->translate('An error has occurred') . $debugMsg,
                ];
            }
        } else {
            return ['error' => $this->translate('Invalid Method')];
        }
    }

    /**
     * Home action
     *
     * @return mixed
     */
    public function homeAction()
    {
        // Set the output mode to JSON:
        $this->outputMode = 'text';
        $params = $this->params()->fromQuery();
        $methods = (array)$this->params()->fromQuery('method');
        if (empty($methods)) {
            return $this->output(
                $this->translate('Invalid data - no method'),
                'error'
            );
        }
        unset($params['method']);
        $output = [];
        foreach ($methods as $method) {
            $output[$method] = $this->getMethodResponse($method, $params);
        }
        return $this->output($output, 'results');
    }
}
