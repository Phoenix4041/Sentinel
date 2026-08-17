<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\staff\listener;

use pocketmine\event\Listener;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerDeathEvent;

use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\player\Player;
use pocketmine\block\tile\Container;
use Phoenix4041\Sentinel\staff\manager\DataManager;

final class DataListener implements Listener {

    private DataManager $dataManager;

    public function __construct(DataManager $dataManager) {
        $this->dataManager = $dataManager;
    }

    public function onPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $pos = $block->getPosition();

        $this->dataManager->logBlockPlace(
            (int)$pos->x,
            (int)$pos->y,
            (int)$pos->z,
            $pos->getWorld()->getFolderName(),
            $player->getName()
        );
    }

    public function onDeath(PlayerDeathEvent $event): void {
        $player = $event->getPlayer();
        $cause = $player->getLastDamageCause();

        if (!$cause instanceof \pocketmine\event\entity\EntityDamageByEntityEvent) return;

        $damager = $cause->getDamager();
        if (!$damager instanceof Player) return;

        $weapon = $damager->getInventory()->getItemInHand();
        $enchants = array_map(
            fn($e) => $e->getType()->getName(),
            $weapon->getEnchantments()
        );

        $this->dataManager->logKill(
            $damager->getName(),
            $player->getName(),
            $weapon->getName(),
            $enchants
        );

        $this->dataManager->logDeath(
            $player->getName(),
            $damager->getName(),
            $weapon->getName(),
            $enchants
        );
    }



    public function onTransaction(InventoryTransactionEvent $event): void {
        $transaction = $event->getTransaction();
        $source = $transaction->getSource();

        foreach ($transaction->getActions() as $action) {
            if (!$action instanceof SlotChangeAction) continue;

            $inv = $action->getInventory();
            if (!$inv instanceof \pocketmine\block\inventory\BlockInventory) continue;

            $tile = $inv->getHolder();
            if (!$tile instanceof Container) continue;

            $pos = $tile->getPosition();
            $sourceItem = $action->getSourceItem();
            $targetItem = $action->getTargetItem();

            if (!$sourceItem->isNull() && $targetItem->isNull()) {
                $this->dataManager->logContainerAccess(
                    (int)$pos->x,
                    (int)$pos->y,
                    (int)$pos->z,
                    $pos->getWorld()->getFolderName(),
                    $source->getName(),
                    "took",
                    $sourceItem->getName()
                );
            } elseif ($sourceItem->isNull() && !$targetItem->isNull()) {
                $this->dataManager->logContainerAccess(
                    (int)$pos->x,
                    (int)$pos->y,
                    (int)$pos->z,
                    $pos->getWorld()->getFolderName(),
                    $source->getName(),
                    "put",
                    $targetItem->getName()
                );
            }
        }
    }
}
