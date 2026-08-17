<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\item;

use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;

/**
 * The staff mode toolkit. Every tool is tagged with an NBT marker instead of
 * matched by CustomName, so item identity survives renames/localization and
 * can't be spoofed by a player-crafted item with the same display name.
 */
final class ToolItems {

    private const NBT_TAG = "sentineltool";

    public static function give(Player $player): void {
        $inventory = $player->getInventory();

        $inventory->setItem(0, self::tag(VanillaItems::ENDER_PEARL(), "randomtp", "§8[§5RandomTp§8]"));
        $inventory->setItem(1, self::tag(VanillaItems::COMPASS(), "teleport", "§8[§dTeleport§8]"));
        $inventory->setItem(2, self::tag(VanillaItems::PAPER(), "history", "§8[§eHistory§8]"));
        $inventory->setItem(3, self::tag(VanillaBlocks::CHEST()->asItem(), "invsee", "§8[§6InvSee§8]"));
        $inventory->setItem(4, self::tag(VanillaBlocks::ENDER_CHEST()->asItem(), "enderinv", "§8[§dEnderInv§8]"));
        $inventory->setItem(5, self::vanishItem(false));
        $inventory->setItem(6, self::tag(VanillaItems::BLAZE_ROD(), "freeze", "§8[§bFreeze§8]"));
        $inventory->setItem(7, self::nightVisionItem());
        $inventory->setItem(8, self::tag(VanillaItems::STICK(), "ban", "§8[§cBan§8]"));
    }

    public static function vanishItem(bool $vanished): Item {
        $dye = VanillaItems::DYE()->setColor($vanished ? DyeColor::RED() : DyeColor::LIME());
        return self::tag($dye, "vanish", $vanished ? "§8[§cVanish§8]" : "§8[§aVanish§8]");
    }

    private static function nightVisionItem(): Item {
        $item = VanillaItems::POTION();
        $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 1));
        return self::tag($item, "nightvision", "§8[§1Night Vision§8]");
    }

    private static function tag(Item $item, string $type, string $name): Item {
        $item->setCustomName($name);
        $item->getNamedTag()->setString(self::NBT_TAG, $type);
        return $item;
    }

    public static function isToolItem(Item $item): bool {
        return $item->getNamedTag()->getTag(self::NBT_TAG) !== null;
    }

    public static function getType(Item $item): ?string {
        if (!self::isToolItem($item)) {
            return null;
        }
        return $item->getNamedTag()->getString(self::NBT_TAG, null);
    }
}
