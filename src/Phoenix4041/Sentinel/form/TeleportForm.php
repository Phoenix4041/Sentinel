<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\form;

use Phoenix4041\Sentinel\Loader;
use pocketmine\form\Form;
use pocketmine\player\Player;

final class TeleportForm implements Form {

    public function __construct(private readonly Loader $plugin, private readonly Player $sender) {}

    public function jsonSerialize(): array {
        $onlinePlayers = [];
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            if ($player->getName() !== $this->sender->getName()) {
                $onlinePlayers[] = $player->getName();
            }
        }

        $msg = $this->plugin->getMessageManager();

        if (empty($onlinePlayers)) {
            $onlinePlayers[] = $msg->getRawMessage("teleport-no-players");
        }

        return [
            "type" => "custom_form",
            "title" => $msg->getRawMessage("teleport-form-title"),
            "content" => [
                [
                    "type" => "dropdown",
                    "text" => $msg->getRawMessage("teleport-player-selection"),
                    "options" => $onlinePlayers,
                    "default" => 0
                ]
            ]
        ];
    }

    public function handleResponse(Player $player, $data): void {
        if ($data === null) {
            return;
        }

        $selectedIndex = (int)$data[0];

        $onlinePlayers = [];
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
            if ($p->getName() !== $player->getName()) {
                $onlinePlayers[] = $p;
            }
        }

        if (empty($onlinePlayers)) {
            $this->plugin->getMessageManager()->sendToast($player, "teleport-error-no-players");
            return;
        }

        if (!isset($onlinePlayers[$selectedIndex])) {
            $this->plugin->getMessageManager()->sendToast($player, "teleport-error-invalid");
            return;
        }

        $target = $onlinePlayers[$selectedIndex];

        if ($target->getName() === $player->getName()) {
            $this->plugin->getMessageManager()->sendToast($player, "teleport-error-self");
            return;
        }

        $player->teleport($target->getPosition());

        $this->plugin->getMessageManager()->sendToast($player, "teleport-success", [
            "player" => $target->getName()
        ]);
    }
}
