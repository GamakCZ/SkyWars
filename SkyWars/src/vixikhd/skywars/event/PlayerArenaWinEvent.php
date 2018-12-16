<?php

declare(strict_types=1);

namespace vixikhd\skywars\event;

use pocketmine\event\plugin\PluginEvent;
use pocketmine\Player;
use vixikhd\skywars\arena\Arena;
use vixikhd\skywars\SkyWars;

/**
 * Class PlayerArenaWinEvent
 * @package skywars\event
 */
class PlayerArenaWinEvent extends PluginEvent {

    /** @var null $handlerList */
    public static $handlerList = \null;

    /** @var Player $player */
    protected $player;

    /** @var Arena $arena */
    protected $arena;

    /**
     * PlayerArenaWinEvent constructor.
     * @param SkyWars $plugin
     * @param Player $player
     * @param Arena $arena
     */
    public function __construct(SkyWars $plugin, Player $player, Arena $arena) {
        $this->player = $player;
        $this->arena = $arena;
        parent::__construct($plugin);
    }

    /**
     * @return Player $arena
     */
    public function getPlayer(): Player {
        return $this->player;
    }

    /**
     * @return Arena $arena
     */
    public function getArena(): Arena {
        return $this->arena;
    }
}