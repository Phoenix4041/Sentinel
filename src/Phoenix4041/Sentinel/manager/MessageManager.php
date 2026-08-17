<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\manager;

use Phoenix4041\Sentinel\Loader;
use pocketmine\player\Player;
use pocketmine\utils\Config;

final class MessageManager {

    private Config $messages;
    private string $toastTitle;

    public function __construct(private readonly Loader $plugin) {
        $this->messages = new Config($plugin->getDataFolder() . "messages.yml", Config::YAML);
        $this->toastTitle = $this->messages->get("toast-title", "§8[§6Sentinel§8]");
    }

    public function sendToast(Player $player, string $key, array $replacements = []): void {
        $player->sendToastNotification($this->toastTitle, $this->getRawMessage($key, $replacements));
    }

    public function broadcastToast(string $key, array $replacements = []): void {
        $message = $this->getRawMessage($key, $replacements);

        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            $player->sendToastNotification($this->toastTitle, $message);
        }
    }

    public function getRawMessage(string $key, array $replacements = []): string {
        $message = $this->messages->get($key, "Message not found: {$key}");

        foreach ($replacements as $search => $replace) {
            $message = str_replace("{" . $search . "}", $replace, $message);
        }

        return $message;
    }

    public function getToastTitle(): string {
        return $this->toastTitle;
    }
}
