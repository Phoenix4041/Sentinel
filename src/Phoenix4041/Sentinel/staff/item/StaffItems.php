<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\staff\item;

use pocketmine\player\Player;
use pocketmine\item\VanillaItems;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\enchantment\EnchantmentInstance;

final class StaffItems {

    public static function give(Player $player): void {
        $inv = $player->getInventory();

        $randomTp = VanillaItems::ENDER_PEARL();
        $randomTp->setCustomName("§8[§5RandomTp§8]");
        $randomTp->getNamedTag()->setString("staffitem", "randomtp");
        $inv->setItem(0, $randomTp);

        $freezer = VanillaItems::BLAZE_ROD();
        $freezer->setCustomName("§8[§bFreezer§8]");
        $freezer->getNamedTag()->setString("staffitem", "freezer");
        $inv->setItem(1, $freezer);

        $inspector = VanillaItems::BOOK();
        $inspector->setCustomName("§8[§2Inspector§8]");
        $inspector->getNamedTag()->setString("staffitem", "inspector");
        $inv->setItem(3, $inspector);

        $vanish = VanillaItems::EYE_OF_ENDER();
        $vanish->setCustomName("§8[§aVanish§8]");
        $vanish->getNamedTag()->setString("staffitem", "vanish");
        $inv->setItem(4, $vanish);

        $invsee = VanillaItems::CHEST();
        $invsee->setCustomName("§8[§6InvSee§8]");
        $invsee->getNamedTag()->setString("staffitem", "invsee");
        $inv->setItem(5, $invsee);

        $nightvision = VanillaItems::POTION();
        $nightvision->setCustomName("§8[§1Night Vision§8]");
        $nightvision->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 1));
        $nightvision->getNamedTag()->setString("staffitem", "nightvision");
        $inv->setItem(7, $nightvision);

        $teleport = VanillaItems::COMPASS();
        $teleport->setCustomName("§8[§dTeleport§8]");
        $teleport->getNamedTag()->setString("staffitem", "teleport");
        $inv->setItem(8, $teleport);
    }

    public static function isStaffItem(mixed $item): bool {
        if (!$item instanceof \pocketmine\item\Item) return false;
        return $item->getNamedTag()->getTag("staffitem") !== null;
    }

    public static function getType(mixed $item): ?string {
        if (!self::isStaffItem($item)) return null;
        return $item->getNamedTag()->getString("staffitem", null);
    }
}
