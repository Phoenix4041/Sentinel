<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\inspector\listener;

use Phoenix4041\Sentinel\Loader;
use pocketmine\event\Listener;
use pocketmine\event\server\CommandEvent;

class CommandListener implements Listener {

    private Loader $plugin;
    private array $commandCache = [];
    private const CACHE_DURATION = 60;

    public function __construct(Loader $plugin) {
        $this->plugin = $plugin;
    }

    public function onCommand(CommandEvent $event): void {
        $sender = $event->getSender();

        if (!$sender instanceof \pocketmine\player\Player) {
            return;
        }

        $command = $event->getCommand();

        if (empty($command)) {
            return;
        }

        $parts = explode(" ", $command, 2);
        $cmd = $parts[0];
        $args = $parts[1] ?? "";

        $playerName = $sender->getName();
        $cacheKey = $playerName . ":" . time();

        if (!isset($this->commandCache[$playerName])) {
            $this->commandCache[$playerName] = [];
        }

        $this->commandCache[$playerName][$cacheKey] = true;

        $this->plugin->getDatabaseManager()->logCommand($playerName, $cmd, $args);

        if (count($this->commandCache[$playerName]) > 100) {
            $this->cleanupCache($playerName);
        }
    }

    private function cleanupCache(string $playerName): void {
        if (!isset($this->commandCache[$playerName])) {
            return;
        }

        $currentTime = time();
        $expired = array_filter(
            array_keys($this->commandCache[$playerName]),
            fn($key) => ((int)explode(":", $key)[1] + self::CACHE_DURATION) < $currentTime
        );

        foreach ($expired as $key) {
            unset($this->commandCache[$playerName][$key]);
        }

        if (empty($this->commandCache[$playerName])) {
            unset($this->commandCache[$playerName]);
        }
    }
}
