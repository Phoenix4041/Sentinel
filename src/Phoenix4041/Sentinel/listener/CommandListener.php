<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\listener;

use Phoenix4041\Sentinel\Loader;
use pocketmine\event\Listener;
use pocketmine\event\server\CommandEvent;
use pocketmine\player\Player;

/**
 * Cancels commands from frozen players and logs every player-issued
 * command to DatabaseManager.
 */
final class CommandListener implements Listener {

    public function __construct(private readonly Loader $plugin) {}

    public function onCommand(CommandEvent $event): void {
        $sender = $event->getSender();

        if (!$sender instanceof Player) {
            return;
        }

        if ($this->plugin->getModeManager()->isFrozen($sender)) {
            $event->cancel();
            return;
        }

        $command = $event->getCommand();

        if ($command === "") {
            return;
        }

        [$cmd, $args] = array_pad(explode(" ", $command, 2), 2, "");

        $this->plugin->getDatabaseManager()->logCommand($sender->getName(), $cmd, $args);
    }
}
