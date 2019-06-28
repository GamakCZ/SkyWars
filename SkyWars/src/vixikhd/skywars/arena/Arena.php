<?php

/**
 * Copyright 2018-2019 GamakCZ
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

use pocketmine\block\Block;
use pocketmine\entity\Attribute;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\inventory\ChestInventory;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\Player;
use pocketmine\tile\Chest;
use pocketmine\tile\Tile;
use vixikhd\skywars\event\PlayerArenaWinEvent;
use vixikhd\skywars\math\Vector3;
use vixikhd\skywars\SkyWars;

/**
 * Class Arena
 * @package skywars\arena
 */
class Arena implements Listener {

    const MSG_MESSAGE = 0;
    const MSG_TIP = 1;
    const MSG_POPUP = 2;
    const MSG_TITLE = 3;

    const PHASE_LOBBY = 0;
    const PHASE_GAME = 1;
    const PHASE_RESTART = 2;

    /** @var SkyWars $plugin */
    public $plugin;

    /** @var ArenaScheduler $scheduler */
    public $scheduler;

    /** @var MapReset $mapReset */
    public $mapReset;

    /** @var int $phase */
    public $phase = 0;

    /** @var array $data */
    public $data = [];

    /** @var bool $setting */
    public $setup = false;

    /** @var Player[] $players */
    public $players = [];

    /** @var Player[] $toRespawn */
    public $toRespawn = [];

    /** @var Level $level */
    public $level = null;

    /**
     * Arena constructor.
     * @param SkyWars $plugin
     * @param array $arenaFileData
     */
    public function __construct(SkyWars $plugin, array $arenaFileData) {
        $this->plugin = $plugin;
        $this->data = $arenaFileData;
        $this->setup = !$this->enable(\false);

        $this->plugin->getScheduler()->scheduleRepeatingTask($this->scheduler = new ArenaScheduler($this), 20);

        if($this->setup) {
            if(empty($this->data)) {
                $this->createBasicData();
            }
        }
        else {
            $this->loadArena();
        }
    }

    /**
     * @param Player $player
     */
    public function joinToArena(Player $player) {
        if(!$this->data["enabled"]) {
            $player->sendMessage("§c> Arena is under setup!");
            return;
        }

        if(count($this->players) >= $this->data["slots"]) {
            $player->sendMessage("§c> Arena is full!");
            return;
        }

        if($this->inGame($player)) {
            $player->sendMessage("§c> You are already in game!");
            return;
        }

        $selected = false;
        for($lS = 1; $lS <= $this->data["slots"]; $lS++) {
            if(!$selected) {
                if(!isset($this->players[$index = "spawn-{$lS}"])) {
                    $player->teleport(Position::fromObject(Vector3::fromString($this->data["spawns"][$index]), $this->level));
                    $this->players[$index] = $player;
                    $selected = true;
                }
            }
        }

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();

        $player->setGamemode($player::ADVENTURE);
        $player->setHealth(20);
        $player->setFood(20);

        $this->broadcastMessage("§a> Player {$player->getName()} joined! §7[".count($this->players)."/{$this->data["slots"]}]");
    }

    /**
     * @param Player $player
     * @param string $quitMsg
     * @param bool $death
     */
    public function disconnectPlayer(Player $player, string $quitMsg = "", bool $death = false) {
        switch ($this->phase) {
            case Arena::PHASE_LOBBY:
                $index = "";
                foreach ($this->players as $i => $p) {
                    if($p->getId() == $player->getId()) {
                        $index = $i;
                    }
                }
                if($index != "") {
                    unset($this->players[$index]);
                }
                break;
            default:
                unset($this->players[$player->getName()]);
                break;
        }

        $player->removeAllEffects();

        $player->setGamemode($this->plugin->getServer()->getDefaultGamemode());

        $player->setHealth(20);
        $player->setFood(20);

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();

        $player->teleport($this->plugin->getServer()->getDefaultLevel()->getSpawnLocation());

        if(!$death) {
            $this->broadcastMessage("§a> Player {$player->getName()} left the game. §7[".count($this->players)."/{$this->data["slots"]}]");
        }

        if($quitMsg != "") {
            $player->sendMessage("§a> $quitMsg");
        }
    }

    public function startGame() {
        $players = [];
        foreach ($this->players as $player) {
            $players[$player->getName()] = $player;
            $player->setGamemode($player::SURVIVAL);
        }


        $this->players = $players;
        $this->phase = 1;

        $this->fillChests();

        $this->broadcastMessage("Game Started!", self::MSG_TITLE);
    }

    public function startRestart() {
        $player = null;
        foreach ($this->players as $p) {
            $player = $p;
        }

        if($player === null || (!$player instanceof Player) || (!$player->isOnline())) {
            $this->phase = self::PHASE_RESTART;
            return;
        }

        $player->addTitle("§aYOU WON!");
        $this->plugin->getServer()->getPluginManager()->callEvent(new PlayerArenaWinEvent($this->plugin, $player, $this));
        $this->plugin->getServer()->broadcastMessage("§a[SkyWars] Player {$player->getName()} won the game at {$this->level->getFolderName()}!");
        $this->phase = self::PHASE_RESTART;
    }

    /**
     * @param Player $player
     * @return bool $isInGame
     */
    public function inGame(Player $player): bool {
        switch ($this->phase) {
            case self::PHASE_LOBBY:
                $inGame = false;
                foreach ($this->players as $players) {
                    if($players->getId() == $player->getId()) {
                        $inGame = true;
                    }
                }
                return $inGame;
            default:
                return isset($this->players[$player->getName()]);
        }
    }

    /**
     * @param string $message
     * @param int $id
     * @param string $subMessage
     */
    public function broadcastMessage(string $message, int $id = 0, string $subMessage = "") {
        foreach ($this->players as $player) {
            switch ($id) {
                case self::MSG_MESSAGE:
                    $player->sendMessage($message);
                    break;
                case self::MSG_TIP:
                    $player->sendTip($message);
                    break;
                case self::MSG_POPUP:
                    $player->sendPopup($message);
                    break;
                case self::MSG_TITLE:
                    $player->addTitle($message, $subMessage);
                    break;
            }
        }
    }

    /**
     * @return bool $end
     */
    public function checkEnd(): bool {
        return count($this->players) <= 1;
    }

    public function fillChests() {

        $fillInv = function (ChestInventory $inv) {
            $fillSlot = function (ChestInventory $inv, int $slot) {
                $id = self::getChestItems()[$index = rand(0, 4)][rand(0, (int)(count(self::getChestItems()[$index])-1))];
                switch ($index) {
                    case 0:
                        $count = 1;
                        break;
                    case 1:
                        $count = 1;
                        break;
                    case 2:
                        $count = rand(5, 64);
                        break;
                    case 3:
                        $count = rand(5, 64);
                        break;
                    case 4:
                        $count = rand(1, 5);
                        break;
                    default:
                        $count = 0;
                        break;
                }
                $inv->setItem($slot, Item::get($id, 0, $count));
            };

            $inv->clearAll();

            for($x = 0; $x <= 26; $x++) {
                if(rand(1, 3) == 1) {
                    $fillSlot($inv, $x);
                }
            }
        };

        $level = $this->level;
        foreach ($level->getTiles() as $tile) {
            if($tile instanceof Chest) {
                $fillInv($tile->getInventory());
            }
        }
    }

    /**
     * @param PlayerMoveEvent $event
     */
    public function onMove(PlayerMoveEvent $event) {
        if($this->phase != self::PHASE_LOBBY) return;
        $player = $event->getPlayer();
        if($this->inGame($player)) {
            $index = null;
            foreach ($this->players as $i => $p) {
                if($p->getId() == $player->getId()) {
                    $index = $i;
                }
            }
            if($event->getPlayer()->asVector3()->distance(Vector3::fromString($this->data["spawns"][$index])) > 1) {
                // $event->setCancelled() wont work
                $player->teleport(Vector3::fromString($this->data["spawns"][$index]));
            }
        }
    }

    /**
     * @param PlayerExhaustEvent $event
     */
    public function onExhaust(PlayerExhaustEvent $event) {
        $player = $event->getPlayer();

        if(!$player instanceof Player) return;

        if($this->inGame($player) && $this->phase == self::PHASE_LOBBY) {
            $event->setCancelled(true);
        }
    }

    /**
     * @param PlayerInteractEvent $event
     */
    public function onInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();

        if($this->inGame($player) && $event->getBlock()->getId() == Block::CHEST && $this->phase == self::PHASE_LOBBY) {
            $event->setCancelled(true);
            return;
        }

        if(!$block->getLevel()->getTile($block) instanceof Tile) {
            return;
        }

        $signPos = Position::fromObject(Vector3::fromString($this->data["joinsign"][0]), $this->plugin->getServer()->getLevelByName($this->data["joinsign"][1]));

        if((!$signPos->equals($block)) || $signPos->getLevel()->getId() != $block->getLevel()->getId()) {
            return;
        }

        if($this->phase == self::PHASE_GAME) {
            $player->sendMessage("§c> Arena is in-game");
            return;
        }
        if($this->phase == self::PHASE_RESTART) {
            $player->sendMessage("§c> Arena is restarting!");
            return;
        }

        if($this->setup) {
            return;
        }

        $this->joinToArena($player);
    }

    /**
     * @param PlayerDeathEvent $event
     */
    public function onDeath(PlayerDeathEvent $event) {
        $player = $event->getPlayer();

        if(!$this->inGame($player)) return;

        foreach ($event->getDrops() as $item) {
            $player->getLevel()->dropItem($player, $item);
        }
        $this->toRespawn[$player->getName()] = $player;
        $this->disconnectPlayer($player, "", true);
        $this->broadcastMessage("§a> {$this->plugin->getServer()->getLanguage()->translate($event->getDeathMessage())} §7[".count($this->players)."/{$this->data["slots"]}]");
        $event->setDeathMessage("");
        $event->setDrops([]);
    }

    /**
     * @param PlayerRespawnEvent $event
     */
    public function onRespawn(PlayerRespawnEvent $event) {
        $player = $event->getPlayer();
        if(isset($this->toRespawn[$player->getName()])) {
            $event->setRespawnPosition($this->plugin->getServer()->getDefaultLevel()->getSpawnLocation());
            unset($this->toRespawn[$player->getName()]);
        }
    }

    /**
     * @param PlayerQuitEvent $event
     */
    public function onQuit(PlayerQuitEvent $event) {
        if($this->inGame($event->getPlayer())) {
            $this->disconnectPlayer($event->getPlayer());
        }
    }

    /**
     * @param EntityLevelChangeEvent $event
     */
    public function onLevelChange(EntityLevelChangeEvent $event) {
        $player = $event->getEntity();
        if(!$player instanceof Player) return;
        if($this->inGame($player)) {
            $this->disconnectPlayer($player, "You have successfully left the game!");
        }
    }

    /**
     * @param bool $restart
     */
    public function loadArena(bool $restart = false) {
        if(!$this->data["enabled"]) {
            $this->plugin->getLogger()->error("Can not load arena: Arena is not enabled!");
            return;
        }

        if(!$this->mapReset instanceof MapReset) {
            $this->mapReset = new MapReset($this);
        }

        if(!$restart) {
            $this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);

            if(!$this->plugin->getServer()->isLevelLoaded($this->data["level"])) {
                $this->plugin->getServer()->loadLevel($this->data["level"]);
            }
        }



        else {
            $this->scheduler->reloadTimer();
            $this->level = $this->mapReset->loadMap($this->data["level"]);
        }

        if(!$this->level instanceof Level) {
            $level = $this->mapReset->loadMap($this->data["level"]);
            if(!$level instanceof Level) {
                $this->plugin->getLogger()->error("Arena level wasn't found. Try save level in setup mode.");
                $this->setup = true;
                return;
            }
            $this->level = $level;
        }


        $this->phase = static::PHASE_LOBBY;
        $this->players = [];
    }

    /**
     * @param bool $loadArena
     * @return bool $isEnabled
     */
    public function enable(bool $loadArena = true): bool {
        if(empty($this->data)) {
            return false;
        }
        if($this->data["level"] == null) {
            return false;
        }
        if(!$this->plugin->getServer()->isLevelGenerated($this->data["level"])) {
            return false;
        }
        if(!is_int($this->data["slots"])) {
            return false;
        }
        if(!is_array($this->data["spawns"])) {
            return false;
        }
        if(count($this->data["spawns"]) != $this->data["slots"]) {
            return false;
        }
        if(!is_array($this->data["joinsign"])) {
            return false;
        }
        if(count($this->data["joinsign"]) !== 2) {
            return false;
        }
        $this->data["enabled"] = true;
        $this->setup = false;
        if($loadArena) $this->loadArena();
        return true;
    }

    private function createBasicData() {
        $this->data = [
            "level" => null,
            "slots" => 12,
            "spawns" => [],
            "enabled" => false,
            "joinsign" => []
        ];
    }

    /**
     * @return array $chestItems
     */
    public static function getChestItems(): array {
        $chestItems = [];
        $chestItems[0] = [
            256, 257, 258, 267, 268, 269, 270, 271, 272, 273, 274, 275, 276, 277, 278, 279
        ];
        $chestItems[1] = [
            298, 299, 300, 301, 302, 303, 304, 305, 306, 307, 308, 309, 310, 311, 312, 313, 314, 315, 316, 317
        ];
        $chestItems[2] = [
            319, 320, 297, 391, 392, 393, 396, 400, 411, 412, 423, 424
        ];
        $chestItems[3] = [
            1, 2, 3, 4, 5, 12, 13, 14, 15, 16, 17, 18, 82, 35, 45
        ];
        $chestItems[4] = [
            263, 264, 265, 266, 280, 297, 322
        ];
        return $chestItems;
    }

    public function __destruct() {
        unset($this->scheduler);
    }
}