<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\menu;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\type\InvMenuTypeIds;
use Phoenix4041\Sentinel\Loader;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\player\Player;

/**
 * Read-only inventory/ender-chest viewer for staff mode's InvSee and
 * EnderInv tools, built on the muqsit/invmenu virion.
 */
final class InventoryMenu {

    /** @var array<string, true> */
    private array $viewing = [];

    public function __construct(private readonly Loader $plugin) {}

    public function isViewing(Player $player): bool {
        return isset($this->viewing[strtolower($player->getName())]);
    }

    public function stopViewing(Player $player): void {
        unset($this->viewing[strtolower($player->getName())]);
    }

    public function openPlayerInventory(Player $moderator, Player $target): void {
        if (!$moderator->isConnected() || !$target->isConnected()) {
            return;
        }

        $menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $menu->setName("§6" . $target->getName() . "'s Inventory");

        $inventory = $menu->getInventory();
        $inventory->setContents($target->getInventory()->getContents());

        $armor = $target->getArmorInventory();
        $separator = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::RED())->asItem()->setCustomName("§r");

        $inventory->setItem(45, $separator);
        $inventory->setItem(46, $separator);
        $inventory->setItem(47, $armor->getHelmet() ?? VanillaBlocks::AIR()->asItem());
        $inventory->setItem(48, $armor->getChestplate() ?? VanillaBlocks::AIR()->asItem());
        $inventory->setItem(49, $separator);
        $inventory->setItem(50, $armor->getLeggings() ?? VanillaBlocks::AIR()->asItem());
        $inventory->setItem(51, $armor->getBoots() ?? VanillaBlocks::AIR()->asItem());
        $inventory->setItem(52, $separator);
        $inventory->setItem(53, $separator);

        $this->send($menu, $moderator, $target, "inventory-opened");
    }

    public function openPlayerEnderInventory(Player $moderator, Player $target): void {
        if (!$moderator->isConnected() || !$target->isConnected()) {
            return;
        }

        $menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
        $menu->setName("§6" . $target->getName() . "'s Ender Inventory");
        $menu->getInventory()->setContents($target->getEnderInventory()->getContents());

        $this->send($menu, $moderator, $target, "ender-inventory-opened");
    }

    private function send(InvMenu $menu, Player $moderator, Player $target, string $openedMessageKey): void {
        $menu->setListener(InvMenu::readonly());
        $menu->setInventoryCloseListener(function (Player $player) use ($moderator): void {
            if ($player->getName() === $moderator->getName()) {
                $this->stopViewing($moderator);
            }
        });

        $this->viewing[strtolower($moderator->getName())] = true;

        try {
            $menu->send($moderator);
        } catch (\Throwable $e) {
            $this->stopViewing($moderator);
            $this->plugin->getLogger()->warning("Failed to open inventory view for " . $moderator->getName() . ": " . $e->getMessage());
            return;
        }

        $this->plugin->getMessageManager()->sendToast($moderator, $openedMessageKey, ["player" => $target->getName()]);
    }
}
