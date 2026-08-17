<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\staff\form;

use pocketmine\form\Form;
use pocketmine\player\Player;
use Phoenix4041\Sentinel\Loader;

final class InspectorForm implements Form {

    private Player $target;

    public function __construct(Player $target) {
        $this->target = $target;
    }

    public function jsonSerialize(): array {
        return [
            "type" => "form",
            "title" => "§2Inspector - " . $this->target->getName(),
            "content" => "Select an option:",
            "buttons" => [
                ["text" => "Kills"],
                ["text" => "Deaths"],
                ["text" => "Commands"]
            ]
        ];
    }

    public function handleResponse(Player $player, $data): void {
        if ($data === null) return;

        $dataManager = Loader::getInstance()->getDataManager();
        $targetName = $this->target->getName();

        match ($data) {
            0 => $player->sendForm(new KillsForm($targetName, $dataManager->getKills($targetName))),
            1 => $player->sendForm(new DeathsForm($targetName, $dataManager->getDeaths($targetName))),
            2 => $player->sendForm(new CommandsForm($targetName, $dataManager->getCommands($targetName))),
            default => null
        };
    }
}
