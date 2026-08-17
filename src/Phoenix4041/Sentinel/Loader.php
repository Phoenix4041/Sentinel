<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel;

use muqsit\invmenu\InvMenuHandler;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\EventPriority;
use pocketmine\plugin\PluginBase;

use Phoenix4041\Sentinel\manager\PlayerRegistry;
use Phoenix4041\Sentinel\manager\PlayerRegistryListener;

use Phoenix4041\Sentinel\staff\manager\StaffManager;
use Phoenix4041\Sentinel\staff\manager\DataManager;
use Phoenix4041\Sentinel\staff\manager\GlowManager;
use Phoenix4041\Sentinel\staff\command\StaffCommand;
use Phoenix4041\Sentinel\staff\listener\StaffListener;
use Phoenix4041\Sentinel\staff\listener\DataListener;
use Phoenix4041\Sentinel\staff\listener\CommandListener as StaffCommandListener;

use Phoenix4041\Sentinel\inspector\command\InspectorCommand;
use Phoenix4041\Sentinel\inspector\command\UnbanCommand;
use Phoenix4041\Sentinel\inspector\database\DatabaseManager;
use Phoenix4041\Sentinel\inspector\listener\BlockListener;
use Phoenix4041\Sentinel\inspector\listener\CommandListener as InspectorCommandListener;
use Phoenix4041\Sentinel\inspector\listener\ContainerListener;
use Phoenix4041\Sentinel\inspector\listener\PlayerListener;
use Phoenix4041\Sentinel\inspector\manager\ConfigManager;
use Phoenix4041\Sentinel\inspector\manager\InspectorManager;
use Phoenix4041\Sentinel\inspector\manager\MessageManager;
use Phoenix4041\Sentinel\inspector\manager\SanctionManager;

/**
 * Single bootstrap for the merged Sentinel plugin (EpicStaff + Inspector).
 *
 * Mirrors what each original Loader.php did in isolation - loading
 * resources, saving default config, registering listeners/commands and
 * initializing managers - but unified into one plugin lifecycle.
 */
final class Loader extends PluginBase {

    private static ?self $instance = null;

    private PlayerRegistry $playerRegistry;

    // staff module
    private StaffManager $staffManager;
    private DataManager $dataManager;
    private GlowManager $glowManager;

    // inspector module
    private DatabaseManager $databaseManager;
    private InspectorManager $inspectorManager;
    private MessageManager $messageManager;
    private ConfigManager $configManager;
    private SanctionManager $sanctionManager;

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
        // Staff module cleanup
        if (isset($this->staffManager)) {
            $this->staffManager->disableAll();
        }
        if (isset($this->glowManager)) {
            $this->glowManager->removeAllGlow();
        }
        if (isset($this->dataManager)) {
            $this->dataManager->save();
        }

        // Inspector module cleanup
        if (isset($this->inspectorManager)) {
            $this->inspectorManager->disableAllInspectorModes();
        }
        if (isset($this->databaseManager)) {
            $this->databaseManager->close();
        }
        if (isset($this->sanctionManager)) {
            $this->sanctionManager->saveBans();
            $this->sanctionManager->saveMutes();
        }

        self::$instance = null;
    }

    private function initializeManagers(): void {
        $this->playerRegistry = new PlayerRegistry();

        // Staff module
        $this->dataManager = new DataManager($this);
        $this->glowManager = new GlowManager($this->playerRegistry);
        $this->staffManager = new StaffManager($this->glowManager, $this->playerRegistry);

        // Inspector module
        $this->configManager = new ConfigManager($this);
        $this->messageManager = new MessageManager($this);
        $this->databaseManager = new DatabaseManager($this);
        $this->inspectorManager = new InspectorManager($this);
        $this->sanctionManager = new SanctionManager($this, $this->playerRegistry);

        $this->databaseManager->initialize();
    }

    private function registerCommands(): void {
        $commandMap = $this->getServer()->getCommandMap();

        // Staff module
        $commandMap->register("epicstaff", new StaffCommand($this->staffManager));

        // Inspector module
        $commandMap->register("inspector", new InspectorCommand($this));
        $commandMap->register("unban", new UnbanCommand($this));
    }

    private function registerListeners(): void {
        $pm = $this->getServer()->getPluginManager();

        // Shared
        $pm->registerEvents(new PlayerRegistryListener($this->playerRegistry), $this);

        // Staff module
        $pm->registerEvents(new StaffListener($this->staffManager), $this);
        $pm->registerEvents(new DataListener($this->dataManager), $this);
        $pm->registerEvents(new StaffCommandListener($this->staffManager, $this->dataManager), $this);

        // Inspector module
        $playerListener = new PlayerListener($this);

        $pm->registerEvents($playerListener, $this);
        $pm->registerEvents(new BlockListener($this), $this);
        $pm->registerEvents(new ContainerListener($this), $this);
        $pm->registerEvents(new InspectorCommandListener($this), $this);

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

    // Staff module accessors

    public function getStaffManager(): StaffManager {
        return $this->staffManager;
    }

    public function getDataManager(): DataManager {
        return $this->dataManager;
    }

    public function getGlowManager(): GlowManager {
        return $this->glowManager;
    }

    // Inspector module accessors

    public function getDatabaseManager(): DatabaseManager {
        return $this->databaseManager;
    }

    public function getInspectorManager(): InspectorManager {
        return $this->inspectorManager;
    }

    public function getMessageManager(): MessageManager {
        return $this->messageManager;
    }

    public function getConfigManager(): ConfigManager {
        return $this->configManager;
    }

    public function getSanctionManager(): SanctionManager {
        return $this->sanctionManager;
    }
}
