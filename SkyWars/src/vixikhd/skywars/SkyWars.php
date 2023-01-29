<?php

/**
 * Copyright 2018-2020 GamakCZ
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

namespace vixikhd\skywars;

use pocketmine\command\Command;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\world\World;
use pocketmine\plugin\PluginBase;
use vixikhd\skywars\arena\Arena;
use vixikhd\skywars\arena\MapReset;
use vixikhd\skywars\commands\SkyWarsCommand;
use vixikhd\skywars\math\Vector3;
use vixikhd\skywars\provider\YamlDataProvider;

/**
 * Class SkyWars
 * @package skywars
 */
class SkyWars extends PluginBase implements Listener {

    /** @var YamlDataProvider */
    public $dataProvider;

    /** @var Command[] $commands */
    public $commands = [];

    /** @var Arena[] $arenas */
    public $arenas = [];

    /** @var Arena[] $setters */
    public $setters = [];

    /** @var int[] $setupData */
    public $setupData = [];

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->dataProvider = new YamlDataProvider($this);
        $this->getServer()->getCommandMap()->register("SkyWars", $this->commands[] = new SkyWarsCommand($this));
    }

    public function onDisable(): void {
        $this->dataProvider->saveArenas();
    }

    /**
     * @param PlayerChatEvent $event
     */
    public function onChat(PlayerChatEvent $event) {
        $player = $event->getPlayer();

        if(!isset($this->setters[$player->getName()])) {
            return;
        }

        $event->cancel();
        $args = explode(" ", $event->getMessage());

        /** @var Arena $arena */
        $arena = $this->setters[$player->getName()];

        switch ($args[0]) {
            case "help":
                $player->sendMessage("§a> SkyWars setup help (1/1):\n".
                "§7help : Displays list of available setup commands\n" .
                "§7slots : Updates arena slots\n".
                "§7level : Sets arena level\n".
                "§7spawn : Sets arena spawns\n".
                "§7joinsign : Sets arena join sign\n".
                "§7savelevel : Saves the arena level\n".
                "§7enable : Enables the arena");
                break;
            case "slots":
                if(!isset($args[1])) {
                    $player->sendMessage("§cUsage: §7slots <int: slots>");
                    break;
                }
                $arena->data["slots"] = (int)$args[1];
                $player->sendMessage("§a> Slots updated to $args[1]!");
                break;
            case "level":
                if(!isset($args[1])) {
                    $player->sendMessage("§cUsage: §7level <levelName>");
                    break;
                }
                if(!$this->getServer()->getWorldManager()->isWorldGenerated($args[1])) {
                    $player->sendMessage("§c> Level $args[1] does not found!");
                    break;
                }
                $player->sendMessage("§a> Arena level updated to $args[1]!");
                $arena->data["level"] = $args[1];
                break;
            case "spawn":
                if(!isset($args[1])) {
                    $player->sendMessage("§cUsage: §7setspawn <int: spawn>");
                    break;
                }
                if(!is_numeric($args[1])) {
                    $player->sendMessage("§cType number!");
                    break;
                }
                if((int)$args[1] > $arena->data["slots"]) {
                    $player->sendMessage("§cThere are only {$arena->data["slots"]} slots!");
                    break;
                }

                $arena->data["spawns"]["spawn-{$args[1]}"] = (new Vector3($player->getPosition()->getX(), $player->getPosition()->getY(), $player->getPosition()->getZ()))->__toString();
                $player->sendMessage("§a> Spawn $args[1] set to X: " . (string)round($player->getPosition()->getX()) . " Y: " . (string)round($player->getPosition()->getY()) . " Z: " . (string)round($player->getPosition()->getZ()));
                break;
            case "joinsign":
                $player->sendMessage("§a> Break block to set join sign!");
                $this->setupData[$player->getName()] = 0;
                break;
            case "savelevel":
                if(!$arena->level instanceof World) {
                    $levelName = $arena->data["level"];
                    if(!is_string($levelName) || !$this->getServer()->getWorldManager()->isWorldGenerated($levelName)) {
                        errorMessage:
                        $player->sendMessage("§c> Error while saving the level: world not found.");
                        if($arena->setup) {
                            $player->sendMessage("§6> Try save level after enabling the arena.");
                        }
                        return;
                    }
                    if(!$this->getServer()->getWorldManager()->isWorldLoaded($levelName)) {
                        $this->getServer()->getWorldManager()->loadWorld($levelName, true);
                    }

                    try {
                        if(!$arena->mapReset instanceof MapReset) {
                            goto errorMessage;
                        }
                        $arena->mapReset->saveMap($this->getServer()->getWorldManager()->getWorldByName($levelName));
                        $player->sendMessage("§a> Level saved!");
                    }
                    catch (\Exception $exception) {
                        goto errorMessage;
                    }
                    break;
                }
                break;
            case "enable":
                if(!$arena->setup) {
                    $player->sendMessage("§6> Arena is already enabled!");
                    break;
                }

                if(!$arena->enable(false)) {
                    $player->sendMessage("§c> Could not load arena, there are missing information!");
                    break;
                }

                if($this->getServer()->getWorldManager()->isWorldGenerated($arena->data["level"])) {
                    if(!$this->getServer()->getWorldManager()->isWorldLoaded($arena->data["level"]))
                        $this->getServer()->getWorldManager()->loadWorld($arena->data["level"], true);
                    if(!$arena->mapReset instanceof MapReset)
                        $arena->mapReset = new MapReset($arena);
                    $arena->mapReset->saveMap($this->getServer()->getWorldManager()->getWorldByName($arena->data["level"]));
                }

                $arena->loadArena(false);
                $player->sendMessage("§a> Arena enabled!");
                break;
            case "done":
                $player->sendMessage("§a> You have successfully left setup mode!");
                unset($this->setters[$player->getName()]);
                if(isset($this->setupData[$player->getName()])) {
                    unset($this->setupData[$player->getName()]);
                }
                break;
            default:
                $player->sendMessage("§6> You are in setup mode.\n".
                    "§7- use §lhelp §r§7to display available commands\n"  .
                    "§7- or §ldone §r§7to leave setup mode");
                break;
        }
    }

    /**
     * @param BlockBreakEvent $event
     */
    public function onBreak(BlockBreakEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        if(isset($this->setupData[$player->getName()])) {
            switch ($this->setupData[$player->getName()]) {
                case 0:
                    $this->setters[$player->getName()]->data["joinsign"] = [(new Vector3($block->getPosition()->getX(), $block->getPosition()->getY(), $block->getPosition()->getZ()))->__toString(), $block->getPosition()->getWorld()->getFolderName()];
                    $player->sendMessage("§a> Join sign updated!");
                    unset($this->setupData[$player->getName()]);
                    $event->cancel();
                    break;
            }
        }
    }
}