<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\staff\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use Phoenix4041\Sentinel\staff\manager\StaffManager;

final class StaffCommand extends Command {

    private StaffManager $staffManager;

    public function __construct(StaffManager $staffManager) {
        parent::__construct("staff", "Toggle staff mode");
        $this->setPermission("epicstaff.use");
        $this->staffManager = $staffManager;
    }

    public function execute(CommandSender $sender, string $label, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cThis command can only be used in-game");
            return;
        }

        if (!$sender->hasPermission("epicstaff.use")) {
            $sender->sendPopup("§cNo permission");
            return;
        }

        $this->staffManager->toggle($sender);
    }
}
