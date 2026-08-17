<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\staff\form;

use pocketmine\form\Form;
use pocketmine\player\Player;

final class DeathsForm implements Form {

    private string $targetName;
    private array $deaths;

    public function __construct(string $targetName, array $deaths) {
        $this->targetName = $targetName;
        $this->deaths = $deaths;
    }

    public function jsonSerialize(): array {
        $content = empty($this->deaths) ? "No deaths recorded" : "";

        foreach ($this->deaths as $death) {
            $date = date("Y-m-d H:i:s", $death["time"]);
            $enchants = empty($death["enchants"]) ? "None" : implode(", ", $death["enchants"]);
            $content .= "§cKiller: §f{$death["killer"]}\n";
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
