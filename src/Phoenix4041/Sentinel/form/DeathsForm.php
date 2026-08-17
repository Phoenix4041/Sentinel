<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\form;

use pocketmine\form\Form;
use pocketmine\player\Player;

final class DeathsForm implements Form {

    /**
     * @param array<int, array{killer_name: string, weapon: string, enchantments: string, timestamp: int}> $deaths
     */
    public function __construct(private readonly string $targetName, private readonly array $deaths) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array {
        $content = empty($this->deaths) ? "No deaths recorded" : "";

        foreach ($this->deaths as $death) {
            $date = date("Y-m-d H:i:s", $death["timestamp"]);
            $enchants = empty($death["enchantments"]) ? "None" : $death["enchantments"];
            $content .= "§cKiller: §f{$death["killer_name"]}\n";
            $content .= "§eWeapon: §f{$death["weapon"]}\n";
            $content .= "§bEnchants: §f$enchants\n";
            $content .= "§7Time: §f$date\n\n";
        }

        return [
            "type" => "form",
            "title" => "Deaths - " . $this->targetName,
            "content" => trim($content),
            "buttons" => [["text" => "Close"]]
        ];
    }

    public function handleResponse(Player $player, $data): void {}
}
