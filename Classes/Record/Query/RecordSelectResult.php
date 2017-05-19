<?php
namespace In2code\In2publishCore\Record\Query;

/***************************************************************
 * Copyright notice
 *
 * (c) 2017 in2code.de and the following authors:
 * Oliver Eglseder <oliver.eglseder@in2code.de>
 *
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Class RecordSelectResult
 */
class RecordSelectResult implements \IteratorAggregate
{
    /**
     * @var array
     */
    protected $properties = [];

    /**
     * RecordSelectResult constructor.
     *
     * @param array $localProperties
     * @param array $foreignProperties
     * @throws \Exception
     */
    public function __construct(array $localProperties, array $foreignProperties)
    {
        foreach ($localProperties as $localPropertyArray) {
            if (isset($localPropertyArray['uid'])) {
                $this->properties[$localPropertyArray['uid']]['local'] = $localPropertyArray;
                if (!isset($this->properties[$localPropertyArray['uid']]['foreign'])) {
                    $this->properties[$localPropertyArray['uid']]['foreign'] = [];
                }
            } else {
                // TODO
                throw new \Exception('Missing implementation: UID not found in local properties array', 1495181306);
            }
        }
        foreach ($foreignProperties as $foreignPropertyArray) {
            if (isset($foreignPropertyArray['uid'])) {
                $this->properties[$foreignPropertyArray['uid']]['foreign'] = $foreignPropertyArray;
                if (!isset($this->properties[$foreignPropertyArray['uid']]['local'])) {
                    $this->properties[$foreignPropertyArray['uid']]['local'] = [];
                }
            } else {
                // TODO
                throw new \Exception('Missing implementation: UID not found in foreign properties array', 1495181315);
            }
        }
    }

    /**
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->properties);
    }
}
