<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\inspector\listener;

use Phoenix4041\Sentinel\inspector\form\SanctionForm;
use Phoenix4041\Sentinel\Loader;
use pocketmine\entity\projectile\EnderPearl;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;

class PlayerListener implements Listener {

    private Loader $plugin;
    private array $lastToolUse = [];

    public function __construct(Loader $plugin) {
        $this->plugin = $plugin;
    }

    public function onPlayerLogin(PlayerLoginEvent $event): void {
        $player = $event->getPlayer();

        if ($this->plugin->getSanctionManager()->isBanned($player->getName())) {
            $this->plugin->getSanctionManager()->kickBannedPlayer($player);
            $event->cancel();
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $manager = $this->plugin->getInspectorManager();

        if ($manager->isInspectorMode($player)) {
            $manager->disableInspectorMode($player);
        }

        $manager->handlePlayerQuit($player);
        unset($this->lastToolUse[$player->getName()]);
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $this->plugin->getInspectorManager()->handlePlayerJoin($event->getPlayer());
    }

    public function onPlayerChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $manager = $this->plugin->getInspectorManager();

        if ($manager->isStaffChatEnabled($player)) {
            $event->cancel();
            $manager->broadcastStaffMessage($player, $event->getMessage());
        }
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();

        if (!$this->plugin->getInspectorManager()->isFrozen($player)) {
            return;
        }

        $from = $event->getFrom();
        $to = $event->getTo();

        if ($from->x !== $to->x || $from->y !== $to->y || $from->z !== $to->z) {
            $event->cancel();
        }
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $manager = $this->plugin->getInspectorManager();

        if (!$manager->isInspectorMode($player)) {
            return;
        }

        $item = $event->getItem();
        $itemName = $item->getCustomName();

        if ($itemName === "§r§8[§aVANISH§8]§r" || $itemName === "§r§8[§cVANISH§8]§r") {
            $event->cancel();
            $manager->toggleVanish($player);
            return;
        }

        if ($itemName === "§8[§cPLAYER§f-§cBAN§8]§r") {
            $event->cancel();
            $player->sendForm(new SanctionForm($this->plugin));
            return;
        }

        if ($itemName === "§r§8[§bTELEPORT§8]§r") {
            $event->cancel();
            $manager->openTeleportForm($player);
            return;
        }
    }

    public function onPlayerItemUse(PlayerItemUseEvent $event): void {
        $player = $event->getPlayer();
        $manager = $this->plugin->getInspectorManager();

        if ($manager->isFrozen($player)) {
            $event->cancel();
            return;
        }

        if ($manager->isInspectorMode($player)) {
            $item = $event->getItem();
            $itemName = $item->getCustomName();

            if ($itemName === "§r§8[§aVANISH§8]§r" || $itemName === "§r§8[§cVANISH§8]§r") {
                $event->cancel();
                $manager->toggleVanish($player);
                return;
            }

            if ($itemName === "§8[§cPLAYER§f-§cBAN§8]§r") {
                $event->cancel();
                $player->sendForm(new SanctionForm($this->plugin));
                return;
            }

            if ($itemName === "§r§8[§bTELEPORT§8]§r") {
                $event->cancel();
                $manager->openTeleportForm($player);
                return;
            }

            if (!$manager->isInspectorTool($item)) {
                $event->cancel();
            }
        }
    }

    public function onPlayerDropItem(PlayerDropItemEvent $event): void {
        $player = $event->getPlayer();
        $manager = $this->plugin->getInspectorManager();

        if ($manager->isInspectorMode($player) || $manager->isFrozen($player)) {
            $event->cancel();
        }
    }

    public function onEntityItemPickup(EntityItemPickupEvent $event): void {
        $entity = $event->getEntity();

        if (!$entity instanceof Player) {
            return;
        }

        $manager = $this->plugin->getInspectorManager();

        if ($manager->isInspectorMode($entity) || $manager->isFrozen($entity)) {
            $event->cancel();
        }
    }

    public function onProjectileLaunch(ProjectileLaunchEvent $event): void {
        $entity = $event->getEntity();

        if (!$entity instanceof EnderPearl) {
            return;
        }

        $shooter = $entity->getOwningEntity();

        if (!$shooter instanceof Player) {
            return;
        }

        $manager = $this->plugin->getInspectorManager();

        if ($manager->isInspectorMode($shooter) || $manager->isFrozen($shooter)) {
            $event->cancel();
        }
    }

    /**
     * @priority LOWEST
     * @ignoreCancelled false
     */
    public function onEntityDamageLowest(EntityDamageEvent $event): void {
        if (!$event instanceof EntityDamageByEntityEvent) {
            return;
        }

        $entity = $event->getEntity();
        $damager = $event->getDamager();

        if (!$entity instanceof Player || !$damager instanceof Player) {
            return;
        }

        $manager = $this->plugin->getInspectorManager();

        if (!$manager->isInspectorMode($damager)) {
            return;
        }

        $item = $damager->getInventory()->getItemInHand();
        $itemName = $item->getCustomName();

        if (!$this->isInspectorActionTool($itemName)) {
            return;
        }

        $currentTime = microtime(true);
        $playerName = $damager->getName();
        $lastUse = $this->lastToolUse[$playerName] ?? 0;

        if (($currentTime - $lastUse) < 0.3) {
            return;
        }

        $this->lastToolUse[$playerName] = $currentTime;
        $this->handleInspectorToolAction($damager, $entity, $itemName);
    }

    /**
     * @priority MONITOR
     * @ignoreCancelled false
     */
    public function onEntityDamageMonitor(EntityDamageEvent $event): void {
        $entity = $event->getEntity();

        if (!$entity instanceof Player) {
            return;
        }

        $manager = $this->plugin->getInspectorManager();

        if (!$event instanceof EntityDamageByEntityEvent) {
            if ($manager->isInspectorMode($entity) || $manager->isFrozen($entity)) {
                $event->cancel();
            }
            return;
        }

        $damager = $event->getDamager();

        if (!$damager instanceof Player) {
            if ($manager->isInspectorMode($entity) || $manager->isFrozen($entity)) {
                $event->cancel();
            }
            return;
        }

        if ($manager->isInspectorMode($damager)) {
            $event->cancel();
            return;
        }

        if ($manager->isInspectorMode($entity) ||
            $manager->isFrozen($entity) ||
            $manager->isFrozen($damager)) {
            $event->cancel();
        }
    }

    private function isInspectorActionTool(string $itemName): bool {
        return in_array($itemName, [
            "§r§8[§eCMD§f-§eHISTORY§8]§r",
            "§r§8[§bFREEZE§8]§r",
            "§r§8[§eINVENTORY§8]§r",
            "§r§8[§dENDER§f-§dINV§8]§r",
            "§8[§cPLAYER§f-§cBAN§8]§r"
        ], true);
    }

    private function handleInspectorToolAction(Player $inspector, Player $target, string $itemName): void {
        $manager = $this->plugin->getInspectorManager();

        switch ($itemName) {
            case "§r§8[§eCMD§f-§eHISTORY§8]§r":
                $this->showCommandHistory($inspector, $target);
                break;

            case "§r§8[§bFREEZE§8]§r":
                $manager->toggleFreeze($inspector, $target);
                break;

            case "§8[§cPLAYER§f-§cBAN§8]§r":
                $inspector->sendForm(new SanctionForm($this->plugin, $target->getName()));
                break;

            case "§r§8[§eINVENTORY§8]§r":
                $manager->openPlayerInventory($inspector, $target);
                break;

            case "§r§8[§dENDER§f-§dINV§8]§r":
                $manager->openPlayerEnderInventory($inspector, $target);
                break;
        }
    }

    private function showCommandHistory(Player $inspector, Player $target): void {
        $db = $this->plugin->getDatabaseManager();
        $history = $db->getCommandHistory($target->getName(), 10);

        if (empty($history)) {
            $this->plugin->getMessageManager()->sendToast($inspector, "no-command-history", [
                "player" => $target->getName()
            ]);
            return;
        }

        $inspector->sendMessage("§6Inspector | User: §a" . $target->getName() . " §6Author: §a" . $inspector->getName());

        foreach ($history as $entry) {
            $time = date("H:i:s", $entry["timestamp"]);
            $command = $entry["command"];
            $args = $entry["arguments"] ?? "";

            $inspector->sendMessage("§3[§b{$time}§3] §f| §3[§b{$command}§3] §f| §3[§b{$args}§3]");
        }
    }
}
