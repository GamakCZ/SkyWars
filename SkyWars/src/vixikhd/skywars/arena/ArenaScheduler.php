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

use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\scheduler\Task;
use pocketmine\tile\Sign;
use vixikhd\skywars\math\Time;
use vixikhd\skywars\math\Vector3;

use pocketmine\item\Item;
use pocketmine\tile\Sign;
use skywars\math\Time;
use skywars\math\Vector3;
use pocketmine\Player;
use pocketmine\inventory\Inventory;

use pocketmine\utils\Config;
use Scoreboards\Scoreboards;

/**
 * Class ArenaScheduler
 * @package skywars\arena
 */
class ArenaScheduler extends Task {

    /** @var Arena $plugin */
    protected $plugin;

    /** @var int $startTime */
    public $startTime = 30;
    
    /** @var int $ChestFill */
    public $refill = 100;

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

    /**
     * @param int $currentTick
     */
    public function onRun(int $currentTick) {
        $this->reloadSign();

        if($this->plugin->setup) return;

        switch ($this->plugin->phase) {
            case Arena::PHASE_LOBBY:
                if(count($this->plugin->players) >= 2) {
                        foreach ($this->plugin->players as $player) {

    $api = Scoreboards::getInstance();
   
    $api->new($player, "ObjectiveName", "§l§eSKYWARS");
    
    $api->setLine($player, 1, T::GRAY." ".date("d/m/Y").T::BLACK." ");
    
    $api->setLine($player, 2, "  ");
          
    $api->setLine($player, 3, "§f§7: §a Solo");
                
    $api->setLine($player, 4, "§fa§7: §a ". $this->plugin->level->getFolderName());
    
    $api->setLine($player, 5, "   ");

                $api->setLine($player, 6, "§f Starting in§7: §a " . Time::calculateTime($this->startTime) . str_repeat(" ", 3));
                
                $api->setLine($player, 7, "          ");
                
                    $api->setLine($player, 8, "§f§7: §a /12 " . count($this->plugin->players));
    
                $api->setLine($player, 9, "          ");

                $api->setLine($player, 10, "§eurservername.com");
                $api->getObjectiveName($player);
                    
					$this->plugin->broadcastMessage("§eStarting in §c" . Time::calculateTime($this->startTime) . "", Arena::MSG_TIP);
					if($this->startTime == 10){
					$this->plugin->broadcastMessage("§l§eSKY§aWARS\n§r§cSolo§f Mode", Arena::MSG_TITLE);
					$this->plugin->broadcastMessage("§eThe game will started in §610§e seconds");
					}
					if($this->startTime == 5){
					$this->plugin->broadcastMessage("§l§c5\n§r§ePrepare to fight", Arena::MSG_TITLE);
					$this->plugin->broadcastMessage("§eThe game will started in §c5§e seconds");
					}
					if($this->startTime == 4){
					$this->plugin->broadcastMessage("§l§c4\n§r§ePrepare to fight", Arena::MSG_TITLE);
					$this->plugin->broadcastMessage("§eThe game will started in §c4§e seconds");
					}
					if($this->startTime == 3){
					$this->plugin->broadcastMessage("§l§c3\n§r§ePrepare to fight", Arena::MSG_TITLE);
					$this->plugin->broadcastMessage("§eThe game will started in §c3§e seconds");
					}
					if($this->startTime == 2){
					$this->plugin->broadcastMessage("§l§c2\n§r§ePrepare to fight", Arena::MSG_TITLE);
					$this->plugin->broadcastMessage("§eThe game will started in §c2§e seconds");
					}
					if($this->startTime == 1){
					$this->plugin->broadcastMessage("§l§c1\n§r§ePrepare to fight", Arena::MSG_TITLE);
					$this->plugin->broadcastMessage("§eThe game will started in §c1§e seconds");
					}
                    $this->startTime--;
                    if($this->startTime == 0){
                        $this->plugin->startGame();
                        foreach ($this->plugin->players as $player) {
                            $this->plugin->level->addSound(new AnvilUseSound($player->asVector3()));
						//	$player->getInventory()->removeItem(Item::get(261, 0, 1));
                        }
                    }
                 }
             }else{
                        foreach ($this->plugin->players as $player) {
                            $this->plugin->level->addSound(new ClickSound($player->asVector3()));
                        }
                    $this->startTime = 30;
                        foreach ($this->plugin->players as $player) {

    $api = Scoreboards::getInstance();
   
    $api->new($player, "ObjectiveName", "§l§eSKY§aWARS");
    
    $api->setLine($player, 1, T::GRAY." ".date("d/m/Y").T::BLACK." ");
    
                $api->getObjectiveName($player);
                }
                }
                break;
            case Arena::PHASE_GAME:
                        foreach ($this->plugin->players as $player) {

			    $t = str_repeat(" ", 65);
			    $kills = new Config("plugin_data/SkyWars/kills.yml", Config::YAML);
               $kills->getAll();
			    
			    $api = Scoreboards::getInstance();
			    $api->new($player, "ObjectiveName", "§l§eSKY§aWARS");
    
                $api->setLine($player, 1, T::GRAY." ".date("d/m/Y").T::BLACK." ");
    
                $api->setLine($player, 2, " ");
          
                $api->setLine($player, 3, "§f Chest Refill: §a " . Time::calculateTime($this->refill) . str_repeat(" ", 3));
    
                $api->setLine($player, 4, "          ");

                $api->setLine($player, 5, "§f§7: §a " . count($this->plugin->players) . str_repeat(" ", 3));
                
                $api->setLine($player, 6, "             ");
                
                $api->setLine($player, 7, "§f§7: §a " . $kills->get($player->getName(), 0) . str_repeat(" ", 3));
                
                $api->setLine($player, 8, "   ");
                      
                $api->setLine($player, 9, "§f Game Ends In: §a" . Time::calculateTime($this->gameTime) . str_repeat(" ", 3));
                
                $api->setLine($player, 10, "§f§7: §a ". $this->plugin->level->getFolderName());
    
                $api->setLine($player, 11, "§f§7: §aNormal");
    
                $api->setLine($player, 12, "              ");

                $api->setLine($player, 13, "§eurservername.com");
                $api->getObjectiveName($player);
			    
                switch ($this->gameTime) {
                    case 15 * 60:
                        $this->plugin->broadcastMessage("§l§eSKY§aWARS§8 >§r §eAll chest will be refill in 5 minutes!");
                        break;
                    case 11 * 60:
                        $this->plugin->broadcastMessage("§l§eSKY§aWARS§8 >§r §eAll chest will be refill in 1 minute!");
                        break;
                    case 10 * 60:
                        $this->plugin->broadcastMessage("§l§eSKY§aWARS§8 >§r §eAll chest has been refilled!");
                        break;
                    case 9 * 60:
                        $this->plugin->broadcastMessage("§l§eSKY§aWARS§8 >§r §eEnder dragon spawning in 2 minutes!");
                        break;
                    case 8 * 60:
                        $this->plugin->broadcastMessage("§l§eSKY§aWARS§8 >§r §eThe End dragon has been spawned!");

                        break;

                }
         }
                if($this->plugin->checkEnd()) $this->plugin->startRestart();
                $this->gameTime--;
                break;
            case Arena::PHASE_RESTART:
                $this->plugin->broadcastMessage("§eTeleporting to lobby in §c{$this->restartTime}§e seconds", Arena::MSG_TIP);
                $this->restartTime--;

                switch ($this->restartTime) {
                    case 0:
        foreach ($this->plugin->players as $player) {
                            
        $player->teleport($this->plugin->plugin->getServer()->getDefaultLevel()->getSpawnLocation());
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->setFood(20);
        $player->setHealth(20);
        $player->setGamemode($this->plugin->plugin->getServer()->getDefaultGamemode());
             //CLEARS KILLS
             $bkconfig = new Config("plugin_data/SkyWars/kills.yml", Config::YAML);
             $bkconfig->set($player->getName(), $bkconfig->remove($player->getName(), "               "));
             $bkconfig->set($player->getName(), $bkconfig->remove($player->getName(), "0"));
             $bkconfig->getAll();

                        }
                $this->plugin->loadArena(true);
                  $this->reloadTimer();
                        break;
                }
                break;
        }
    }
    
    /**
     * @param PlayerQuitEvent $event
     */
    public function onQuit(PlayerQuitEvent $event) : void{
        $player = $event->getPlayer();
        $player->teleport($this->plugin->plugin->getServer()->getDefaultLevel()->getSpawnLocation());
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->setFood(20);
        $player->setHealth(20);
        $player->setGamemode($this->plugin->plugin->getServer()->getDefaultGamemode());
        //CLEARS KILLS
        $bkconfig = new Config("plugin_data/SkyWars/kills.yml", Config::YAML);
        $bkconfig->set($player->getName(), $bkconfig->remove($player->getName(), "               "));
        $bkconfig->set($player->getName(), $bkconfig->remove($player->getName(), "0"));
        $bkconfig->getAll();

    }
    
  /**
   * @param PlayerDeathEvent $event
   */
   
   public function onPlayerDeath(PlayerDeathEvent $event){
    $victim = $event->getEntity();
    
    if($victim->getLastDamageCause() instanceof EntityDamageByEntityEvent){
    if($victim->getLastDamageCause()->getDamager() instanceof Player){
      $killer = $victim->getLastDamageCause()->getDamager();

      SoulsAPI::getInstance()->addSouls($killer, 3);
      $killer->sendMessage("§l§a+§r§b3§e souls");
       $victim->setHealth(20);
        $victim->setFood(20);
		$victim->setGamemode(3);
		$victim->setFlying(true);

      $tklconfig = new Config("plugin_data/SkyWars/kills.yml", Config::YAML);
		$tklconfig->set($player->getName(), $tklconfig->get($player->getName()) + 1);
     $tklconfig->set($killer->getName(), $tklconfig->get($killer->getName()) + 1);
      $tklconfig->save();
         }
     }
}

    public function reloadSign() {
        if(!is_array($this->plugin->data["joinsign"]) || empty($this->plugin->data["joinsign"])) return;

        $signPos = Position::fromObject(Vector3::fromString($this->plugin->data["joinsign"][0]), $this->plugin->plugin->getServer()->getLevelByName($this->plugin->data["joinsign"][1]));

        if(!$signPos->getLevel() instanceof Level) return;

        $signText = [
            "§l§eSKY§aWARS",
            "§8[ §f?§7 /§a ? §8]",
            "§cResetting Arena",
            "§eWait few seconds..."
        ];

        if($signPos->getLevel()->getTile($signPos) === null) return;

        if($this->plugin->setup) {
            /** @var Sign $sign */
            $sign = $signPos->getLevel()->getTile($signPos);
            $sign->setText($signText[0], $signText[1], $signText[2], $signText[3]);
            return;
        }

        $signText[1] = "§f" . count($this->plugin->players) . " §7/ " . $this->plugin->data["slots"] . "";

        switch ($this->plugin->phase) {
            case Arena::PHASE_LOBBY:
                if(count($this->plugin->players) >= $this->plugin->data["slots"]) {
                    $signText[2] = "§c§lFull ";
                    $signText[3] = "§fMap§7: §e{$this->plugin->level->getFolderName()}";
                }
                else {
                    $signText[2] = "§ajoinable";
                    $signText[3] = "§fMap§7: §e{$this->plugin->level->getFolderName()}";
                }
                break;
            case Arena::PHASE_GAME:
                $signText[2] = "§c§lGame-running";
                $signText[3] = "§fMap§7: §e{$this->plugin->level->getFolderName()}";
                break;
            case Arena::PHASE_RESTART:
                $signText[2] = "§c§lResetting arena§r§e...";
                $signText[3] = "§fMap§7: §a{$this->plugin->level->getFolderName()}";
                break;
        }

        /** @var Sign $sign */
        $sign = $signPos->getLevel()->getTile($signPos);
        $sign->setText($signText[0], $signText[1], $signText[2], $signText[3]);
    }

    public function reloadTimer() {
        $this->startTime = 30;
        $this->gameTime = 20 * 60;
        $this->restartTime = 10;
        $this->refill = 100;
    }
}
