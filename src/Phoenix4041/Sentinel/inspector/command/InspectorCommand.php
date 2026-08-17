<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\inspector\command;

use Phoenix4041\Sentinel\Loader;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class InspectorCommand extends Command {

    private Loader $plugin;

    public function __construct(Loader $plugin) {
        parent::__construct(
            "inspector",
            "Inspector mode and management tools",
            "/inspector [toggle|chat|on|off|help]",
            ["insp", "staff"]
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

        $manager = $this->plugin->getInspectorManager();
        $subcommand = strtolower($args[0] ?? "toggle");

        switch ($subcommand) {
            case "toggle":
            case "t":
                if ($manager->isInspectorMode($sender)) {
                    $manager->disableInspectorMode($sender);
                } else {
                    $manager->enableInspectorMode($sender);
                }
                break;

            case "on":
            case "enable":
                if (!$manager->isInspectorMode($sender)) {
                    $manager->enableInspectorMode($sender);
                } else {
                    $this->plugin->getMessageManager()->sendToast($sender, "inspector-already-enabled");
                }
                break;

            case "off":
            case "disable":
                if ($manager->isInspectorMode($sender)) {
                    $manager->disableInspectorMode($sender);
                } else {
                    $this->plugin->getMessageManager()->sendToast($sender, "inspector-already-disabled");
                }
                break;

            case "chat":
            case "c":
            case "sc":
            case "staffchat":
                if ($manager->isStaffChatEnabled($sender)) {
                    $manager->disableStaffChat($sender);
                } else {
                    $manager->enableStaffChat($sender);
                }
                break;

            case "help":
            case "?":
            case "h":
                $this->sendHelp($sender);
                break;

            default:
                $this->plugin->getMessageManager()->sendToast($sender, "inspector-invalid-subcommand");
                $this->sendHelp($sender);
                break;
        }

        return true;
    }

    private function sendHelp(Player $player): void {
        $msg = $this->plugin->getMessageManager();

        $player->sendMessage("§8§l§m                                                    ");
        $player->sendMessage("§6§lInspector Commands");
        $player->sendMessage("");
        $player->sendMessage("§e/inspector §for §e/insp §f- Toggle inspector mode");
        $player->sendMessage("§e/inspector toggle §f- Toggle inspector mode");
        $player->sendMessage("§e/inspector on §f- Enable inspector mode");
        $player->sendMessage("§e/inspector off §f- Disable inspector mode");
        $player->sendMessage("§e/inspector chat §f- Toggle staff chat");
        $player->sendMessage("§e/inspector help §f- Show this help message");
        $player->sendMessage("");
        $player->sendMessage("§7Aliases: §finspector, insp, staff");
        $player->sendMessage("§8§l§m                                                    ");
    }
}
