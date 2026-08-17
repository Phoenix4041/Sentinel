<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\inspector\menu;

use Phoenix4041\Sentinel\Loader;
use pocketmine\block\inventory\ChestInventory;
use pocketmine\block\inventory\DoubleChestInventory;
use pocketmine\block\tile\Chest;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\player\Player;
use pocketmine\world\Position;

class InventoryMenu {

    private Loader $plugin;
    private static array $viewing = [];

    public function __construct(Loader $plugin) {
        $this->plugin = $plugin;
    }

    public static function isViewing(Player $player): bool {
        return isset(self::$viewing[$player->getName()]);
    }

    public static function setViewing(Player $player, bool $viewing): void {
        if ($viewing) {
            self::$viewing[$player->getName()] = true;
        } else {
            unset(self::$viewing[$player->getName()]);
        }
    }

    public function openPlayerInventory(Player $inspector, Player $target): void {
        $pos = $inspector->getPosition()->floor();
        $chestPos = new Vector3($pos->x, $pos->y + 3, $pos->z);

        $inspector->getNetworkSession()->sendDataPacket(
            BlockActorDataPacket::create(
                $chestPos->getFloorX(),
                $chestPos->getFloorY(),
                $chestPos->getFloorZ(),
                new CacheableNbt(\pocketmine\nbt\tag\CompoundTag::create()
                    ->setString("id", "Chest")
                    ->setString("CustomName", "§6" . $target->getName() . "'s Inventory"))
            )
        );

        $inventory = $target->getInventory();
        $inspector->setCurrentWindow($inventory);

        self::setViewing($inspector, true);

        $this->plugin->getMessageManager()->sendToast($inspector, "inventory-opened", [
            "player" => $target->getName()
        ]);
    }

    public function openPlayerEnderInventory(Player $inspector, Player $target): void {
        $enderInventory = $target->getEnderInventory();
        $inspector->setCurrentWindow($enderInventory);

        self::setViewing($inspector, true);

        $this->plugin->getMessageManager()->sendToast($inspector, "ender-inventory-opened", [
            "player" => $target->getName()
        ]);
    }
}
