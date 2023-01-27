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

namespace vixikhd\skywars\arena;

use pocketmine\block\utils\SignText;
use pocketmine\world\Position;
use pocketmine\world\sound\AnvilUseSound;
use pocketmine\world\sound\ClickSound;
use pocketmine\scheduler\Task;
use pocketmine\block\tile\Sign;
use pocketmine\world\World;
use vixikhd\skywars\math\Time;
use vixikhd\skywars\math\Vector3;

/**
 * Class ArenaScheduler
 * @package skywars\arena
 */
class ArenaScheduler extends Task {

    /** @var Arena $plugin */
    protected $plugin;

    /** @var int $startTime */
    public $startTime = 40;

    /** @var float|int $gameTime */
    public $gameTime = 20 * 60;

    /** @var int $restartTime */
    public $restartTime = 10;

    /** @var array $restartData */
    public $restartData = [];

    /**
     * ArenaScheduler constructor.
     * @param Arena $plugin
     */
    public function __construct(Arena $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void {
        $this->reloadSign();

        if($this->plugin->setup) return;

        switch ($this->plugin->phase) {
            case Arena::PHASE_LOBBY:
                if(count($this->plugin->players) >= 2) {
                    $this->plugin->broadcastMessage("§a> Starting in " . Time::calculateTime($this->startTime) . " sec.", Arena::MSG_TIP);
                    $this->startTime--;
                    if($this->startTime == 0) {
                        $this->plugin->startGame();
                        foreach ($this->plugin->players as $player) {
                            $this->plugin->level->addSound($player->getPosition()->asVector3(), new AnvilUseSound());
                        }
                    }
                    else {
                        foreach ($this->plugin->players as $player) {
                            $this->plugin->level->addSound($player->getPosition()->asVector3(), new ClickSound());
                        }
                    }
                }
                else {
                    $this->plugin->broadcastMessage("§c> You need more players to start a game!", Arena::MSG_TIP);
                    $this->startTime = 40;
                }
                break;
            case Arena::PHASE_GAME:
                $this->plugin->broadcastMessage("§a> There are " . count($this->plugin->players) . " players, time to end: " . Time::calculateTime($this->gameTime) . "", Arena::MSG_TIP);
                switch ($this->gameTime) {
                    case 15 * 60:
                        $this->plugin->broadcastMessage("§a> All chests will be refilled in 5 min.");
                        break;
                    case 11 * 60:
                        $this->plugin->broadcastMessage("§a> All chest will be refilled in 1 min.");
                        break;
                    case 10 * 60:
                        $this->plugin->broadcastMessage("§a> All chests are refilled.");
                        break;
                }
                if($this->plugin->checkEnd()) $this->plugin->startRestart();
                $this->gameTime--;
                break;
            case Arena::PHASE_RESTART:
                $this->plugin->broadcastMessage("§a> Restarting in {$this->restartTime} sec.", Arena::MSG_TIP);
                $this->restartTime--;

                switch ($this->restartTime) {
                    case 0:

                        foreach ($this->plugin->players as $player) {
                            $player->teleport($this->plugin->plugin->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());

                            $player->getInventory()->clearAll();
                            $player->getArmorInventory()->clearAll();
                            $player->getCursorInventory()->clearAll();

                            $player->getHungerManager()->setFood(20);
                            $player->setHealth(20);

                            $player->setGamemode($this->plugin->plugin->getServer()->getGamemode());
                        }
                        $this->plugin->loadArena(true);
                        $this->reloadTimer();
                        break;
                }
                break;
        }
    }

    public function reloadSign() {
        if(!is_array($this->plugin->data["joinsign"]) || empty($this->plugin->data["joinsign"])) return;

        $signPos = Position::fromObject(Vector3::fromString($this->plugin->data["joinsign"][0]), $this->plugin->plugin->getServer()->getWorldManager()->getWorldByName($this->plugin->data["joinsign"][1]));

        if(!$signPos->getWorld() instanceof World || is_null($this->plugin->level) || !$signPos->isValid()) return;

        $signText = [
            "§e§lSkyWars",
            "§9[ §b? / ? §9]",
            "§6Setup",
            "§6Wait few sec..."
        ];

        if($signPos->getWorld()->getTile($signPos) === null) return;

        if($this->plugin->setup || $this->plugin->level === null) {
            /** @var Sign $sign */
            $sign = $signPos->getWorld()->getTile($signPos);
            $sign->setText(new SignText([$signText[0], $signText[1], $signText[2], $signText[3]]));
            return;
        }

        $signText[1] = "§9[ §b" . count($this->plugin->players) . " / " . $this->plugin->data["slots"] . " §9]";

        switch ($this->plugin->phase) {
            case Arena::PHASE_LOBBY:
                if(count($this->plugin->players) >= $this->plugin->data["slots"]) {
                    $signText[2] = "§6Full";
                }
                else {
                    $signText[2] = "§aJoin";
                }
                $signText[3] = "§8Map: §7{$this->plugin->level->getFolderName()}";
                break;
            case Arena::PHASE_GAME:
                $signText[2] = "§5InGame";
                $signText[3] = "§8Map: §7{$this->plugin->level->getFolderName()}";
                break;
            case Arena::PHASE_RESTART:
                $signText[2] = "§cRestarting...";
                $signText[3] = "§8Map: §7{$this->plugin->level->getFolderName()}";
                break;
        }

        /** @var Sign $sign */
        $sign = $signPos->getWorld()->getTile($signPos);
        if($sign instanceof Sign) // Chest->setText() doesn't work :D
            $sign->setText(new SignText([$signText[0], $signText[1], $signText[2], $signText[3]]));
    }

    public function reloadTimer() {
        $this->startTime = 30;
        $this->gameTime = 20 * 60;
        $this->restartTime = 10;
    }
}
