<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\manager;

use pocketmine\player\Player;

/**
 * Marks staff members as easy to spot: a colored, always-visible nametag.
 * Bedrock Edition has no client-side glow/outline effect at all (that's a
 * Java Edition exclusive - there's no metadata flag, status effect or
 * packet for it to send), so this is the closest verifiable equivalent the
 * PMMP API actually supports.
 */
final class GlowManager {

    private const PREFIX = "§b§l";

    /** @var array<string, string> original name tag, keyed by strtolower(name) */
    private array $originalNameTags = [];

    public function __construct(private readonly PlayerRegistry $playerRegistry) {}

    public function addGlow(Player $player): void {
        $key = strtolower($player->getName());
        if (isset($this->originalNameTags[$key])) {
            return;
        }

        $this->originalNameTags[$key] = $player->getNameTag();
        $player->setNameTag(self::PREFIX . $player->getName());
        $player->setNameTagAlwaysVisible(true);
    }

    public function removeGlow(Player $player): void {
        $key = strtolower($player->getName());
        if (!isset($this->originalNameTags[$key])) {
            return;
        }

        $player->setNameTag($this->originalNameTags[$key]);
        $player->setNameTagAlwaysVisible(false);
        unset($this->originalNameTags[$key]);
    }

    public function removeAllGlow(): void {
        foreach (array_keys($this->originalNameTags) as $name) {
            $player = $this->playerRegistry->getByName($name);
            if ($player !== null) {
                $this->removeGlow($player);
            }
        }
    }
}
