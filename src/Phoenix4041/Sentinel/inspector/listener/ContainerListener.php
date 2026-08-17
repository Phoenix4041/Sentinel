<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\inspector\listener;

use Phoenix4041\Sentinel\Loader;
use pocketmine\block\inventory\BlockInventory;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\inventory\transaction\action\SlotChangeAction;

class ContainerListener implements Listener {

    private Loader $plugin;

    public function __construct(Loader $plugin) {
        $this->plugin = $plugin;
    }

    public function onInventoryOpen(InventoryOpenEvent $event): void {
        $player = $event->getPlayer();
        $inventory = $event->getInventory();

        if ($inventory instanceof \muqsit\invmenu\inventory\InvMenuInventory) {
            return;
        }

        if ($this->plugin->getInspectorManager()->isInspectorMode($player) ||
            $this->plugin->getInspectorManager()->isFrozen($player)) {

            if (!$this->plugin->getInspectorManager()->isViewingInventory($player)) {
                $event->cancel();
                return;
            }
        }

        if (!$inventory instanceof BlockInventory) {
            return;
        }

        $pos = $inventory->getHolder();

        if (!$pos->isValid()) {
            return;
        }

        $containerType = $this->getContainerType($inventory);
        $x = (int)$pos->getFloorX();
        $y = (int)$pos->getFloorY();
        $z = (int)$pos->getFloorZ();
        $world = $pos->getWorld()->getFolderName();

        $this->plugin->getDatabaseManager()->logContainerAction(
            $player->getName(),
            "open",
            $containerType,
            "",
            $x,
            $y,
            $z,
            $world
        );
    }

    public function onInventoryClose(InventoryCloseEvent $event): void {
        $player = $event->getPlayer();

        if ($this->plugin->getInspectorManager()->isViewingInventory($player)) {
            $this->plugin->getInspectorManager()->stopViewingInventory($player);
        }
    }

    public function onInventoryTransaction(InventoryTransactionEvent $event): void {
        $transaction = $event->getTransaction();
        $player = $transaction->getSource();

        if ($this->plugin->getInspectorManager()->isFrozen($player)) {
            $event->cancel();
            return;
        }

        if ($this->plugin->getInspectorManager()->isInspectorMode($player) &&
            !$this->plugin->getInspectorManager()->isViewingInventory($player)) {
            $event->cancel();
            return;
        }

        foreach ($transaction->getActions() as $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $inventory = $action->getInventory();

            if ($inventory instanceof \muqsit\invmenu\inventory\InvMenuInventory) {
                continue;
            }

            if (!$inventory instanceof BlockInventory) {
                continue;
            }

            $pos = $inventory->getHolder();

            if (!$pos->isValid()) {
                continue;
            }

            $sourceItem = $action->getSourceItem();
            $targetItem = $action->getTargetItem();

            $itemsChanged = $this->formatItemChange(
                $sourceItem->getName(),
                $sourceItem->getCount(),
                $targetItem->getName(),
                $targetItem->getCount()
            );

            $x = (int)$pos->getFloorX();
            $y = (int)$pos->getFloorY();
            $z = (int)$pos->getFloorZ();
            $world = $pos->getWorld()->getFolderName();

            $this->plugin->getDatabaseManager()->logContainerAction(
                $player->getName(),
                "transaction",
                $this->getContainerType($inventory),
                $itemsChanged,
                $x,
                $y,
                $z,
                $world
            );
        }
    }

    private function getContainerType(BlockInventory $inventory): string {
        $holder = $inventory->getHolder();
        return (new \ReflectionClass($holder))->getShortName();
    }

    private function formatItemChange(string $sourceName, int $sourceCount, string $targetName, int $targetCount): string {
        if ($sourceCount === 0 && $targetCount > 0) {
            return "Added {$targetCount}x {$targetName}";
        } elseif ($sourceCount > 0 && $targetCount === 0) {
            return "Removed {$sourceCount}x {$sourceName}";
        } else {
            return "Changed {$sourceName}x{$sourceCount} -> {$targetName}x{$targetCount}";
        }
    }
}
