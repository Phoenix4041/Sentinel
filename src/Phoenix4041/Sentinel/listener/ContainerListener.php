<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\listener;

use Phoenix4041\Sentinel\Loader;
use muqsit\invmenu\inventory\InvMenuInventory;
use pocketmine\block\inventory\BlockInventory;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\player\Player;

/**
 * Logs container open/take/put actions to DatabaseManager and blocks
 * container access while a player is in staff mode (unless it's the
 * InvSee/EnderInv menu) or frozen.
 */
final class ContainerListener implements Listener {

    public function __construct(private readonly Loader $plugin) {}

    public function onInventoryOpen(InventoryOpenEvent $event): void {
        $player = $event->getPlayer();
        $inventory = $event->getInventory();

        if ($inventory instanceof InvMenuInventory) {
            return;
        }

        $mode = $this->plugin->getModeManager();

        if (($mode->isActive($player) || $mode->isFrozen($player)) && !$this->plugin->getInventoryMenu()->isViewing($player)) {
            $event->cancel();
            return;
        }

        if (!$inventory instanceof BlockInventory) {
            return;
        }

        $pos = $inventory->getHolder();

        if (!$pos->isValid()) {
            return;
        }

        $this->plugin->getDatabaseManager()->logContainerAction(
            $player->getName(),
            "open",
            $this->getContainerType($inventory),
            "",
            (int)$pos->getFloorX(),
            (int)$pos->getFloorY(),
            (int)$pos->getFloorZ(),
            $pos->getWorld()->getFolderName()
        );
    }

    public function onInventoryClose(InventoryCloseEvent $event): void {
        $player = $event->getPlayer();

        if ($this->plugin->getInventoryMenu()->isViewing($player)) {
            $this->plugin->getInventoryMenu()->stopViewing($player);
        }
    }

    public function onInventoryTransaction(InventoryTransactionEvent $event): void {
        $transaction = $event->getTransaction();
        $player = $transaction->getSource();
        $mode = $this->plugin->getModeManager();

        if ($mode->isFrozen($player)) {
            $event->cancel();
            return;
        }

        if ($mode->isActive($player) && !$this->plugin->getInventoryMenu()->isViewing($player)) {
            $event->cancel();
            return;
        }

        foreach ($transaction->getActions() as $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $inventory = $action->getInventory();

            if ($inventory instanceof InvMenuInventory || !$inventory instanceof BlockInventory) {
                continue;
            }

            $pos = $inventory->getHolder();

            if (!$pos->isValid()) {
                continue;
            }

            $sourceItem = $action->getSourceItem();
            $targetItem = $action->getTargetItem();

            $this->plugin->getDatabaseManager()->logContainerAction(
                $player->getName(),
                "transaction",
                $this->getContainerType($inventory),
                $this->formatItemChange($sourceItem->getName(), $sourceItem->getCount(), $targetItem->getName(), $targetItem->getCount()),
                (int)$pos->getFloorX(),
                (int)$pos->getFloorY(),
                (int)$pos->getFloorZ(),
                $pos->getWorld()->getFolderName()
            );
        }
    }

    private function getContainerType(BlockInventory $inventory): string {
        return (new \ReflectionClass($inventory->getHolder()))->getShortName();
    }

    private function formatItemChange(string $sourceName, int $sourceCount, string $targetName, int $targetCount): string {
        if ($sourceCount === 0 && $targetCount > 0) {
            return "Added {$targetCount}x {$targetName}";
        }
        if ($sourceCount > 0 && $targetCount === 0) {
            return "Removed {$sourceCount}x {$sourceName}";
        }
        return "Changed {$sourceName}x{$sourceCount} -> {$targetName}x{$targetCount}";
    }
}
