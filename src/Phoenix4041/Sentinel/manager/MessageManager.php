<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\manager;

use Phoenix4041\Sentinel\Loader;
use pocketmine\player\Player;
use pocketmine\utils\Config;

final class MessageManager {

    private Config $messages;
    private string $toastTitle;

    public function __construct(private readonly Loader $plugin, ConfigManager $configManager) {
        // getResources() is documented as \SplFileInfo[] (int-keyed) even though the
        // real keys are the resource's relative path; cast to line up with reality.
        foreach ($plugin->getResources() as $resourceKey => $resource) {
            $resourcePath = (string) $resourceKey;
            if (str_starts_with($resourcePath, "langs/")) {
                $plugin->saveResource($resourcePath);
            }
        }

        $language = $configManager->getLanguage();
        $languageFile = $plugin->getDataFolder() . "langs" . DIRECTORY_SEPARATOR . $language . ".yml";

        if (!is_file($languageFile)) {
            $plugin->getLogger()->warning("Language \"{$language}\" not found in langs/, falling back to English");
            $languageFile = $plugin->getDataFolder() . "langs" . DIRECTORY_SEPARATOR . "en.yml";
        }

        $this->messages = new Config($languageFile, Config::YAML);

        $title = $this->messages->get("toast-title", "§8[§6Sentinel§8]");
        $this->toastTitle = is_string($title) ? $title : "§8[§6Sentinel§8]";
    }

    /** @param array<string, string> $replacements */
    public function sendToast(Player $player, string $key, array $replacements = []): void {
        $player->sendToastNotification($this->toastTitle, $this->getRawMessage($key, $replacements));
    }

    /** @param array<string, string> $replacements */
    public function broadcastToast(string $key, array $replacements = []): void {
        $message = $this->getRawMessage($key, $replacements);

        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            $player->sendToastNotification($this->toastTitle, $message);
        }
    }

    /** @param array<string, string> $replacements */
    public function getRawMessage(string $key, array $replacements = []): string {
        $raw = $this->messages->get($key, "Message not found: {$key}");
        $message = is_string($raw) ? $raw : "Message not found: {$key}";

        foreach ($replacements as $search => $replace) {
            $message = str_replace("{" . $search . "}", $replace, $message);
        }

        return $message;
    }

    public function getToastTitle(): string {
        return $this->toastTitle;
    }
}
