<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\command;

use CortexPE\Commando\BaseCommand;
use Phoenix4041\Sentinel\command\args\StaffSubCommandArgument;
use Phoenix4041\Sentinel\Loader;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class StaffCommand extends BaseCommand {

    public function __construct(private readonly Loader $plugin) {
        parent::__construct($plugin, "staff", "Toggle staff mode and manage its tools", ["staffmode", "mod"]);
        $this->setPermission("sentinel.staff");
    }

    protected function prepare(): void {
        $this->registerArgument(0, new StaffSubCommandArgument("subcommand", true));
    }

    // See UnbanCommand::testPermission() for why this always allows entry.
    public function testPermission(CommandSender $target, ?string $permission = null): bool {
        return true;
    }

    /** @param array<string, mixed> $args */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage($this->plugin->getMessageManager()->getRawMessage("player-only"));
            return;
        }

        if (!$sender->hasPermission("sentinel.staff")) {
            $this->plugin->getMessageManager()->sendToast($sender, "no-permission");
            return;
        }

        $mode = $this->plugin->getModeManager();
        $subcommand = isset($args["subcommand"]) && is_string($args["subcommand"]) ? $args["subcommand"] : "toggle";

        switch ($subcommand) {
            case "toggle":
                $mode->toggle($sender);
                break;

            case "on":
                if (!$mode->isActive($sender)) {
                    $mode->enable($sender);
                } else {
                    $this->plugin->getMessageManager()->sendToast($sender, "mode-already-enabled");
                }
                break;

            case "off":
                if ($mode->isActive($sender)) {
                    $mode->disable($sender);
                } else {
                    $this->plugin->getMessageManager()->sendToast($sender, "mode-already-disabled");
                }
                break;

            case "chat":
                if ($mode->isStaffChatEnabled($sender)) {
                    $mode->disableStaffChat($sender);
                } else {
                    $mode->enableStaffChat($sender);
                }
                break;

            case "help":
                $this->sendHelp($sender);
                break;

            default:
                $this->plugin->getMessageManager()->sendToast($sender, "mode-invalid-subcommand");
                $this->sendHelp($sender);
                break;
        }
    }

    private function sendHelp(Player $player): void {
        $player->sendMessage("§8§l§m                                                    ");
        $player->sendMessage("§6§lStaff Commands");
        $player->sendMessage("");
        $player->sendMessage("§e/staff §for §e/staff toggle §f- Toggle staff mode");
        $player->sendMessage("§e/staff on §f- Enable staff mode");
        $player->sendMessage("§e/staff off §f- Disable staff mode");
        $player->sendMessage("§e/staff chat §f- Toggle staff chat");
        $player->sendMessage("§e/staff help §f- Show this help message");
        $player->sendMessage("");
        $player->sendMessage("§7Aliases: §fstaff, staffmode, mod");
        $player->sendMessage("§8§l§m                                                    ");
    }
}
