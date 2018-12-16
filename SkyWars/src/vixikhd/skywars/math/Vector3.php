<?php

/**
 * Copyright 2018 GamakCZ
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace vixikhd\skywars\math;

/**
 * Class Vector3
 * @package skywars\math
 */
class Vector3 extends \pocketmine\math\Vector3 {

    /**
     * @return string
     */
    public function __toString() {
        return "$this->x,$this->y,$this->z";
    }

    /**
     * @param string $string
     * @return Vector3
     */
    public static function fromString(string $string) {
        return new Vector3((int)explode(",", $string)[0], (int)explode(",", $string)[1], (int)explode(",", $string)[2]);
    }
}