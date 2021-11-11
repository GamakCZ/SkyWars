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

use pocketmine\block\Block;
use pocketmine\entity\Attribute;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\Listener;

use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;

use pocketmine\command\CommandExecutor;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\inventory\ChestInventory;

use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;

use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;

use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\Player;
use pocketmine\tile\Chest;
use pocketmine\tile\Tile;
use skywars\event\PlayerArenaWinEvent;
use skywars\math\Vector3;
use skywars\SkyWars;

use onebone\economyapi\EconomyAPI;
use AMGM\TOP;

use pocketmine\Server;
use pocketmine\inventory\Inventory;

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


    public $plugin;
    
    public $scheduler;
    public $mapReset;
    public $phase = 0;
    
    public $data = [];
    public $setup = false;

    public $players = [];
    public $toRespawn = [];
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
    public function joinToArena(Player $event) {
		$player = $event->getPlayer();
        if(!$this->data["enabled"]) {
            $player->sendMessage("§l§cPlease try again later!");
            return;
        }

        if(count($this->players) >= $this->data["slots"]) {
            $player->sendMessage("§cMap is §lFULL§r§c! Please try another map!");
            return;
        }

        if($this->inGame($player)) {
            $player->sendMessage("§cYou Are already in a game!");
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
		$player->setAllowFlight(false);
		$player->setFlying(false);
		$player = $event->getPlayer();
		$name = $player->getName();
		$this->broadcastMessage("§r§f{$name}§e joined the match §e(§b".count($this->players)."/{$this->data["slots"]}§e)");
		$player->getInventory()->setItem(0, Item::get(261, 0, 1)->setCustomName("§eKits §7(§fTap to use§7)"));
		$player->getInventory()->setItem(8, Item::get(355, 14, 1)->setCustomName("§cBack To Hub §5(§fTap to use§5)"));
	}

    /**
     * @param Player $player
     * @param string $quitMsg
     * @param bool $death
     */
    public function disconnectPlayer(Player $player, string $quitMsg = "", bool $death = \false) {
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
        if(!$death) {
            $this->broadcastMessage("§f{$player->getName()}§e left the match §e(§b".count($this->players)."/{$this->data["slots"]}§e)");
        }

        if($quitMsg != "") {
            $player->sendMessage("$quitMsg");
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
     
        $this->broadcastMessage("§l§cSTART", self::MSG_TITLE);
		$this->broadcastMessage("§eGame started!");
		$this->broadcastMessage("§l§a=============================");
		$this->broadcastMessage("                    §l§fSKYWARS         ");
		$this->broadcastMessage("§eThe game has started! Be the last player to win! There are also goodies in the middle, try to get there fast!");
        $this->broadcastMessage("§cThis is Not DctxGames Map, We Currently Dont Have Builders...");
        $this->broadcastMessage("§cSo");
		$this->broadcastMessage("§l§a=============================");

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
        $player->addTitle("§l§6VICTORY!", "§7You were the last man standing!");
		$player->sendMessage("§a=====================================");
		$player->sendMessage("\n");
		$player->sendMessage("                   §l§eSKY§aWARS§r!                    ");
		$player->sendMessage("§eEarned §610§b Coins§e for Winning");
		$player->sendMessage("§eEarned §610§b Coins§e for Playing");
		$player->sendMessage("\n");
		$player->sendMessage("§a=====================================");
		$player->sendMessage("§6+10 Coins Total");
        $this->plugin->getServer()->getPluginManager()->callEvent(new PlayerArenaWinEvent($this->plugin, $player, $this));
        $this->plugin->getServer()->broadcastMessage("§l§eSKY§aWARS §f{$player->getName()}§b won the skywars match on arena §b{$this->level->getFolderName()}!");
		$player->getInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
		EconomyAPI::getInstance()->addMoney($player, 100);
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
                $id = self::getChestItems()[$index = rand(0, 5)][rand(0, (int)(count(self::getChestItems()[$index])-1))];
                switch ($index) {
                    case 0:
                        $count = 1;
                        break;
                    case 1:
                        $count = rand(16, 32);
                        break;
                    case 2:
                        $count = 1;
                        break;
                    case 3:
                        $count = 1;
                        break;
                    case 4:
                        $count = rand(8, 16);
                        break;
				    case 5:
					    $count = 1;
						break;
                    default:
                        $count = 0;
                        break;
                }
                $ec = mt_rand(1, 60);
                switch($ec){
               case 1:
			   $ec = Item::get(1, 0, 16);
			   break;
			   case 3:
			   $ec = Item::get(4, 0, 16);
			   break;
			   case 5:
			   $ec = Item::get(5, 0, 16);
			   break;
			   case 7:
			   $ec = Item::get(275, 0, 1);
			   break;
			   case 9:
			   $ec = Item::get(274, 0, 1);
			   break;
			   case 11:
			   $ec = Item::get(306, 0, 1);
			   break;
			   case 13:
			   $ec = Item::get(307, 0, 1);
			   break;
			   case 14:
			   $ec = Item::get(308, 0, 1);
			   $ec1 = Enchantment::getEnchantment(0);
			   $ec1a = new EnchantmentInstance($ec1, 1);
			   $ec->addEnchantment($ec1a);
			   break;
			   case 16:
			   $ec = Item::get(308, 0, 1);
               break;
			   case 18:
			   $ec = Item::get(276, 0, 1);
			   break;
			   case 20:
			   $ec = Item::get(276, 0, 1);
			   $ec1 = Enchantment::getEnchantment(9);
			   $ec1a = new EnchantmentInstance($ec1, 1);
			   break;
			   case 22:
			   $ec = Item::get(261, 0, 1);
			   break;
			   case 23:
			   $ec = Item::get(262, 0, 16);
			   break;
			   case 25:
			   $ec = Item::get(279, 0, 1);
			   break;
			   case 27:
			   $ec = Item::get(278, 0, 1);
			   break;
			   case 29:
			   $ec = Item::get(332, 0, 16);
			   break;
			   case 31:
			   $ec = Item::get(344, 0, 16);
			   break;
			   case 35:
			   $ec = Item::get(276, 0, 1);
			   $ec1 = Enchantment::getEnchantment(9);
			   $ec1a = new EnchantmentInstance($ec1, 3);
			   break;
			   case 37:
			   $ec = Item::get(310, 0, 1);
			   $ec1 = Enchantment::getEnchantment(0);
			   $ec1a = new EnchantmentInstance($ec1, 3);
			   $ec->addEnchantment($ec1a);
			   break;
			   case 39:
			   $ec = Item::get(311, 0, 1);
			   $ec1 = Enchantment::getEnchantment(0);
			   $ec1a = new EnchantmentInstance($ec1, 3);
			   $ec->addEnchantment($ec1a);
			   break;
			   case 41:
			   $ec = Item::get(312, 0, 1);
			   $ec1 = Enchantment::getEnchantment(0);
			   $ec1a = new EnchantmentInstance($ec1, 3);
			   $ec->addEnchantment($ec1a);
			   break;
			   case 44:
			   $ec = Item::get(313, 0, 1);
			   $ec1 = Enchantment::getEnchantment(0);
			   $ec1a = new EnchantmentInstance($ec1, 3);
			   $ec->addEnchantment($ec1a);
			   break;
			   case 46:
			   $ec = Item::get(373, 8226, 1);
			   break;
			   case 48:
			   $ec = Item::get(373, 30, 1);
			   break;
			   case 50:
			   $ec = Item::get(368, 0, 1);
			   break;
			   case 52:
			   $ec = Item::get(322, 0, 1);
			   break;
			   case 53:
			   $ec = Item::get(10, 0, 1);
			   break;
               case 54:
               $ec = Item::get(5, 0, 64);
               break;
               case 55:
               $ec = Item::get(5, 0, 64);
               break;
               case 56:
               $ec = Item::get(1, 0, 64);
               break;
               case 57:
               $ec = Item::get(1, 0, 64);
               break;
               case 58:
               $ec = Item::get(17, 0, 64);
               break;
               case 59:
               $ec = Item::get(4, 0, 64);
               break;
			   
			   default:
               $ec = Item::get(0, 0, 0);
                        break;
                }  
                $inv->setItem($slot, Item::get($id, 0, $count));
                $inv->setItem($slot, $ec);
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
                // $event->setCancelled() will not work
                $player->teleport(Vector3::fromString($this->data["spawns"][$index]));
            }
        }
    }
    /**
     * @param PlayerInteractEvent $event
     */
    public function onInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();

        if($this->inGame($player) && $event->getBlock()->getId() == Block::CHEST && $this->phase == self::PHASE_LOBBY) {
            $event->setCancelled(\true);
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
            $player->sendMessage("§cArena is in-game");
            return;
        }
        if($this->phase == self::PHASE_RESTART) {
            $player->sendMessage("§cArena is reseting!");
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
		$this->disconnectPlayer($player, "", true);
		$player->setHealth(20);
        $player->setFood(20);
		$player->setGamemode(3);
		$player->setFlying(true);

        $player->sendMessage(count($this->players). "/{$this->data["slots"]})");
        $player->addTitle("§l§cYOU DIED!");
		$event->setDrops([]);
    }
    
	public function onHit(ProjectileHitEvent $event) {
		$projectile = $event->getEntity();
		if($projectile->isAlive() and $projectile instanceof Arrow) {
			$shooter = $projectile->shootingEntity;
			if($shooter instanceof Player) {
                           if ($shooter->getDirection() == 0) {
                               $projectile->knockBack($projectile, 0, 0.1, 0.1, 0);
                           } elseif ($shooter->getDirection() == 1) {
                               $projectile->knockBack($projectile, 0, 0.1, 0.1, 0);
                           } elseif ($shooter->getDirection() == 2) {
                               $projectile->knockBack($projectile, 0, -0.1, 0.1, 0);
                           } elseif ($shooter->getDirection() == 3) {
                               $projectile->knockBack($projectile, 0, 0.1, -0.1, 0);
                           }
                       }
                   }
                }

    /**
     * @param PlayerQuitEvent $event
     */
    public function onQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
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

            $this->mapReset->saveMap($this->level = $this->plugin->getServer()->getLevelByName($this->data["level"]));
        }



        else {
            $this->scheduler->reloadTimer();
            $this->level = $this->mapReset->loadMap($this->data["level"]);
        }

        if(!$this->level instanceof Level) $this->level = $this->mapReset->loadMap($this->data["level"]);

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
            268, 298, 299, 300, 301, 259, 275, 274
        ];
        $chestItems[1] = [
            1, 4, 46, 364, 3
        ];
        $chestItems[2] = [
            258, 306, 307, 308, 309
        ];
        $chestItems[3] = [
            265, 264, 327, 326, 261, 276
        ];
        $chestItems[4] = [
            262
        ];
		$chestItems[5] = [
		    311, 313
	    ];
        return $chestItems;
    }

    public function __destruct() {
        unset($this->scheduler);
    }
}
