<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\staff\form;

use pocketmine\form\Form;
use pocketmine\player\Player;

final class KillsForm implements Form {

    private string $targetName;
    private array $kills;

    public function __construct(string $targetName, array $kills) {
        $this->targetName = $targetName;
        $this->kills = $kills;
    }

    public function jsonSerialize(): array {
        $content = empty($this->kills) ? "No kills recorded" : "";

        foreach ($this->kills as $kill) {
            $date = date("Y-m-d H:i:s", $kill["time"]);
            $enchants = empty($kill["enchants"]) ? "None" : implode(", ", $kill["enchants"]);
            $content .= "§aVictim: §f{$kill["victim"]}\n";
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
