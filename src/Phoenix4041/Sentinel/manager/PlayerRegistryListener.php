<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\manager;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

/**
 * Feeds the shared PlayerRegistry from join/quit events so both the
 * staff and inspector modules can resolve a Player by name without
 * calling Server::getPlayerExact()/getPlayerByName() or iterating
 * getOnlinePlayers() themselves.
 */
final class PlayerRegistryListener implements Listener {

    private PlayerRegistry $registry;

    public function __construct(PlayerRegistry $registry) {
        $this->registry = $registry;
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $this->registry->add($event->getPlayer());
    }

    public function onQuit(PlayerQuitEvent $event): void {
        $this->registry->remove($event->getPlayer());
    }
}
