<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\form;

use pocketmine\form\Form;
use pocketmine\player\Player;

final class KillsForm implements Form {

    /**
     * @param array<int, array{victim_name: string, weapon: string, enchantments: string, timestamp: int}> $kills
     */
    public function __construct(private readonly string $targetName, private readonly array $kills) {}

    public function jsonSerialize(): array {
        $content = empty($this->kills) ? "No kills recorded" : "";

        foreach ($this->kills as $kill) {
            $date = date("Y-m-d H:i:s", $kill["timestamp"]);
            $enchants = empty($kill["enchantments"]) ? "None" : $kill["enchantments"];
            $content .= "§aVictim: §f{$kill["victim_name"]}\n";
            $content .= "§eWeapon: §f{$kill["weapon"]}\n";
            $content .= "§bEnchants: §f$enchants\n";
            $content .= "§7Time: §f$date\n\n";
        }

        return [
            "type" => "form",
            "title" => "Kills - " . $this->targetName,
            "content" => trim($content),
            "buttons" => [["text" => "Close"]]
        ];
    }

    public function handleResponse(Player $player, $data): void {}
}
