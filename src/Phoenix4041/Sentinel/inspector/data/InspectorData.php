<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\inspector\data;

use pocketmine\player\GameMode;

class InspectorData {

    private GameMode $gameMode;
    private bool $allowFlight;
    private bool $isFlying;
    private bool $invulnerable;
    private array $inventory;

    public function __construct(
        GameMode $gameMode,
        bool $allowFlight,
        bool $isFlying,
        bool $invulnerable,
        array $inventory
    ) {
        $this->gameMode = $gameMode;
        $this->allowFlight = $allowFlight;
        $this->isFlying = $isFlying;
        $this->invulnerable = $invulnerable;
        $this->inventory = $inventory;
    }

    public function getGameMode(): GameMode {
        return $this->gameMode;
    }

    public function getAllowFlight(): bool {
        return $this->allowFlight;
    }

    public function isFlying(): bool {
        return $this->isFlying;
    }

    public function getInvulnerable(): bool {
        return $this->invulnerable;
    }

    public function getInventory(): array {
        return $this->inventory;
    }
}
