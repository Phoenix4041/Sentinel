# Sentinel

### A unified staff toolkit for PocketMine-MP 5.x, merging staff-mode utilities with player/chest inspection and moderation tools.

---

## Features

* **Staff Mode**: One-command toggle that equips a staff kit (random teleport, freezer, inspector, vanish, night vision, teleport picker, inv-see) and clears the player's inventory while active.
* **Glow Tracking**: Staff members glow for other staff/ops via `SetActorDataPacket` metadata, without affecting non-staff visibility.
* **Kill/Death/Command History**: Per-player kill, death and command logs (with weapon/enchant details) persisted to `players.yml`, viewable through in-game forms.
* **Block & Container Auditing**: Every block placement and container take/put is logged with world coordinates for later lookup.
* **Inspector Mode**: A dedicated moderation mode that swaps the player's inventory for inspection tools (command history, freeze, inventory/ender-chest viewer, teleport, vanish, ban).
* **SQLite-backed Command/Block/Container History**: Indexed SQLite3 tables with an in-memory query cache for fast lookups on high-traffic servers.
* **Sanctions & Bans**: Ban/mute players with custom or preset durations, view and remove active sanctions through forms, with automatic login-time enforcement.
* **InvMenu-based Inventory Viewing**: Read-only inventory/ender-chest viewer built on the `muqsit/invmenu` virion.
* **Staff Chat**: Toggleable staff-only chat channel.

---

## Requirements

* PocketMine-MP 5.0+
* PHP 8.1+
* `ext-sqlite3` (bundled with PMMP)

---

## Installation

1. Download or build the `Sentinel.phar` (or copy this folder into your server's `plugins/` directory).
2. Start (or restart) your PocketMine-MP 5.x server.
3. On first run, Sentinel generates `config.yml` and `messages.yml` inside `plugins/Sentinel/`.
4. Grant `epicstaff.use` and/or `inspector.command.access` to your staff group.

---

## Configuration

* `config.yml` - Database cache/history/performance settings (cache TTL, cooldowns, history limits, logging toggles) used by the inspector module.
* `messages.yml` - All player-facing toast/chat text for the inspector module, fully customizable.
* `blocks.yml`, `containers.yml`, `players.yml` - Generated data files used by the staff module's kill/death/command/block tracking.
* `inspector.db` - SQLite3 database holding command, block and container audit logs for the inspector module.
* `bans.yml`, `mutes.yml` - Generated sanction storage.

---

## Permissions

```yaml
epicstaff.use              # Access to /staff (staff mode: teleport, glow, kill/death tracking, quick items)
inspector.command.access   # Access to /inspector, /unban and inspector mode tools
```

---

## Usage

### Commands

```bash
/staff                     # Toggle staff mode
/inspector [toggle|on|off|chat|help]   # Manage inspector mode / staff chat
/unban                     # Open a form to remove a player's ban/mute/all sanctions
```

**Aliases**: `/inspector` -> `insp`, `staff` &nbsp;|&nbsp; `/unban` -> `pardon`, `unsanction`

### Staff Mode Items

| Slot | Item | Name | Function |
| :--- | :--- | :--- | :--- |
| 0 | Ender Pearl | RandomTp | Teleport to a random online player |
| 1 | Blaze Rod | Freezer | Freeze the nearest player |
| 3 | Book | Inspector | Open kill/death/command history for the nearest player |
| 4 | Eye of Ender | Vanish | Toggle invisibility to non-staff |
| 5 | Chest | InvSee | Preview the nearest player's inventory |
| 7 | Potion | Night Vision | Toggle permanent night vision |
| 8 | Compass | Teleport | Open a form to teleport to any online player |

### Inspector Mode Items

| Slot | Item | Name | Function |
| :--- | :--- | :--- | :--- |
| 0 | Paper | CMD-HISTORY | Show a target's recent command history |
| 1 | Ender Pearl | Inspector | Right/left-click a block to see its place/break/container history |
| 2 | Compass | TELEPORT | Open a form to teleport to any online player |
| 3 | Chest | INVENTORY | View a target player's inventory (read-only) |
| 4 | Ender Chest | ENDER-INV | View a target player's ender chest (read-only) |
| 5 | Lime Dye | VANISH | Toggle invisibility to non-staff |
| 7 | Stick | PLAYER-BAN | Open the sanction form, pre-filled with the target |
| 8 | Light Blue Dye | FREEZE | Freeze/unfreeze the target player |

### How It Works

**Entering staff mode**:
1. Run `/staff`; your current inventory is cleared and the staff kit is equipped.
2. You start glowing for other staff/ops; you can vanish, freeze players, and inspect their kill/death/command history.
3. Running `/staff` again restores your inventory and disables all staff effects.

**Entering inspector mode**:
1. Run `/inspector` (or `/inspector on`); your position, gamemode and inventory are saved and swapped for the inspector toolkit.
2. Use the tools to freeze, inspect, teleport, vanish or ban players; block interactions are cancelled while active.
3. Running `/inspector off` restores your saved position, gamemode and inventory.

---

## Technical Details

### Architecture

```
src/Phoenix4041/Sentinel/
├── Loader.php                 # Single bootstrap, singleton, wires up both modules
├── manager/                   # Shared infrastructure (PlayerRegistry)
├── staff/
│   ├── command/                # /staff
│   ├── form/                   # Kills/Deaths/Commands/Inspector/Teleport forms
│   ├── item/                   # Staff kit item definitions
│   ├── listener/                # Join/quit/interact/data-tracking listeners
│   └── manager/                 # StaffManager, GlowManager, DataManager
├── inspector/
│   ├── command/                # /inspector, /unban
│   ├── data/                    # InspectorData value object
│   ├── database/                 # SQLite3-backed DatabaseManager
│   ├── form/                    # Sanction/Teleport/Unban forms
│   ├── listener/                 # Block/Command/Container/Player listeners
│   ├── manager/                  # InspectorManager, SanctionManager, MessageManager, ConfigManager
│   └── menu/                     # InventoryMenu helper
└── (src/muqsit/invmenu/...)      # Vendored InvMenu virion (MIT, unmodified)
```

### Design Principles

* **Single Bootstrap**: One `Loader` (singleton via `getInstance()`) owns both modules' managers and wires all commands/listeners in `onEnable()`.
* **No Player-Lookup Anti-Pattern**: A shared `PlayerRegistry`, fed by join/quit events, replaces every `Server::getPlayerExact()`/`getPlayerByName()` call that previously existed in the staff and sanction managers.
* **Dependency Injection**: Managers receive their collaborators (e.g. `StaffManager` receives `GlowManager` and `PlayerRegistry`) through constructors rather than reaching into globals.
* **Type Safety**: `declare(strict_types=1)` and typed properties/return types throughout.

---

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## Support

For issues, feature requests, or questions:

* Open an issue against this repository.
