# MyAdmin Docker VPS Plugin

## Overview
PHP plugin for MyAdmin — Docker VPS lifecycle management.
- **Namespace:** `Detain\MyAdminDocker\` → `src/`
- **Tests:** `tests/PluginTest.php` · **Templates:** `templates/` · `templates/backup/`
- **Composer:** `composer.json` · autoload PSR-4, requires `symfony/event-dispatcher ^5.0`

## Commands
```bash
composer install
vendor/bin/phpunit                        # all tests
vendor/bin/phpunit tests/ -v              # verbose
```

```bash
vendor/bin/phpunit tests/PluginTest.php --testdox        # single class with documentation output
vendor/bin/phpunit tests/ --coverage-text --whitelist src/  # with coverage report
```

## Architecture
**Entry:** `src/Plugin.php` — single class `Plugin` in `Detain\MyAdminDocker`
**CI/CD:** `.github/workflows/` — automated test workflows (`tests.yml`) triggered on push and pull requests
**IDE Config:** `.idea/` — JetBrains project settings including `inspectionProfiles/`, `deployment.xml`, `encodings.xml`

**Hooks registered in `getHooks()`:**
| Method | Hook key |
|---|---|
| `getSettings` | `vps.settings` |
| `getDeactivate` | `vps.deactivate` |
| `getQueue` | `vps.queue` |
| `getActivate` | *(exists but not registered)* |

```php
// Hook registration pattern in src/Plugin.php
public static function getHooks()
{
    return [
        self::$module.'.settings'    => [__CLASS__, 'getSettings'],
        self::$module.'.deactivate'  => [__CLASS__, 'getDeactivate'],
        self::$module.'.queue'       => [__CLASS__, 'getQueue'],
    ];
}
```

**Templates:** `templates/{action}.sh.tpl` — Smarty shell scripts rendered in `getQueue()`
**Backup templates:** `templates/backup/{action}.sh.tpl` — mirror set for backup VPS operations
**Service types:** `DOCKER` · `DOCKER_STORAGE` — checked via `get_service_define()`

**Available template actions:** `create` · `delete` · `destroy` · `start` · `stop` · `restart` · `reset` · `reset_password` · `backup` · `restore` · `add_ip` · `remove_ip` · `change_hostname` · `change_timezone` · `block_smtp` · `enable` · `disable_cd` · `enable_cd` · `insert_cd` · `eject_cd` · `set_slices` · `setup_vnc` · `snapshot_save` · `snapshot_restore` · `update_hdsize` · `reinstall_os`

## Conventions
- All hook methods: `public static function get*(GenericEvent $event)`
- Service type guard: `in_array($event['type'], [get_service_define('DOCKER'), get_service_define('DOCKER_STORAGE')])`
- Logging: `myadmin_log(self::$module, 'info'|'error', $msg, __LINE__, __FILE__, self::$module, $id, true, false, $custid)`
- Queue output append: `$event['output'] = $event['output'].$output;` then `$event->stopPropagation()`
- Template fetch: `$smarty->assign($serviceInfo)` → `$smarty->fetch(__DIR__.'/../templates/'.$action.'.sh.tpl')`
- Settings helpers: `add_text_setting()` · `add_select_master()` · `add_dropdown_setting()`
- Missing template: log error with `myadmin_log(..., 'error', ...)` instead of throwing

## Testing
- Tests in `tests/PluginTest.php`, extend `PHPUnit\Framework\TestCase`
- Test namespace: `Detain\MyAdminDocker\Tests`
- Use `ReflectionMethod`/`ReflectionClass` for static method signature assertions
- New hook methods need: instantiation test, signature test, hook key presence test
- Config: `phpunit.xml.dist`

<!-- caliber:managed:pre-commit -->
## Before Committing

**IMPORTANT:** Before every git commit, you MUST ensure Caliber syncs agent configs with the latest code changes.

First, check if the pre-commit hook is already installed:
```bash
grep -q "caliber" .git/hooks/pre-commit 2>/dev/null && echo "hook-active" || echo "no-hook"
```

- If **hook-active**: the hook handles sync automatically — just commit normally. Tell the user: "Caliber will sync your agent configs automatically via the pre-commit hook."
- If **no-hook**: run Caliber manually before committing:
  1. Tell the user: "Caliber: Syncing agent configs with your latest changes..."
  2. Run: `caliber refresh && git add CLAUDE.md .claude/ .cursor/ .cursorrules .github/copilot-instructions.md .github/instructions/ AGENTS.md CALIBER_LEARNINGS.md 2>/dev/null`
  3. After it completes, briefly tell the user what Caliber updated. Then proceed with the commit.

If `caliber` is not found, tell the user: "This project uses Caliber for agent config sync. Run /setup-caliber to get set up."
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->
