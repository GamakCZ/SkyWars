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

namespace vixikhd\skywars\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use vixikhd\skywars\arena\Arena;
use vixikhd\skywars\SkyWars;

/**
 * Class SkyWarsCommand
 * @package skywars\commands
 */
class SkyWarsCommand extends Command implements PluginIdentifiableCommand {

    /** @var SkyWars $plugin */
    protected $plugin;

    /**
     * SkyWarsCommand constructor.
     * @param SkyWars $plugin
     */
    public function __construct(SkyWars $plugin) {
        $this->plugin = $plugin;
        parent::__construct("skywarsteam", "SkyWarsTeam commands", \null, ["swt"]);
    }

    /**
     * @param CommandSender $sender
     * @param string $commandLabel
     * @param array $args
     * @return mixed|void
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if(!$sender->hasPermission("sw.cmd")) {
            $sender->sendMessage("§cYou have not permissions to use this command!");
            return;
        }
        if(!isset($args[0])) {
            $sender->sendMessage("§cUsage: §7/swt help");
            return;
        }

        switch ($args[0]) {
            case "help":
                if(!$sender->hasPermission("sw.cmd.help")) {
                    $sender->sendMessage("§cYou have not permissions to use this command!");
                    break;
                }
                $sender->sendMessage("§a> SkyWars Teams commands:\n" .
                    "§7/swt help : Displays list of SkyWars Teams commands\n".
                    "§7/swt create : Create SkyWars Teams arena\n".
                    "§7/swt remove : Remove SkyWars Teams arena\n".
                    "§7/swt set : Set SkyWars Teams arena\n".
                    "§7/swt arenas : Displays list of arenas");

                break;
            case "create":
                if(!$sender->hasPermission("sw.cmd.create")) {
                    $sender->sendMessage("§cYou have not permissions to use this command!");
                    break;
                }
                if(!isset($args[1])) {
                    $sender->sendMessage("§cUsage: §7/swt create <arenaName>");
                    break;
                }
                if(isset($this->plugin->arenas[$args[1]])) {
                    $sender->sendMessage("§c> Arena $args[1] already exists!");
                    break;
                }
                $this->plugin->arenas[$args[1]] = new Arena($this->plugin, []);
                $sender->sendMessage("§a> Arena $args[1] created!");
                break;
            case "remove":
                if(!$sender->hasPermission("sw.cmd.remove")) {
                    $sender->sendMessage("§cYou have not permissions to use this command!");
                    break;
                }
                if(!isset($args[1])) {
                    $sender->sendMessage("§cUsage: §7/sw remove <arenaName>");
                    break;
                }
                if(!isset($this->plugin->arenas[$args[1]])) {
                    $sender->sendMessage("§c> Arena $args[1] was not found!");
                    break;
                }

                /** @var Arena $arena */
                $arena = $this->plugin->arenas[$args[1]];

                foreach ($arena->players as $player) {
                    $player->teleport($this->plugin->getServer()->getDefaultLevel()->getSpawnLocation());
                }

                if(is_file($file = $this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $args[1] . ".yml")) unlink($file);
                unset($this->plugin->arenas[$args[1]]);

                $sender->sendMessage("§a> Arena removed!");
                break;
            case "set":
                if(!$sender->hasPermission("sw.cmd.set")) {
                    $sender->sendMessage("§cYou have not permissions to use this command!");
                    break;
                }
                if(!$sender instanceof Player) {
                    $sender->sendMessage("§c> This command can be used only in-game!");
                    break;
                }
                if(!isset($args[1])) {
                    $sender->sendMessage("§cUsage: §7/swt set <arenaName>");
                    break;
                }
                if(isset($this->plugin->setters[$sender->getName()])) {
                    $sender->sendMessage("§c> You are already in setup mode!");
                    break;
                }
                if(!isset($this->plugin->arenas[$args[1]])) {
                    $sender->sendMessage("§c> Arena $args[1] does not found!");
                    break;
                }
                $sender->sendMessage("§a> You are joined setup mode.\n".
                    "§7- use §lhelp §r§7to display available commands\n"  .
                    "§7- or §ldone §r§7to leave setup mode");
                $this->plugin->setters[$sender->getName()] = $this->plugin->arenas[$args[1]];
                break;
            case "arenas":
                if(!$sender->hasPermission("sw.cmd.arenas")) {
                    $sender->sendMessage("§cYou have not permissions to use this command!");
                    break;
                }
                if(count($this->plugin->arenas) === 0) {
                    $sender->sendMessage("§6> There are 0 arenas.");
                    break;
                }
                $list = "§7> Arenas:\n";
                foreach ($this->plugin->arenas as $name => $arena) {
                    if($arena->setup) {
                        $list .= "§7- $name : §cdisabled\n";
                    }
                    else {
                        $list .= "§7- $name : §aenabled\n";
                    }
                }
                $sender->sendMessage($list);
                break;
            default:
                if(!$sender->hasPermission("sw.cmd.help")) {
                    $sender->sendMessage("§cYou have not permissions to use this command!");
                    break;
                }
                $sender->sendMessage("§cUsage: §7/swt help");
                break;
        }

    }

    /**
     * @return SkyWars|Plugin $plugin
     */
    public function getPlugin(): Plugin {
        return $this->plugin;
    }

}
