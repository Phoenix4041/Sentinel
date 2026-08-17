<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\staff\form;

use pocketmine\form\Form;
use pocketmine\player\Player;

final class CommandsForm implements Form {

    private string $targetName;
    private array $commands;

    public function __construct(string $targetName, array $commands) {
        $this->targetName = $targetName;
        $this->commands = $commands;
    }

    public function jsonSerialize(): array {
        $content = empty($this->commands) ? "No commands recorded" : "";

        foreach ($this->commands as $cmd) {
            $date = date("Y-m-d H:i:s", $cmd["time"]);
            $content .= "§6Command: §f{$cmd["command"]}\n";
            $content .= "§7Time: §f$date\n\n";
        }

        return [
            "type" => "form",
            "title" => "Commands - " . $this->targetName,
            "content" => trim($content),
            "buttons" => [["text" => "Close"]]
        ];
    }

    public function handleResponse(Player $player, $data): void {}
}
