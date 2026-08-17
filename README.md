# Sentinel

### A unified staff toolkit for PocketMine-MP 5.x: one staff mode, one deduplicated tool kit, one SQLite-backed audit log.

**Statically analyzed with [PHPStan](https://phpstan.org) at max level (9) - zero errors.**

---

## Features

* **Staff Mode**: One command toggles staff mode - saves your position/inventory/gamemode, equips the tool kit, and marks you as staff for other staff/ops.
* **Deduplicated Tool Kit**: Nine NBT-tagged tools, one hotbar, no overlap - RandomTp, Teleport, History, InvSee, EnderInv, Vanish, Freeze, Night Vision and Ban.
* **Staff Nametag**: Staff members get a colored, always-visible nametag while in staff mode. Bedrock Edition has no client-side glow/outline effect (that's Java Edition only), so this is the closest real equivalent.
* **Kill/Death/Command History**: Per-player kill, death and command logs (with weapon/enchant details), persisted to SQLite and viewable through in-game forms.
* **Block & Container Auditing**: Every block placement/break and container open/take/put is logged with world coordinates for later lookup.
* **SQLite-backed Persistence**: A single indexed SQLite3 database with prepared statements and an in-memory query cache backs all history, replacing flat-file storage entirely.
* **Sanctions, Bans & Mutes**: Ban/mute players with custom or preset durations, view and remove active bans (`/unban`) or mutes (`/unmute`) through forms, with automatic login-time enforcement.
* **InvMenu-based Inventory Viewing**: Read-only inventory/ender-chest viewer built on the `muqsit/invmenu` virion.
* **Staff Chat**: Toggleable staff-only chat channel.
* **Command Autocompletion**: `/staff`, `/unban`, `/mute` and `/unmute` expose real client-side argument previews/dropdowns via the `CortexPE/Commando` virion.
* **Multi-language Messages**: All player-facing text lives in `resources/langs/*.yml` (English and Spanish included), selected via `language` in `config.yml`.

---

## Requirements

* PocketMine-MP 5.0+
* PHP 8.1+
* `ext-sqlite3` (bundled with PMMP)

---

## Installation

1. Download or build the `Sentinel.phar` (or copy this folder into your server's `plugins/` directory).
2. Start (or restart) your PocketMine-MP 5.x server.
3. On first run, Sentinel generates `config.yml` and `langs/en.yml`/`langs/es.yml` inside `plugins/Sentinel/`.
4. Grant `sentinel.staff` (and `sentinel.command.unban`/`sentinel.command.mute`/`sentinel.command.unmute` if separate from staff) to your staff group.

---

## Configuration

* `config.yml` - Tool cooldowns (vanish, freeze), history query limits, and `language` (the langs/ file to use, without extension).
* `langs/en.yml`, `langs/es.yml` - All player-facing toast/chat text, fully customizable per language.
* `sentinel.db` - SQLite3 database holding command, block, container and kill/death audit logs.
* `bans.yml`, `mutes.yml` - Generated sanction storage.

---

## Permissions

```yaml
sentinel.staff              # Access to /staff, staff mode and its tool kit
sentinel.command.unban      # Access to /unban
sentinel.command.mute       # Access to /mute
sentinel.command.unmute     # Access to /unmute
```

---

## Usage

### Commands

```bash
/staff [toggle|on|off|chat|help]   # Manage staff mode / staff chat
/unban [player]                    # Open a form to remove a player's ban/mute/all sanctions
/mute [player]                     # Open a form to mute a player
/unmute [player]                   # Open a form to remove a player's mute
```

**Aliases**: `/staff` -> `staffmode`, `mod` &nbsp;|&nbsp; `/unban` -> `pardon`, `unsanction`

All four commands use the `CortexPE/Commando` virion, so their optional arguments (subcommand for `/staff`, player name for `/unban`, `/mute`, `/unmute`) show up as real client-side autocomplete/preview instead of plain text.

### Staff Mode Tools

| Slot | Item | Name | Function |
| :--- | :--- | :--- | :--- |
| 0 | Ender Pearl | RandomTp | Teleport to a random online player |
| 1 | Compass | Teleport | Open a form to teleport to any online player |
| 2 | Paper | History | Attack a player to view their kills, deaths and command history |
| 3 | Chest | InvSee | Attack a player to view their inventory (read-only) |
| 4 | Dye | Vanish | Toggle invisibility to non-staff; dye color reflects state |
| 5 | Ender Chest | EnderInv | Attack a player to view their ender chest (read-only) |
| 6 | Blaze Rod | Freeze | Attack a player to freeze/unfreeze them |
| 7 | Potion | Night Vision | Toggle permanent night vision |
| 8 | Stick | Ban | Attack a player to open the sanction form, pre-filled with the target |

### How It Works

**Entering staff mode**:
1. Run `/staff`; your position, gamemode and inventory are saved and swapped for the tool kit.
2. You start glowing for other staff/ops; self-use tools (RandomTp, Teleport, Vanish, Night Vision) trigger on right-click, target tools (History, InvSee, EnderInv, Freeze, Ban) trigger by attacking the target player.
3. Running `/staff` again restores your saved position, gamemode and inventory, and disables all staff effects.

---

## Technical Details

### Architecture

```
src/Phoenix4041/Sentinel/
├── Loader.php              # Single bootstrap, singleton, wires up every manager/listener/command
├── command/                 # /staff, /unban
├── listener/                 # Player, Block, Container, Command and PlayerRegistry listeners
├── manager/                   # ModeManager, GlowManager, SanctionManager, ConfigManager, MessageManager, PlayerRegistry
├── database/                   # SQLite3-backed DatabaseManager (command/block/container/kill logs)
├── item/                        # ToolItems - the unified, NBT-tagged tool kit
├── form/                         # Teleport/Sanction/Unban/PlayerHistory/Kills/Deaths/Commands forms
├── menu/                          # InvMenu-based inventory/ender-chest viewer
├── (src/muqsit/invmenu/...)        # Vendored InvMenu virion (MIT, unmodified)
├── (src/muqsit/simplepackethandler/...) # Vendored SimplePacketHandler virion (GPL-3.0, unmodified)
└── (src/CortexPE/Commando/...)     # Vendored Commando virion (LGPL-3.0, unmodified)
```

### Design Principles

* **Single Bootstrap**: One `Loader` (singleton via `getInstance()`) owns every manager and wires all commands/listeners in `onEnable()`.
* **No Player-Lookup Anti-Pattern**: A shared `PlayerRegistry`, fed by join/quit events, replaces every `Server::getPlayerExact()`/`getPlayerByName()` call.
* **Dependency Injection**: Managers receive their collaborators (e.g. `ModeManager` receives `GlowManager`, `PlayerRegistry`, `MessageManager`, `ConfigManager`) through constructors rather than reaching into globals.
* **NBT-Tagged Items**: Tool identity is resolved via an NBT marker, not `CustomName` string matching, so it survives renames/localization.
* **Type Safety**: `declare(strict_types=1)` and typed properties/return types throughout.
* **Statically Verified**: `composer install && vendor/bin/phpstan analyse` runs clean at level 9 (max), pinned against the real PMMP 5.x API stubs - no `mixed`-cast guesses, no unchecked nullables.

---

### Planned Features (v2.0.0+)

- TBD

---

## License

This project's original Sentinel code is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Sentinel vendors the following third-party virions, unmodified, each under its own license (kept inside its own folder under `src/`):

* [`muqsit/invmenu`](https://github.com/Muqsit/InvMenu) - MIT
* [`muqsit/simplepackethandler`](https://github.com/Muqsit/SimplePacketHandler) - GPL-3.0
* [`CortexPE/Commando`](https://github.com/CortexPE/Commando) - LGPL-3.0

The MIT LICENSE at the repository root covers only the original Sentinel code under `src/Phoenix4041`; it does not relicense the vendored virions above.

---

## Support

For issues, feature requests, or questions:

* Open an issue against this repository.
