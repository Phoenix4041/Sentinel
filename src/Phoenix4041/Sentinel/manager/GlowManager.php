<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\manager;

use pocketmine\player\Player;

/**
 * Tracks which players are marked as staff-visible. This used to also push
 * a fake "glowing" SetActorDataPacket flag, but PMMP 5.x's
 * EntityMetadataFlags table has no glow/outline bit and there is no vanilla
 * status effect for it either - Bedrock's actual glow effect is driven by
 * scoreboard team color, which needs its own virion to do safely. Until
 * that's added, this only tracks who is in glow state; it has no visual
 * effect on the client.
 */
final class GlowManager {

    /** @var array<string, true> */
    private array $glowing = [];

    public function __construct(private readonly PlayerRegistry $playerRegistry) {}

    public function addGlow(Player $player): void {
        $this->glowing[strtolower($player->getName())] = true;
    }

    public function removeGlow(Player $player): void {
        unset($this->glowing[strtolower($player->getName())]);
    }

    public function removeAllGlow(): void {
        foreach (array_keys($this->glowing) as $name) {
            $player = $this->playerRegistry->getByName($name);
            if ($player !== null) {
                $this->removeGlow($player);
            }
        }
    }

    public function sendGlow(Player $player, Player $viewer): void {}
}
