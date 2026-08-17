<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\form;

use Phoenix4041\Sentinel\Loader;
use pocketmine\form\Form;
use pocketmine\player\Player;

final class UnmuteForm implements Form {

    public function __construct(private readonly Loader $plugin, private readonly ?string $targetPlayer = null) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array {
        $mutedPlayers = array_keys($this->plugin->getSanctionManager()->getActiveMutes());

        $msg = $this->plugin->getMessageManager();

        if (empty($mutedPlayers)) {
            $mutedPlayers[] = $msg->getRawMessage("unmute-no-players");
        }

        return [
            "type" => "custom_form",
            "title" => $msg->getRawMessage("unmute-form-title"),
            "content" => [
                [
                    "type" => "dropdown",
                    "text" => $msg->getRawMessage("unmute-player-selection"),
                    "options" => $mutedPlayers,
                    "default" => $this->targetPlayer !== null ?
                        (array_search($this->targetPlayer, $mutedPlayers, true) ?: 0) : 0
                ]
            ]
        ];
    }

    public function handleResponse(Player $player, $data): void {
        if (!is_array($data) || !isset($data[0]) || !is_numeric($data[0])) {
            return;
        }

        $selectedIndex = (int)$data[0];
        $mutedPlayers = array_keys($this->plugin->getSanctionManager()->getActiveMutes());

        if (empty($mutedPlayers)) {
            $this->plugin->getMessageManager()->sendToast($player, "unmute-error-no-muted");
            return;
        }

        if (!isset($mutedPlayers[$selectedIndex])) {
            $this->plugin->getMessageManager()->sendToast($player, "unmute-error-invalid");
            return;
        }

        $targetName = $mutedPlayers[$selectedIndex];
        $success = $this->plugin->getSanctionManager()->unmutePlayer($targetName);

        if ($success) {
            $this->plugin->getMessageManager()->sendToast($player, "unmute-success", [
                "player" => $targetName
            ]);

            $this->plugin->getMessageManager()->broadcastToast("unmute-broadcast", [
                "player" => $targetName,
                "staff" => $player->getName()
            ]);
        } else {
            $this->plugin->getMessageManager()->sendToast($player, "unmute-error-failed");
        }
    }
}
