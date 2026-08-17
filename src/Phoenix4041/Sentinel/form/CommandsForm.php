<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\form;

use pocketmine\form\Form;
use pocketmine\player\Player;

final class CommandsForm implements Form {

    /**
     * @param array<int, array{command: string, arguments: string, timestamp: int}> $commands
     */
    public function __construct(private readonly string $targetName, private readonly array $commands) {}

    public function jsonSerialize(): array {
        $content = empty($this->commands) ? "No commands recorded" : "";

        foreach ($this->commands as $cmd) {
            $date = date("Y-m-d H:i:s", $cmd["timestamp"]);
            $content .= "§6Command: §f{$cmd["command"]} {$cmd["arguments"]}\n";
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
