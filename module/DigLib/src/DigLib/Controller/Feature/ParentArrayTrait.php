<?php

/**
 * "Get parent array" trait for sharing logic between record/collection controllers.
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace DigLib\Controller\Feature;

/**
 * "Get parent array" trait for sharing logic between record/collection controllers.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait ParentArrayTrait
{
    /**
     * Get details on parent records.
     *
     * @return array
     */
    protected function getParentArray()
    {
        $connector = $this->serviceLocator->get(\DigLib\Connection\Manager::class);
        $parents = $connector->getParentList($this->driver->getUniqueId());
        return $parents;
    }
}
