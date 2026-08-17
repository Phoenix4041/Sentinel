<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\staff\listener;

use pocketmine\event\Listener;
use pocketmine\event\server\CommandEvent;
use pocketmine\player\Player;
use Phoenix4041\Sentinel\staff\manager\StaffManager;
use Phoenix4041\Sentinel\staff\manager\DataManager;

final class CommandListener implements Listener {

    private StaffManager $staffManager;
    private DataManager $dataManager;

    public function __construct(StaffManager $staffManager, DataManager $dataManager) {
        $this->staffManager = $staffManager;
        $this->dataManager = $dataManager;
    }

    public function onCommand(CommandEvent $event): void {
        $sender = $event->getSender();

        if (!$sender instanceof Player) return;

        if ($this->staffManager->isFrozen($sender)) {
            $event->cancel();
            return;
        }

        $this->dataManager->logCommand($sender->getName(), $event->getCommand());
    }
}
