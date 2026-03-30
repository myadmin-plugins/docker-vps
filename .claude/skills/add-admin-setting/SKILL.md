---
name: add-admin-setting
description: Adds a new admin setting inside getSettings() in src/Plugin.php using add_text_setting(), add_select_master(), or add_dropdown_setting(). Use when user says 'add setting', 'new config option', 'admin panel field', or adds a new per-datacenter control. Do NOT use for adding event hooks or queue actions.
---
# add-admin-setting

## Critical

- All setting calls go **inside `getSettings()`**, between `$settings->setTarget('module')` and `$settings->setTarget('global')` — never outside this block.
- The setting key (3rd argument) must be **lowercase with underscores** (e.g. `vps_slice_docker_cost`). The corresponding constant used by `get_setting()` is the **UPPERCASE** version (e.g. `VPS_SLICE_DOCKER_COST`).
- Never add raw DB calls or logic inside `getSettings()` — it is purely declarative.

## Instructions

1. **Identify the setting type** needed:
   - Free-form text/numeric value → `add_text_setting()`
   - Server selector (datacenter dropdown populated from server table) → `add_select_master()`
   - Fixed-choice dropdown (Yes/No, enable/disable) → `add_dropdown_setting()`
   - Verify the method exists on `\MyAdmin\Settings` before proceeding.

2. **Open `src/Plugin.php`** and locate `getSettings()`. All additions go between the two `setTarget` calls:
   ```php
   $settings->setTarget('module');   // <-- insert after this
   // ... existing settings ...
   $settings->setTarget('global');   // <-- insert before this
   ```

3. **Add a text setting** (free-form value):
   ```php
   $settings->add_text_setting(
       self::$module,                                      // module: 'vps'
       _('Slice Costs'),                                   // group label (i18n)
       'vps_slice_docker_cost',                            // key (lowercase)
       _('Docker VPS Cost Per Slice'),                     // field label (i18n)
       _('Docker VPS will cost this much for 1 slice.'),   // description (i18n)
       $settings->get_setting('VPS_SLICE_DOCKER_COST')     // current value (UPPERCASE key)
   );
   ```

4. **Add a server selector** (per-datacenter default server):
   ```php
   $settings->add_select_master(
       _(self::$module),               // module label (i18n)
       _('Default Servers'),           // group label (i18n)
       self::$module,                  // module: 'vps'
       'new_vps_docker_server',        // key (lowercase)
       _('Docker NJ Server'),          // field label (i18n)
       NEW_VPS_DOCKER_SERVER,          // current value constant
       14,                             // server type ID
       1                               // datacenter ID
   );
   ```
   Add one call per datacenter, incrementing the last argument (datacenter ID: 1=NJ, 2=LA, 3=TX, etc.).

5. **Add a dropdown setting** (binary or fixed-choice):
   ```php
   $settings->add_dropdown_setting(
       self::$module,                                      // module: 'vps'
       _('Out of Stock'),                                  // group label (i18n)
       'outofstock_docker_tx',                             // key (lowercase)
       _('Out Of Stock Docker TX'),                        // field label (i18n)
       _('Enable/Disable Sales Of This Type'),             // description (i18n)
       $settings->get_setting('OUTOFSTOCK_DOCKER_TX'),     // current value (UPPERCASE key)
       ['0', '1'],                                         // option values
       ['No', 'Yes']                                       // option labels
   );
   ```

6. **Verify:** After editing, run `vendor/bin/phpunit tests/ -v` — all existing tests must pass. No new test is required for a settings-only addition, but confirm `testGetSettingsExists` still passes.

## Examples

**User says:** "Add an out-of-stock toggle for Docker Miami and a default server selector for Miami."

**Actions taken:**
1. Open `src/Plugin.php`, find `getSettings()`.
2. After the last `add_select_master` call, add:
   ```php
   $settings->add_select_master(_(self::$module), _('Default Servers'), self::$module, 'new_vps_miami_docker_server', _('Docker Miami Server'), NEW_VPS_MIAMI_DOCKER_SERVER, 14, 4);
   ```
3. After the last `add_dropdown_setting` call, add:
   ```php
   $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_docker_miami', _('Out Of Stock Docker Miami'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_DOCKER_MIAMI'), ['0', '1'], ['No', 'Yes']);
   ```
4. Run `vendor/bin/phpunit tests/ -v` — confirm green.

**Result:** Two new fields appear in the admin settings panel under their respective groups.

## Common Issues

- **`get_setting()` returns null / constant undefined:** The constant (e.g. `NEW_VPS_MIAMI_DOCKER_SERVER`) must be defined in the core MyAdmin config before it is usable. If undefined, PHP will throw a notice and use `''`. Define the constant in the appropriate core config file first.
- **Setting not appearing in admin panel:** Confirm `$settings->setTarget('module')` is called before your addition — if you inserted the line before that call, it targets `'global'` and may appear in the wrong section.
- **i18n string not translating:** All label/description strings must be wrapped in `_('...')`. Bare strings are silently accepted but never translated.
- **PHPUnit failure `testGetSettingsExists`:** Ensure the method signature `public static function getSettings(GenericEvent $event)` is unchanged — adding settings inside the body is safe, changing the signature breaks the reflection test.