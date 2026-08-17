<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\command;

use Phoenix4041\Sentinel\Loader;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class StaffCommand extends Command {

    public function __construct(private readonly Loader $plugin) {
        parent::__construct(
            "staff",
            "Toggle staff mode and manage its tools",
            "/staff [toggle|on|off|chat|help]",
            ["staffmode", "mod"]
        );
        $this->setPermission("sentinel.staff");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage($this->plugin->getMessageManager()->getRawMessage("player-only"));
            return false;
        }

        if (!$sender->hasPermission("sentinel.staff")) {
            $this->plugin->getMessageManager()->sendToast($sender, "no-permission");
            return false;
        }

        $mode = $this->plugin->getModeManager();
        $subcommand = strtolower($args[0] ?? "toggle");

        switch ($subcommand) {
            case "toggle":
            case "t":
                $mode->toggle($sender);
                break;

            case "on":
            case "enable":
                if (!$mode->isActive($sender)) {
                    $mode->enable($sender);
                } else {
                    $this->plugin->getMessageManager()->sendToast($sender, "mode-already-enabled");
                }
                break;

            case "off":
            case "disable":
                if ($mode->isActive($sender)) {
                    $mode->disable($sender);
                } else {
                    $this->plugin->getMessageManager()->sendToast($sender, "mode-already-disabled");
                }
                break;

            case "chat":
            case "c":
            case "sc":
                if ($mode->isStaffChatEnabled($sender)) {
                    $mode->disableStaffChat($sender);
                } else {
                    $mode->enableStaffChat($sender);
                }
                break;

            case "help":
            case "?":
            case "h":
                $this->sendHelp($sender);
                break;

            default:
                $this->plugin->getMessageManager()->sendToast($sender, "mode-invalid-subcommand");
                $this->sendHelp($sender);
                break;
        }

        return true;
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
