---
name: add-vps-template
description: Creates new Smarty shell script templates for a Docker VPS action. Use when user says 'add action', 'new template', 'add vps command', or adds a new operation type requiring both templates/{action}.sh.tpl and templates/backup/{action}.sh.tpl. Do NOT use for modifying existing templates or for non-template plugin changes.
---
# Add VPS Template

## Critical

- **Always create BOTH files**: a main Smarty shell script in `templates/` AND a matching file in `templates/backup/`. The queue handler in `src/Plugin.php` (see `getQueue()`) checks only the main `templates/` directory, but backup VPS operations require the parallel file.
- **Never skip `|escapeshellarg`** on any Smarty variable interpolated into shell commands. Raw `{$vps_vzid}` in shell context is an injection risk.
- Action name must be **snake_case** and match exactly what `$serviceInfo['action']` will contain at runtime — the filename IS the dispatch key.
- Do NOT register a new hook or modify `src/Plugin.php` — template dispatch is automatic via `$smarty->fetch(__DIR__.'/../templates/'.$serviceInfo['action'].'.sh.tpl')`.

## Instructions

1. **Determine the action name.** Confirm it is snake_case (e.g., `resize_disk`, `enable_firewall`). Verify the name does not already exist:
   ```bash
   ls templates/ | grep 'resize_disk'
   ```
   Stop if the file already exists — use the modify flow instead.

2. **Identify available Smarty variables.** The queue assigns the full `$serviceInfo` array via `$smarty->assign($serviceInfo)` in `src/Plugin.php`. Common variables:
   - `{$vps_vzid}` — container ID
   - `{$param}` — single action parameter (IP, hostname, etc.)
   - `{$vps_hostname}`, `{$vps_id}`, `{$vps_custid}`
   - Settings fields from `$server_info`

3. **Create the main template** in `templates/` (e.g., `templates/resize_disk.sh.tpl`) — Docker-native command using `provirted.phar`:
   ```smarty
   /root/cpaneldirect/provirted.phar {cli-subcommand} --virt=docker {$vps_vzid|escapeshellarg};
   ```
   For actions with a parameter:
   ```smarty
   /root/cpaneldirect/provirted.phar {cli-subcommand} --{flag}={$param|escapeshellarg} --virt=docker {$vps_vzid|escapeshellarg};
   ```
   Verify: open the file and confirm `|escapeshellarg` is on every interpolated variable.

4. **Create the backup template** in `templates/backup/` (e.g., `templates/backup/resize_disk.sh.tpl`) — libvirt/virsh fallback for backup VPS type. Mirror the operation using `virsh` or host-level tooling:
   ```bash
   export PATH="$PATH:/usr/sbin:/sbin:/bin:/usr/bin:";
   virsh {virsh-subcommand} {$vps_vzid};
   bash /root/cpaneldirect/run_buildebtables.sh;
   ```
   If no virsh equivalent exists, add a comment and a no-op so the file is non-empty and dispatch does not error.

5. **Run tests** to confirm nothing is broken:
   ```bash
   vendor/bin/phpunit tests/ -v
   ```
   All existing tests must pass. Template files are not directly tested but dispatch failure produces a logged error (not an exception), so runtime-verify by checking `myadmin_log` output if possible.

## Examples

**User says:** "Add a `resize_disk` VPS action"

**Actions taken:**

`templates/resize_disk.sh.tpl`:
```smarty
/root/cpaneldirect/provirted.phar update --hd={$param|escapeshellarg} --virt=docker {$vps_vzid|escapeshellarg};
```

`templates/backup/resize_disk.sh.tpl`:
```bash
export PATH="$PATH:/usr/sbin:/sbin:/bin:/usr/bin:";
virsh blockresize {$vps_vzid} --path vda --size {$param}G;
```

**Result:** Queue handler in `src/Plugin.php` resolves `$serviceInfo['action'] === 'resize_disk'`, fetches `templates/resize_disk.sh.tpl`, and appends rendered output to `$event['output']`.

## Common Issues

- **"Does not Exist for Docker VPS" in logs** (from `getQueue()` in `src/Plugin.php`): The action string dispatched does not match the filename. Check `$serviceInfo['action']` value at call time — it must be identical to the template basename (including underscores vs hyphens).
- **Shell variables like `$PATH` being treated as Smarty variables**: Escape dollar signs in shell heredocs with `{literal}...{/literal}` blocks, or use the `export PATH=...` pattern seen in backup templates (no Smarty variable conflict since `PATH` is not assigned).
- **Backup template missing → silent failure**: `getQueue()` only checks `templates/`, not `templates/backup/`. A missing backup template causes no PHP error but the backup VPS type will run the wrong shell command. Always create both files.
- **`vendor/bin/phpunit` reports no tests for new template**: Template files have no direct unit tests. If test coverage is required, add a `testTemplateFileExists` assertion in `tests/PluginTest.php` checking `file_exists(__DIR__.'/../templates/resize_disk.sh.tpl')`.
