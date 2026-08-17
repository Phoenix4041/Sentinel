<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\manager;

use pocketmine\player\Player;

/**
 * Keeps a name-indexed lookup of currently online players, updated on join/quit.
 *
 * Introduced during the EpicStaff/Inspector merge to replace direct
 * Server::getPlayerExact() / getPlayerByName() calls and manual iteration
 * over getOnlinePlayers() to find a player by name, which PMMP 5.x flags
 * as an anti-pattern for high player-count servers.
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
