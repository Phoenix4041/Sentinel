<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\manager;

use pocketmine\player\Player;

/**
 * Name-indexed lookup of currently online players, updated on join/quit.
 * Exists so player-by-name resolution never has to fall back to
 * Server::getPlayerExact()/getPlayerByName() or an O(n) scan over
 * getOnlinePlayers(), both of which are anti-patterns at high player counts.
 */
final class PlayerRegistry {

    /** @var array<string, Player> keyed by strtolower(name) */
    private array $players = [];

    public function add(Player $player): void {
        $this->players[strtolower($player->getName())] = $player;
    }

    public function remove(Player $player): void {
        unset($this->players[strtolower($player->getName())]);
    }

    public function getByName(string $name): ?Player {
        $player = $this->players[strtolower($name)] ?? null;
        if ($player !== null && !$player->isConnected()) {
            unset($this->players[strtolower($name)]);
            return null;
        }
        return $player;
    }

    /**
     * @return array<string, Player>
     */
    public function getAll(): array {
        return $this->players;
    }
}
