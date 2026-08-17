<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\manager;

use Phoenix4041\Sentinel\Loader;

final class ConfigManager {

    public function __construct(private readonly Loader $plugin) {}

    public function getVanishCooldown(): float {
        return (float) $this->plugin->getConfig()->get("vanish-cooldown", 1.0);
    }

    public function getFreezeCooldown(): float {
        return (float) $this->plugin->getConfig()->get("freeze-cooldown", 0.5);
    }

    public function getCommandHistoryLimit(): int {
        return (int) $this->plugin->getConfig()->get("command-history-limit", 10);
    }

    public function getBlockHistoryLimit(): int {
        return (int) $this->plugin->getConfig()->get("block-history-limit", 10);
    }

    public function getContainerHistoryLimit(): int {
        return (int) $this->plugin->getConfig()->get("container-history-limit", 10);
    }
}
