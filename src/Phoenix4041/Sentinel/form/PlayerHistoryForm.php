<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\form;

use Phoenix4041\Sentinel\Loader;
use pocketmine\form\Form;
use pocketmine\player\Player;

/**
 * Hub form opened by the History tool: routes to kills, deaths or command
 * history, all backed by DatabaseManager.
 */
final class PlayerHistoryForm implements Form {

    public function __construct(private readonly Loader $plugin, private readonly string $targetName) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array {
        return [
            "type" => "form",
            "title" => "§2History - " . $this->targetName,
            "content" => "Select an option:",
            "buttons" => [
                ["text" => "Kills"],
                ["text" => "Deaths"],
                ["text" => "Commands"]
            ]
        ];
    }

    public function handleResponse(Player $player, $data): void {
        if (!is_int($data)) {
            return;
        }

        $db = $this->plugin->getDatabaseManager();
        $limit = $this->plugin->getConfigManager()->getCommandHistoryLimit();

        match ($data) {
            0 => $player->sendForm(new KillsForm($this->targetName, $db->getKills($this->targetName, $limit))),
            1 => $player->sendForm(new DeathsForm($this->targetName, $db->getDeaths($this->targetName, $limit))),
            2 => $player->sendForm(new CommandsForm($this->targetName, $db->getCommandHistory($this->targetName, $limit))),
            default => null
        };
    }
}
