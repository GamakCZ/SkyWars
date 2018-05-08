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

namespace skywars\math;

/**
 * Class Time
 * @package skywars\math
 */
class Time {

    /**
     * @param int $time
     * @return string
     */
    public static function calculateTime(int $time): string {
        $min = (int)$time/60;
        if(!is_int($min)) {
            $min = (int)$min;
        }
        $min = strval($min);
        if(strlen($min) == 0) {
            $min = "00";
        }
        elseif(strlen($min) == 1) {
            $min = "0{$min}";
        }
        else {
            $min = strval($min);
        }
        $sec = $time%60;
        if(!is_int($sec)) {
            $sec = (int)$sec;
        }
        $sec = strval($sec);
        if(strlen($sec) == 0) {
            $sec = "00";
        }
        elseif(strlen($sec) == 1) {
            $sec = "0{$sec}";
        }
        else {
            $sec = (string)$sec;
        }
        if($time <= 0) {
            return "00:00";
        }
        return strval($min.":".$sec);
    }
}
