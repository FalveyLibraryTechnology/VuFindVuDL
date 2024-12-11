<?php

/**
 * VuDL Abstract Base Test Class
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

/**
 * VuDL Abstract Base Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class AbstractBaseTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Make sure we can construct an object and pass it an HTTP service;
     * check that the default page length is returned.
     *
     * @return void
     */
    public function testConstructorAndSetAndDefaultPageLength(): void
    {
        $subject = new \DigLib\Connection\AbstractBase([]);
        $mockHttp = $this->getMockBuilder(\VuFindHttp\HttpService::class)
            ->disableOriginalConstructor()->getMock();
        $subject->setHttpService($mockHttp);
        $this->assertEquals(16, $subject->getPageLength());
    }
}
