
<a align="center"><img src="https://image.ibb.co/m90xoy/sw.png"></img></a>

<div align="center">
	<a href="https://discord.gg/uwBf2jS">
        <img src="https://img.shields.io/badge/chat-on%20discord-7289da.svg" alt="discord">
    </a>
    <a href="https://github.com/GamakCZ/SkyWars/blob/master/LICENSE">
        <img src="https://img.shields.io/badge/license-Apache%20License%202.0-yellowgreen.svg" alt="license">
    </a>
    <a href="https://poggit.pmmp.io/ci/GamakCZ/SkyWars/SkyWars">
        <img src="https://poggit.pmmp.io/ci.shield/GamakCZ/SkyWars/SkyWars" alt="poggit-ci">
    </a>
    <br><br>
    ✔️ Simple setup
    <br>
    ✔️Multi arena support
    <br>
    ✔️ Fast, without lags
    <br>
    ✔️ Last PocketMine API support
    <br>
    ✔️ Map reset
    <br>
    ✔️ Chest refill
    <br>
</div>

### Releases:

| Version | Zip Download | Phar Download |
| --- | --- | --- |
| 1.0.0 | [GitHub](https://github.com/GamakCZ/SkyWars/archive/1.0.0.zip) | [GitHub](https://github.com/GamakCZ/SkyWars/releases/download/1.0.0/SkyWars_v1.0.0.phar) |
<br>

- **Other released versions [here](https://github.com/GamakCZ/SkyWars/releases)**
- **All developement builds on poggit [here](https://poggit.pmmp.io/ci/GamakCZ/SkyWars/SkyWars)**

<div align="center">
	<h2>How to setup?</h2>
</div>

 - <h3>Installation:</h3>
 1. Download latest release or sucess. build
 2. Upload it to your server folder /plugins/
 3. Restart the server

-  <h3>Create and setup an arena:</h3>
1. Create an arena using `/sw create <arenaName>`
2. Join the setup mode (command `/sw set <arenaName>`)
3. There are setup commands (they are without `/`), you can use them to set the arena

- <h3>Video:</h3>

<a align="center" href="https://www.youtube.com/watch?v=3tbhWPUFe1c"><img src="http://img.youtube.com/vi/3tbhWPUFe1c/0.jpg"></a>

- _Setup commands_:

| Command | Description |
| --- | --- |
| help | Displays all setup commands |
| done | Is used to exit setup mode |
| slots `<slots>` | Sets arena slots |
| level `<levelName>` | Sets arena game level |
| spawn `<spawnNum.>` | Sets arena spawn position |
| joinsign | Update joinsign |
| enable | Enable the arena |

<div align="center">
	<h2>Commands:</h2>
</div>
<br>

<p align="center">  

```yaml
Commands:
    /sw help:
        Description: Displays all SkyWars commands
        Permission: sw.cmd.help (OP)
    /sw create:
        Description: Create new arena
        Permission: sw.cmd.create (OP)
        Usage: /sw set <arenaName>
    /sw remove:
        Description: Remove arena
        Permission: sw.cmd.remove (OP)
        Usage: /sw remove <arenaName>
        Note: Changes will be after restart
    /sw set:
        Description: Command allows setup arena
        Permission: sw.cmd.set (OP)
        Usage: /sw set <arenaName>
        Note: This command can be used only in-game
    /sw arenas:
        Description: Displays list of all arenas
        Permission: sw.cmd.arenas (OP)
```
</p>

<div align="center">
	<h2>Permissions</h2>
</div>
<br>

<p align="center">

```yaml
sw.cmd:  
    description: Permission to all SkyWars commands  
    default: op  
    children:  
        sw.cmd.help:
            description: Permission for /sw help  
            default: op  
        sw.cmd.create:  
            description: Permission for /sw create  
            default: op
        sw.cmd.remove:
            description: Permission for /sw remove
            default: op
        sw.cmd.set:  
            description: Permission for /sw set  
            default: op  
        sw.cmd.arenas:  
            description: Permission for /sw arenas  
            default: op    
			
```
</p>

<div align="center">
	<h2>API</h2>
</div>
<br>

<h3>Events:</h3>

- [PlayerArenaWinEvent](https://github.com/GamakCZ/SkyWars/blob/master/SkyWars/src/skywars/event/PlayerArenaWinEvent.php)

```php
/**  
 * Arena constructor.
 * @param Server $server  
 * @param Plugin $plugin  
 */
 public function __construct(Server $server, Plugin $plugin) {  
    $server->getPluginManager()->registerEvents($this, $plugin);  
 }  
  
/**  
 * @param PlayerArenaWinEvent $event  
 */
 public function onWin(PlayerArenaWinEvent $event) {  
    $player = $event->getPlayer();  
    $this->addCoins($player, 100);  
    $player->sendMessage("§a> You won 100 coins!");  
 }  
		
/**  
 * @param Player $player  
 * @param int $coins  
 */
 public function addCoins(Player $player, int $coins) {}
```

<div align="center">
    <h2>Credits</h2>
</div>

<div align="center">
    - Icon made by <a href="http://www.freepik.com" title="Freepik">Freepik</a> from <a href="https://www.flaticon.com/" title="Flaticon">www.flaticon.com</a> is licensed by <a href="http://creativecommons.org/licenses/by/3.0/" title="Creative Commons BY 3.0" target="_blank">CC 3.0 BY</a>
</div>
