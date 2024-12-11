<?php

/**
 * VuDL Fedora Connector Test Class
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */

namespace DigLibTest;

use function get_class;

/**
 * VuDL Fedora Connector Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class FedoraTest extends \PHPUnit\Framework\TestCase
{
    public function testAllWithMock()
    {
        $subject = $this->getMockBuilder(\DigLib\Connection\Fedora::class)
            ->onlyMethods(['getDatastreamContent', 'getDatastreamHeaders'])
            ->setConstructorArgs(
                [(object)[
                    'Fedora' => (object)[
                        'api_base' => 'http://jsontest.com/',
                        'adminUser' => 'ADMIN',
                        'adminPass' => 'ADMINPASS',
                    ],
                ]]
            )->getMock();
        $subject->method('getDatastreamContent')->will(
            $this->onConsecutiveCalls(
                'xlink:href="test_passed"',
                '<dc:title>T</dc:title><dc:id>ID</dc:id>'
            )
        );
        $subject->method('getDatastreamHeaders')->will(
            $this->onConsecutiveCalls(
                ['HTTP/1.1 200 OK'],
                ['HTTP/1.1 404 EVERYTHING IS WRONG']
            )
        );

        $this->assertEquals('http://jsontest.com/', $subject->getBase());

        $this->assertEquals(['test_passed', 'fake'], $subject->getCopyright('id', ['passed' => 'fake']));

        $this->assertEquals(['title' => 'T','id' => 'ID'], $subject->getDetails('id'));
        // Detail formatting tested in Solr

        $this->assertEquals(\Laminas\Http\Client::class, get_class($subject->getHttpClient('url')));
    }
}
