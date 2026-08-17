<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\staff\form;

use pocketmine\form\Form;
use pocketmine\player\Player;
use pocketmine\Server;

final class TeleportForm implements Form {

    public function jsonSerialize(): array {
        $players = [];
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            $players[] = $player->getName();
        }

        return [
            "type" => "form",
            "title" => "§dTeleport",
            "content" => "Select a player to teleport to:",
            "buttons" => array_map(fn($name) => ["text" => $name], $players)
        ];
    }

    public function handleResponse(Player $player, $data): void {
        if ($data === null) return;

        $players = array_values(Server::getInstance()->getOnlinePlayers());
        if (!isset($players[$data])) {
            $player->sendPopup("§cPlayer not found");
            return;
        }

        $target = $players[$data];
        $player->teleport($target->getPosition());
        $player->sendPopup("§aTeleported to " . $target->getName());
    }
}
