<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\form;

use Phoenix4041\Sentinel\Loader;
use pocketmine\form\Form;
use pocketmine\player\Player;

final class UnbanForm implements Form {

    public function __construct(private readonly Loader $plugin, private readonly ?string $targetPlayer = null) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array {
        $bannedPlayers = array_keys($this->plugin->getSanctionManager()->getActiveBans());

        $msg = $this->plugin->getMessageManager();

        if (empty($bannedPlayers)) {
            $bannedPlayers[] = $msg->getRawMessage("unban-no-players");
        }

        return [
            "type" => "custom_form",
            "title" => $msg->getRawMessage("unban-form-title"),
            "content" => [
                [
                    "type" => "dropdown",
                    "text" => $msg->getRawMessage("unban-player-selection"),
                    "options" => $bannedPlayers,
                    "default" => $this->targetPlayer !== null ?
                        (array_search($this->targetPlayer, $bannedPlayers, true) ?: 0) : 0
                ],
                [
                    "type" => "dropdown",
                    "text" => $msg->getRawMessage("unban-type-selection"),
                    "options" => [
                        $msg->getRawMessage("unban-type-ban"),
                        $msg->getRawMessage("unban-type-mute"),
                        $msg->getRawMessage("unban-type-all")
                    ],
                    "default" => 0
                ]
            ]
        ];
    }

    public function handleResponse(Player $player, $data): void {
        if (!is_array($data) || !isset($data[0], $data[1]) || !is_numeric($data[0]) || !is_numeric($data[1])) {
            return;
        }

        $selectedIndex = (int)$data[0];
        $unbanType = (int)$data[1];

        $bannedPlayers = array_keys($this->plugin->getSanctionManager()->getActiveBans());

        if (empty($bannedPlayers)) {
            $this->plugin->getMessageManager()->sendToast($player, "unban-error-no-banned");
            return;
        }

        if (!isset($bannedPlayers[$selectedIndex])) {
            $this->plugin->getMessageManager()->sendToast($player, "unban-error-invalid");
            return;
        }

        $targetName = $bannedPlayers[$selectedIndex];
        $sanctionManager = $this->plugin->getSanctionManager();

        $success = match($unbanType) {
            0 => $sanctionManager->unbanPlayer($targetName),
            1 => $sanctionManager->unmutePlayer($targetName),
            2 => $sanctionManager->removeAllSanctions($targetName),
            default => false
        };

        if ($success) {
            $typeKey = match($unbanType) {
                0 => "unban-type-ban",
                1 => "unban-type-mute",
                2 => "unban-type-all",
                default => "unban-type-ban"
            };

            $this->plugin->getMessageManager()->sendToast($player, "unban-success", [
                "player" => $targetName,
                "type" => $this->plugin->getMessageManager()->getRawMessage($typeKey)
            ]);

            $this->plugin->getMessageManager()->broadcastToast("unban-broadcast", [
                "player" => $targetName,
                "staff" => $player->getName(),
                "type" => $this->plugin->getMessageManager()->getRawMessage($typeKey)
            ]);
        } else {
            $this->plugin->getMessageManager()->sendToast($player, "unban-error-failed");
        }
    }
}
