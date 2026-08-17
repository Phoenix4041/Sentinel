<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\command;

use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use Phoenix4041\Sentinel\form\UnbanForm;
use Phoenix4041\Sentinel\Loader;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class UnbanCommand extends BaseCommand {

    public function __construct(private readonly Loader $plugin) {
        parent::__construct($plugin, "unban", "Remove sanctions from players", ["pardon", "unsanction"]);
        $this->setPermission("sentinel.command.unban");
    }

    protected function prepare(): void {
        $this->registerArgument(0, new TextArgument("player", true));
    }

    public function testPermission(CommandSender $target, ?string $permission = null): bool {
        return true;
    }

    /** @param array<string, mixed> $args */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage($this->plugin->getMessageManager()->getRawMessage("player-only"));
            return;
        }

        if (!$sender->hasPermission("sentinel.command.unban")) {
            $this->plugin->getMessageManager()->sendToast($sender, "no-permission");
            return;
        }

        $targetPlayer = isset($args["player"]) && is_string($args["player"]) ? $args["player"] : null;

        $sender->sendForm(new UnbanForm($this->plugin, $targetPlayer));
    }
}
