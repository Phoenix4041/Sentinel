<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\form;

use Phoenix4041\Sentinel\Loader;
use pocketmine\form\Form;
use pocketmine\player\Player;

final class SanctionForm implements Form {

    public function __construct(
        private readonly Loader $plugin,
        private readonly ?string $targetPlayer = null,
        private readonly bool $mute = false
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array {
        $onlinePlayers = [];
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            $onlinePlayers[] = $player->getName();
        }

        $msg = $this->plugin->getMessageManager();

        return [
            "type" => "custom_form",
            "title" => $msg->getRawMessage($this->mute ? "mute-form-title" : "sanction-form-title"),
            "content" => [
                [
                    "type" => "dropdown",
                    "text" => $msg->getRawMessage("sanction-player-selection"),
                    "options" => array_merge([$msg->getRawMessage("sanction-manual-input")], $onlinePlayers),
                    "default" => $this->targetPlayer !== null ?
                        (array_search($this->targetPlayer, $onlinePlayers) ?: 0) + 1 : 0
                ],
                [
                    "type" => "input",
                    "text" => $msg->getRawMessage("sanction-player-name"),
                    "placeholder" => $msg->getRawMessage("sanction-player-placeholder"),
                    "default" => $this->targetPlayer ?? ""
                ],
                [
                    "type" => "input",
                    "text" => $msg->getRawMessage("sanction-reason"),
                    "placeholder" => $msg->getRawMessage("sanction-reason-placeholder"),
                    "default" => ""
                ],
                [
                    "type" => "dropdown",
                    "text" => $msg->getRawMessage("sanction-duration-type"),
                    "options" => [
                        $msg->getRawMessage("sanction-duration-custom"),
                        $msg->getRawMessage("sanction-duration-permanent"),
                        $msg->getRawMessage("sanction-duration-1hour"),
                        $msg->getRawMessage("sanction-duration-1day"),
                        $msg->getRawMessage("sanction-duration-7days"),
                        $msg->getRawMessage("sanction-duration-30days")
                    ],
                    "default" => 0
                ],
                [
                    "type" => "input",
                    "text" => $msg->getRawMessage("sanction-custom-duration"),
                    "placeholder" => $msg->getRawMessage("sanction-custom-placeholder"),
                    "default" => ""
                ]
            ]
        ];
    }

    public function handleResponse(Player $player, $data): void {
        if (
            !is_array($data)
            || !isset($data[0], $data[1], $data[2], $data[3], $data[4])
            || !is_numeric($data[0])
            || !is_scalar($data[1])
            || !is_scalar($data[2])
            || !is_numeric($data[3])
            || !is_scalar($data[4])
        ) {
            return;
        }

        $selectedIndex = (int)$data[0];
        $manualInput = trim((string)$data[1]);
        $reason = trim((string)$data[2]);
        $durationType = (int)$data[3];
        $customDuration = trim((string)$data[4]);

        if ($selectedIndex === 0) {
            if (empty($manualInput)) {
                $this->plugin->getMessageManager()->sendToast($player, "sanction-error-no-player");
                return;
            }
            $targetName = $manualInput;
        } else {
            $onlinePlayers = array_values(array_map(
                fn($p) => $p->getName(),
                $this->plugin->getServer()->getOnlinePlayers()
            ));
            $targetName = $onlinePlayers[$selectedIndex - 1] ?? "";
        }

        if (empty($targetName)) {
            $this->plugin->getMessageManager()->sendToast($player, "sanction-error-invalid-player");
            return;
        }

        if (empty($reason)) {
            $reason = $this->plugin->getMessageManager()->getRawMessage("sanction-no-reason");
        }

        $duration = match($durationType) {
            1 => 0,
            2 => 3600,
            3 => 86400,
            4 => 604800,
            5 => 2592000,
            default => $this->plugin->getSanctionManager()->parseDuration($customDuration)
        };

        $sanctionManager = $this->plugin->getSanctionManager();
        $success = $this->mute
            ? $sanctionManager->mutePlayer($targetName, $reason, $duration, $player->getName())
            : $sanctionManager->banPlayer($targetName, $reason, $duration, $player->getName());

        $successKey = $this->mute ? "mute-success" : "sanction-success";
        $broadcastKey = $this->mute ? "mute-broadcast" : "sanction-broadcast";
        $errorKey = $this->mute ? "mute-error-failed" : "sanction-error-failed";

        if ($success) {
            $durationText = $duration === 0 ?
                $this->plugin->getMessageManager()->getRawMessage("ban-permanent") :
                $customDuration;

            if ($durationType > 1) {
                $durationText = ["",
                    $this->plugin->getMessageManager()->getRawMessage("ban-permanent"),
                    "1h", "1d", "7d", "30d"
                ][$durationType];
            }

            $this->plugin->getMessageManager()->sendToast($player, $successKey, [
                "player" => $targetName,
                "reason" => $reason,
                "duration" => $durationText
            ]);

            $this->plugin->getMessageManager()->broadcastToast($broadcastKey, [
                "player" => $targetName,
                "staff" => $player->getName(),
                "reason" => $reason
            ]);
        } else {
            $this->plugin->getMessageManager()->sendToast($player, $errorKey);
        }
    }
}
