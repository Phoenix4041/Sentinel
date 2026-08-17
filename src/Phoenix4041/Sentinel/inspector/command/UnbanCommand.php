<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\inspector\command;

use Phoenix4041\Sentinel\inspector\form\UnbanForm;
use Phoenix4041\Sentinel\Loader;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class UnbanCommand extends Command {

    private Loader $plugin;

    public function __construct(Loader $plugin) {
        parent::__construct(
            "unban",
            "Remove sanctions from players",
            "/unban",
            ["pardon", "unsanction"]
        );
        $this->setPermission("inspector.command.access");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage($this->plugin->getMessageManager()->getRawMessage("player-only"));
            return false;
        }

        if (!$sender->hasPermission("inspector.command.access")) {
            $this->plugin->getMessageManager()->sendToast($sender, "no-permission");
            return false;
        }

        $sender->sendForm(new UnbanForm($this->plugin));

        return true;
    }
}
