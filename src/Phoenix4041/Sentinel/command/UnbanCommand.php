<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\command;

use Phoenix4041\Sentinel\form\UnbanForm;
use Phoenix4041\Sentinel\Loader;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class UnbanCommand extends Command {

    public function __construct(private readonly Loader $plugin) {
        parent::__construct(
            "unban",
            "Remove sanctions from players",
            "/unban",
            ["pardon", "unsanction"]
        );
        $this->setPermission("sentinel.command.unban");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage($this->plugin->getMessageManager()->getRawMessage("player-only"));
            return false;
        }

        if (!$sender->hasPermission("sentinel.command.unban")) {
            $this->plugin->getMessageManager()->sendToast($sender, "no-permission");
            return false;
        }

        $sender->sendForm(new UnbanForm($this->plugin));

        return true;
    }
}
