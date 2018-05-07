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

namespace skywars\arena;

use pocketmine\event\Listener;
use skywars\SkyWars;

/**
 * Class Arena
 * @package skywars\arena
 */
class Arena implements Listener {

    /** @var SkyWars $plugin */
    public $plugin;

    /** @var array $data */
    public $data = [];

    /** @var bool $setting */
    public $setup = false;

    /**
     * Arena constructor.
     * @param SkyWars $plugin
     * @param array $arenaFileData
     */
    public function __construct(SkyWars $plugin, array $arenaFileData) {
        $this->plugin = $plugin;
        $this->setup = empty($arenaFileData);
        $plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);

        if($this->setup) $this->createBasicData();
    }

    private function createBasicData() {
        $this->data = [
            "level" => null,
            "slots" => 12,
            "spawns" => [],
            ""
        ];
    }
}