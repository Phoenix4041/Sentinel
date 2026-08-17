<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\inspector\manager;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\type\InvMenuTypeIds;
use Phoenix4041\Sentinel\inspector\data\InspectorData;
use Phoenix4041\Sentinel\inspector\form\TeleportForm;
use Phoenix4041\Sentinel\Loader;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\player\Player;

class InspectorManager {

    private Loader $plugin;
    private array $inspectorData = [];
    private array $frozenPlayers = [];
    private array $vanishedPlayers = [];
    private array $staffChatEnabled = [];
    private array $viewingInventory = [];
    private array $cooldowns = [];
    private array $savedPositions = [];
    private array $savedInventories = [];
    private const COOLDOWN_CLEANUP_INTERVAL = 3600;
    private int $lastCleanup = 0;

    public function __construct(Loader $plugin) {
        $this->plugin = $plugin;
        $this->lastCleanup = time();
    }

    public function isInspectorMode(Player $player): bool {
        return isset($this->inspectorData[$player->getName()]);
    }

    public function enableInspectorMode(Player $player): void {
        $playerName = $player->getName();

        if ($this->isInspectorMode($player)) {
            return;
        }

        $this->savedPositions[$playerName] = $player->getPosition();

        $inventoryContents = $player->getInventory()->getContents();
        $armorContents = $player->getArmorInventory()->getContents();

        $this->savedInventories[$playerName] = [
            'inventory' => $inventoryContents,
            'armor' => $armorContents
        ];

        $data = new InspectorData(
            $player->getGamemode(),
            $player->getAllowFlight(),
            $player->isFlying(),
            false,
            $inventoryContents
        );

        $this->inspectorData[$playerName] = $data;

        $player->setGamemode(GameMode::SURVIVAL());
        $player->setAllowFlight(true);
        $player->setFlying(true);

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();

        $this->equipInspectorTools($player);

        $this->plugin->getMessageManager()->sendToast($player, "inspector-mode-on");
    }

    public function disableInspectorMode(Player $player): void {
        $playerName = $player->getName();

        if (!isset($this->inspectorData[$playerName])) {
            return;
        }

        $data = $this->inspectorData[$playerName];

        if ($this->isVanished($player)) {
            $this->toggleVanish($player);
        }

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();

        if (isset($this->savedInventories[$playerName])) {
            $saved = $this->savedInventories[$playerName];

            if (isset($saved['inventory'])) {
                $player->getInventory()->setContents($saved['inventory']);
            }

            if (isset($saved['armor'])) {
                $player->getArmorInventory()->setContents($saved['armor']);
            }

            unset($this->savedInventories[$playerName]);
        }

        $player->setGamemode($data->getGameMode());
        $player->setAllowFlight($data->getAllowFlight());

        if ($data->getAllowFlight() && $data->isFlying()) {
            $player->setFlying(true);
        } else {
            $player->setFlying(false);
        }

        if (isset($this->savedPositions[$playerName])) {
            $savedPosition = $this->savedPositions[$playerName];

            if ($savedPosition->isValid()) {
                $player->teleport($savedPosition);
            }

            unset($this->savedPositions[$playerName]);
        }

        unset($this->inspectorData[$playerName]);

        $this->plugin->getMessageManager()->sendToast($player, "inspector-mode-off");
    }

    public function disableAllInspectorModes(): void {
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            if ($this->isInspectorMode($player)) {
                $this->disableInspectorMode($player);
            }
        }
    }

    private function equipInspectorTools(Player $player): void {
        $inventory = $player->getInventory();

        $inventory->setItem(0, VanillaItems::PAPER()->setCustomName("§r§8[§eCMD§f-§eHISTORY§8]§r"));
        $inventory->setItem(1, VanillaItems::ENDER_PEARL()->setCustomName("§8[§dInspector§8]§r"));
        $inventory->setItem(2, VanillaItems::COMPASS()->setCustomName("§r§8[§bTELEPORT§8]§r"));
        $inventory->setItem(3, VanillaBlocks::CHEST()->asItem()->setCustomName("§r§8[§eINVENTORY§8]§r"));
        $inventory->setItem(4, VanillaBlocks::ENDER_CHEST()->asItem()->setCustomName("§r§8[§dENDER§f-§dINV§8]§r"));
        $inventory->setItem(5, VanillaItems::DYE()->setColor(\pocketmine\block\utils\DyeColor::LIME())->setCustomName("§r§8[§aVANISH§8]§r"));
        $inventory->setItem(7, VanillaItems::STICK()->setCustomName("§8[§cPLAYER§f-§cBAN§8]§r"));
        $inventory->setItem(8, VanillaItems::DYE()->setColor(\pocketmine\block\utils\DyeColor::LIGHT_BLUE())->setCustomName("§r§8[§bFREEZE§8]§r"));
    }

    public function toggleVanish(Player $player): void {
        if (!$this->checkCooldown($player, "vanish", 1.0)) {
            return;
        }

        $playerName = $player->getName();
        $isVanished = $this->isVanished($player);

        if ($isVanished) {
            unset($this->vanishedPlayers[$playerName]);

            foreach ($this->plugin->getServer()->getOnlinePlayers() as $onlinePlayer) {
                $onlinePlayer->showPlayer($player);
            }

            $player->getInventory()->setItem(5, VanillaItems::DYE()->setColor(\pocketmine\block\utils\DyeColor::LIME())->setCustomName("§r§8[§aVANISH§8]§r"));
            $this->plugin->getMessageManager()->sendToast($player, "vanish-off");
        } else {
            $this->vanishedPlayers[$playerName] = true;

            foreach ($this->plugin->getServer()->getOnlinePlayers() as $onlinePlayer) {
                if ($onlinePlayer->getName() !== $playerName && !$onlinePlayer->hasPermission("inspector.command.access")) {
                    $onlinePlayer->hidePlayer($player);
                }
            }

            $player->getInventory()->setItem(5, VanillaItems::DYE()->setColor(\pocketmine\block\utils\DyeColor::RED())->setCustomName("§r§8[§cVANISH§8]§r"));
            $this->plugin->getMessageManager()->sendToast($player, "vanish-on");
        }
    }

    public function isVanished(Player $player): bool {
        return isset($this->vanishedPlayers[$player->getName()]);
    }

    public function handlePlayerJoin(Player $player): void {
        // PM4->PM5 fix: previously used $this->plugin->getServer()->getPlayerExact($vanishedName)
        // here. Replaced with the shared PlayerRegistry lookup to avoid the
        // forbidden getPlayerExact() pattern.
        $registry = $this->plugin->getPlayerRegistry();

        foreach (array_keys($this->vanishedPlayers) as $vanishedName) {
            $vanishedPlayer = $registry->getByName($vanishedName);

            if ($vanishedPlayer !== null && !$player->hasPermission("inspector.command.access")) {
                $player->hidePlayer($vanishedPlayer);
            }
        }
    }

    public function toggleFreeze(Player $player, Player $target): void {
        if (!$this->checkCooldown($player, "freeze", 0.5)) {
            return;
        }

        $targetName = $target->getName();

        if ($this->isFrozen($target)) {
            unset($this->frozenPlayers[$targetName]);

            $this->plugin->getMessageManager()->broadcastToast("unfrozen", ["player" => $targetName]);

            $target->sendTip("§r§8[§cUN§f-§cFROZEN§8]§r");

            $this->plugin->getMessageManager()->sendToast($player, "notification-player-unfrozen", ["player" => $targetName]);
        } else {
            $this->frozenPlayers[$targetName] = true;

            $this->plugin->getMessageManager()->broadcastToast("frozen", ["player" => $targetName]);

            $target->sendTip("§r§8[§bFROZEN§8]§r");

            $this->plugin->getMessageManager()->sendToast($player, "notification-player-frozen", ["player" => $targetName]);
        }
    }

    public function isFrozen(Player $player): bool {
        return isset($this->frozenPlayers[$player->getName()]);
    }

    public function enableStaffChat(Player $player): void {
        $this->staffChatEnabled[$player->getName()] = true;
        $this->plugin->getMessageManager()->sendToast($player, "staff-chat-enabled");
    }

    public function disableStaffChat(Player $player): void {
        unset($this->staffChatEnabled[$player->getName()]);
        $this->plugin->getMessageManager()->sendToast($player, "staff-chat-disabled");
    }

    public function isStaffChatEnabled(Player $player): bool {
        return isset($this->staffChatEnabled[$player->getName()]);
    }

    public function broadcastStaffMessage(Player $sender, string $message): void {
        $formattedMessage = $this->plugin->getMessageManager()->getRawMessage("staff-chat-format", [
            "player" => $sender->getName(),
            "message" => $message
        ]);

        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            if ($player->hasPermission("inspector.command.access")) {
                $player->sendMessage($formattedMessage);
            }
        }
    }

    public function openTeleportForm(Player $player): void {
        $player->sendForm(new TeleportForm($this->plugin, $player));
    }

    public function openPlayerInventory(Player $inspector, Player $target): void {
        if (!$inspector->isConnected() || !$target->isConnected()) {
            return;
        }

        try {
            $this->viewingInventory[$inspector->getName()] = [
                'target' => $target->getName(),
                'type' => 'inventory'
            ];

            $menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
            $menu->setName("§6" . $target->getName() . "'s Inventory");

            $inventory = $menu->getInventory();
            $inventory->clearAll();

            $contents = $target->getInventory()->getContents();
            $inventory->setContents($contents);

            $armorInventory = $target->getArmorInventory();
            $helmet = $armorInventory->getHelmet() ?? VanillaBlocks::AIR()->asItem();
            $chestplate = $armorInventory->getChestplate() ?? VanillaBlocks::AIR()->asItem();
            $leggings = $armorInventory->getLeggings() ?? VanillaBlocks::AIR()->asItem();
            $boots = $armorInventory->getBoots() ?? VanillaBlocks::AIR()->asItem();
            $separator = VanillaBlocks::STAINED_GLASS_PANE()->setColor(\pocketmine\block\utils\DyeColor::RED())->asItem()->setCustomName("§r");

            $inventory->setItem(45, $separator);
            $inventory->setItem(46, $separator);
            $inventory->setItem(47, $helmet);
            $inventory->setItem(48, $chestplate);
            $inventory->setItem(49, $separator);
            $inventory->setItem(50, $leggings);
            $inventory->setItem(51, $boots);
            $inventory->setItem(52, $separator);
            $inventory->setItem(53, $separator);

            $menu->setListener(InvMenu::readonly());

            $menu->setInventoryCloseListener(function(Player $player) use ($inspector): void {
                if ($player->getName() === $inspector->getName()) {
                    $this->stopViewingInventory($inspector);
                }
            });

            $menu->send($inspector);

            $this->plugin->getMessageManager()->sendToast($inspector, "inventory-opened", [
                "player" => $target->getName()
            ]);
        } catch (\Exception $e) {
            $inspector->sendMessage("§cError opening inventory: " . $e->getMessage());
        }
    }

    public function openPlayerEnderInventory(Player $inspector, Player $target): void {
        if (!$inspector->isConnected() || !$target->isConnected()) {
            return;
        }

        try {
            $this->viewingInventory[$inspector->getName()] = [
                'target' => $target->getName(),
                'type' => 'ender'
            ];

            $menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
            $menu->setName("§6" . $target->getName() . "'s Ender Inventory");

            $inventory = $menu->getInventory();
            $inventory->clearAll();

            $enderContents = $target->getEnderInventory()->getContents();
            $inventory->setContents($enderContents);

            $menu->setListener(InvMenu::readonly());

            $menu->setInventoryCloseListener(function(Player $player) use ($inspector): void {
                if ($player->getName() === $inspector->getName()) {
                    $this->stopViewingInventory($inspector);
                }
            });

            $menu->send($inspector);

            $this->plugin->getMessageManager()->sendToast($inspector, "ender-inventory-opened", [
                "player" => $target->getName()
            ]);
        } catch (\Exception $e) {
            $inspector->sendMessage("§cError opening ender inventory: " . $e->getMessage());
        }
    }

    public function isViewingInventory(Player $player): bool {
        return isset($this->viewingInventory[$player->getName()]);
    }

    public function stopViewingInventory(Player $player): void {
        unset($this->viewingInventory[$player->getName()]);
    }

    private function checkCooldown(Player $player, string $action, float $seconds): bool {
        $name = $player->getName();
        $key = $name . "_" . $action;
        $currentTime = microtime(true);

        if ($currentTime - $this->lastCleanup > self::COOLDOWN_CLEANUP_INTERVAL) {
            $this->cleanupCooldowns($currentTime);
            $this->lastCleanup = (int)$currentTime;
        }

        if (isset($this->cooldowns[$key]) && ($currentTime - $this->cooldowns[$key]) < $seconds) {
            return false;
        }

        $this->cooldowns[$key] = $currentTime;
        return true;
    }

    private function cleanupCooldowns(float $currentTime): void {
        $threshold = $currentTime - 300;
        foreach ($this->cooldowns as $key => $time) {
            if ($time < $threshold) {
                unset($this->cooldowns[$key]);
            }
        }
    }

    public function isInspectorTool(Item $item): bool {
        $name = $item->getCustomName();
        return in_array($name, [
            "§r§8[§eCMD§f-§eHISTORY§8]§r",
            "§8[§dInspector§8]§r",
            "§r§8[§bTELEPORT§8]§r",
            "§r§8[§eINVENTORY§8]§r",
            "§r§8[§dENDER§f-§dINV§8]§r",
            "§r§8[§aVANISH§8]§r",
            "§r§8[§cVANISH§8]§r",
            "§r§8[§bFREEZE§8]§r",
            "§8[§cPLAYER§f-§cBAN§8]§r"
        ], true);
    }

    public function handlePlayerQuit(Player $player): void {
        $name = $player->getName();

        if (isset($this->viewingInventory[$name])) {
            $this->stopViewingInventory($player);
        }

        unset($this->inspectorData[$name]);
        unset($this->frozenPlayers[$name]);
        unset($this->vanishedPlayers[$name]);
        unset($this->staffChatEnabled[$name]);
        unset($this->viewingInventory[$name]);
        unset($this->savedPositions[$name]);
        unset($this->savedInventories[$name]);

        foreach (array_keys($this->cooldowns) as $key) {
            if (str_starts_with($key, $name . "_")) {
                unset($this->cooldowns[$key]);
            }
        }
    }
}
