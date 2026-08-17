<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\listener;

use Phoenix4041\Sentinel\Loader;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\player\Player;

/**
 * Logs every block placement/break to DatabaseManager and blocks world
 * edits while a player is in staff mode or frozen.
 */
final class BlockListener implements Listener {

    public function __construct(private readonly Loader $plugin) {}

    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();

        if ($this->blockedFromEditing($player, $event)) {
            return;
        }

        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
            $pos = $block->getPosition();

            $this->plugin->getDatabaseManager()->logBlockAction(
                $player->getName(),
                "place",
                (string)$block->getTypeId(),
                (int)$pos->getFloorX(),
                (int)$pos->getFloorY(),
                (int)$pos->getFloorZ(),
                $pos->getWorld()->getFolderName()
            );
        }
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();

        if ($this->blockedFromEditing($player, $event)) {
            return;
        }

        $block = $event->getBlock();
        $pos = $block->getPosition();

        $this->plugin->getDatabaseManager()->logBlockAction(
            $player->getName(),
            "break",
            (string)$block->getTypeId(),
            (int)$pos->getFloorX(),
            (int)$pos->getFloorY(),
            (int)$pos->getFloorZ(),
            $pos->getWorld()->getFolderName()
        );
    }

    private function blockedFromEditing(Player $player, BlockPlaceEvent|BlockBreakEvent $event): bool {
        $mode = $this->plugin->getModeManager();

        if ($mode->isActive($player) || $mode->isFrozen($player)) {
            $event->cancel();
            return true;
        }

        return false;
    }
}
