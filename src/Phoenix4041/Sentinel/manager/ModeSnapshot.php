<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\manager;

use pocketmine\item\Item;
use pocketmine\player\GameMode;
use pocketmine\world\Position;

/**
 * What a player looked like right before entering staff mode, so it can be
 * restored exactly on exit.
 */
final class ModeSnapshot {

    /**
     * @param array<int, Item> $inventory
     * @param array<int, Item> $armor
     */
    public function __construct(
        public readonly Position $position,
        public readonly array $inventory,
        public readonly array $armor,
        public readonly GameMode $gameMode,
        public readonly bool $allowFlight,
        public readonly bool $isFlying
    ) {}
}
