---
name: add-hook-method
description: Adds a new event hook handler to src/Plugin.php with the correct public static signature, DOCKER/DOCKER_STORAGE service type guard, myadmin_log call, and stopPropagation(). Use when user says 'add hook', 'new event handler', 'handle event', or adds a new lifecycle event. Do NOT use for modifying getSettings or editing existing hook logic.
---
# add-hook-method

## Critical

- Every hook method MUST be `public static function get*(GenericEvent $event)` — never instance methods.
- Service type guard `in_array($event['type'], [get_service_define('DOCKER'), get_service_define('DOCKER_STORAGE')])` MUST wrap all logic — without it the handler fires for every VPS type.
- `$event->stopPropagation()` MUST be called inside the type guard block (not outside) so other plugins handle other service types.
- New hooks MUST be registered in `getHooks()` as `self::$module.'.eventname' => [__CLASS__, 'getMethodName']`.
- Do NOT skip the `myadmin_log` call — it is required for audit trails.

## Instructions

1. **Identify the hook event name and method name.**
   - Hook key format: `vps.{eventname}` (e.g. `vps.suspend`).
   - Method name: `get` + PascalCase event name (e.g. `getSuspend`).
   - Verify no existing method with that name exists in `src/Plugin.php` before proceeding.

2. **Add the method to `src/Plugin.php`** after the last `get*` method, before the closing `}`:
   ```php
   /**
    * @param \Symfony\Component\EventDispatcher\GenericEvent $event
    */
   public static function getEventName(GenericEvent $event)
   {
       if (in_array($event['type'], [get_service_define('DOCKER'), get_service_define('DOCKER_STORAGE')])) {
           $serviceClass = $event->getSubject();
           myadmin_log(self::$module, 'info', self::$name.' EventLabel', __LINE__, __FILE__, self::$module, $serviceClass->getId(), true, false, $serviceClass->getCustid());
           $event->stopPropagation();
       }
   }
   ```
   - Replace `getEventName` with the PascalCase method name.
   - Replace `'EventLabel'` with a human-readable action label (e.g. `'Suspension'`).
   - If the event needs queue output (like `getQueue`), assign `$event['output'] = $event['output'].$output;` before `stopPropagation()`.

3. **Register the hook in `getHooks()`** — add one line to the returned array:
   ```php
   self::$module.'.eventname' => [__CLASS__, 'getEventName'],
   ```
   Verify the key uses dot notation matching the pattern `/^[a-z]+\.[a-z]+$/`.

4. **Add three tests to `tests/PluginTest.php`:**

   a. Signature test (using `ReflectionMethod`):
   ```php
   public function testGetEventNameMethodSignature(): void
   {
       $reflection = new \ReflectionMethod(Plugin::class, 'getEventName');
       $this->assertTrue($reflection->isStatic());
       $this->assertTrue($reflection->isPublic());
       $params = $reflection->getParameters();
       $this->assertCount(1, $params);
       $this->assertSame('event', $params[0]->getName());
       $type = $params[0]->getType();
       $this->assertNotNull($type);
       $this->assertSame(GenericEvent::class, $type->getName());
   }
   ```

   b. Hook key presence test:
   ```php
   public function testGetHooksContainsEventNameHook(): void
   {
       $hooks = Plugin::getHooks();
       $this->assertArrayHasKey('vps.eventname', $hooks);
       $this->assertSame([Plugin::class, 'getEventName'], $hooks['vps.eventname']);
   }
   ```

   c. Update `testGetHooksReturnsExactlyThreeHooks` count to reflect the new total.

5. **Run tests** to confirm all pass:
   ```bash
   vendor/bin/phpunit tests/ -v
   ```
   All existing tests must still pass. Fix any count assertion on hook totals.

## Examples

**User says:** "Add a suspend hook handler"

**Actions taken:**
1. Method name → `getSuspend`, hook key → `vps.suspend`
2. Add to `src/Plugin.php`:
   ```php
   public static function getSuspend(GenericEvent $event)
   {
       if (in_array($event['type'], [get_service_define('DOCKER'), get_service_define('DOCKER_STORAGE')])) {
           $serviceClass = $event->getSubject();
           myadmin_log(self::$module, 'info', self::$name.' Suspension', __LINE__, __FILE__, self::$module, $serviceClass->getId(), true, false, $serviceClass->getCustid());
           $event->stopPropagation();
       }
   }
   ```
3. Add to `getHooks()` return array: `self::$module.'.suspend' => [__CLASS__, 'getSuspend'],`
4. Add `testGetSuspendMethodSignature`, `testGetHooksContainsSuspendHook` tests; update hook count to 4.
5. Run `vendor/bin/phpunit tests/ -v` → all green.

## Common Issues

- **`testGetHooksReturnsExactlyThreeHooks` fails:** You added a hook but didn't update the count assertion. Change `assertCount(3, $hooks)` to the new total.
- **`method_exists` hook callback test fails:** Method name in `getHooks()` doesn't match the actual method name — check PascalCase spelling matches exactly.
- **Hook fires for all VPS types:** Missing or misplaced `in_array` type guard — ensure the entire method body is inside the `if` block.
- **`stopPropagation()` called outside the guard:** Moving it outside means other plugins never handle non-Docker events. It must be the last statement inside the `if` block.
- **`vendor/bin/phpunit` not found:** Run `composer install` first to install PHPUnit into `vendor/`.