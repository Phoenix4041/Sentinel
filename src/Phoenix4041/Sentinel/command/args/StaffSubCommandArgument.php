<?php

declare(strict_types=1);

namespace Phoenix4041\Sentinel\command\args;

use CortexPE\Commando\args\StringEnumArgument;
use pocketmine\command\CommandSender;

final class StaffSubCommandArgument extends StringEnumArgument {

    protected const VALUES = [
        "toggle" => "toggle",
        "on" => "on",
        "off" => "off",
        "chat" => "chat",
        "help" => "help"
    ];

    public function getTypeName(): string {
        return "subcommand";
    }

    public function parse(string $argument, CommandSender $sender): mixed {
        return $this->getValue($argument);
    }
}
