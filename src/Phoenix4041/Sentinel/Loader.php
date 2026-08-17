<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel;

use muqsit\invmenu\InvMenuHandler;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\EventPriority;
use pocketmine\plugin\PluginBase;

use Phoenix4041\Sentinel\command\StaffCommand;
use Phoenix4041\Sentinel\command\UnbanCommand;
use Phoenix4041\Sentinel\database\DatabaseManager;
use Phoenix4041\Sentinel\listener\BlockListener;
use Phoenix4041\Sentinel\listener\CommandListener;
use Phoenix4041\Sentinel\listener\ContainerListener;
use Phoenix4041\Sentinel\listener\PlayerListener;
use Phoenix4041\Sentinel\listener\PlayerRegistryListener;
use Phoenix4041\Sentinel\manager\ConfigManager;
use Phoenix4041\Sentinel\manager\GlowManager;
use Phoenix4041\Sentinel\manager\MessageManager;
use Phoenix4041\Sentinel\manager\ModeManager;
use Phoenix4041\Sentinel\manager\PlayerRegistry;
use Phoenix4041\Sentinel\manager\SanctionManager;
use Phoenix4041\Sentinel\menu\InventoryMenu;

/**
 * Single bootstrap for Sentinel: loads resources, saves default config,
 * wires up every manager/listener/command and owns the plugin lifecycle.
 */
final class Loader extends PluginBase {

    private static ?self $instance = null;

    private PlayerRegistry $playerRegistry;
    private ConfigManager $configManager;
    private MessageManager $messageManager;
    private GlowManager $glowManager;
    private ModeManager $modeManager;
    private DatabaseManager $databaseManager;
    private SanctionManager $sanctionManager;
    private InventoryMenu $inventoryMenu;

    public static function getInstance(): self {
        if (self::$instance === null) {
            throw new \RuntimeException("Sentinel Loader has not been initialized yet");
        }
        return self::$instance;
    }

    protected function onLoad(): void {
        self::$instance = $this;
    }

    protected function onEnable(): void {
        $this->saveDefaultConfig();
        $this->saveResource("messages.yml");

        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }

        $this->initializeManagers();
        $this->registerCommands();
        $this->registerListeners();
    }

    protected function onDisable(): void {
        if (isset($this->modeManager)) {
            $this->modeManager->disableAll();
        }
        if (isset($this->glowManager)) {
            $this->glowManager->removeAllGlow();
        }
        if (isset($this->sanctionManager)) {
            $this->sanctionManager->saveBans();
            $this->sanctionManager->saveMutes();
        }
        if (isset($this->databaseManager)) {
            $this->databaseManager->close();
        }

        self::$instance = null;
    }

    private function initializeManagers(): void {
        $this->playerRegistry = new PlayerRegistry();
        $this->configManager = new ConfigManager($this);
        $this->messageManager = new MessageManager($this);

        $this->glowManager = new GlowManager($this->playerRegistry);
        $this->modeManager = new ModeManager($this->glowManager, $this->playerRegistry, $this->messageManager, $this->configManager);

        $this->databaseManager = new DatabaseManager($this);
        $this->databaseManager->initialize();

        $this->sanctionManager = new SanctionManager($this, $this->playerRegistry);
        $this->inventoryMenu = new InventoryMenu($this);
    }

    private function registerCommands(): void {
        $commandMap = $this->getServer()->getCommandMap();

        $commandMap->register("sentinel", new StaffCommand($this));
        $commandMap->register("sentinel", new UnbanCommand($this));
    }

    private function registerListeners(): void {
        $pm = $this->getServer()->getPluginManager();

        $pm->registerEvents(new PlayerRegistryListener($this->playerRegistry), $this);
        $pm->registerEvents(new BlockListener($this), $this);
        $pm->registerEvents(new ContainerListener($this), $this);
        $pm->registerEvents(new CommandListener($this), $this);

        $playerListener = new PlayerListener($this);
        $pm->registerEvents($playerListener, $this);

        $pm->registerEvent(
            EntityDamageEvent::class,
            function (EntityDamageEvent $event) use ($playerListener): void {
                $playerListener->onEntityDamageLowest($event);
            },
            EventPriority::LOWEST,
            $this,
            false
        );

        $pm->registerEvent(
            EntityDamageEvent::class,
            function (EntityDamageEvent $event) use ($playerListener): void {
                $playerListener->onEntityDamageMonitor($event);
            },
            EventPriority::MONITOR,
            $this,
            false
        );
    }

    public function getPlayerRegistry(): PlayerRegistry {
        return $this->playerRegistry;
    }

    public function getConfigManager(): ConfigManager {
        return $this->configManager;
    }

    public function getMessageManager(): MessageManager {
        return $this->messageManager;
    }

    public function getGlowManager(): GlowManager {
        return $this->glowManager;
    }

    public function getModeManager(): ModeManager {
        return $this->modeManager;
    }

    public function getDatabaseManager(): DatabaseManager {
        return $this->databaseManager;
    }

    public function getSanctionManager(): SanctionManager {
        return $this->sanctionManager;
    }

    public function getInventoryMenu(): InventoryMenu {
        return $this->inventoryMenu;
    }
}
