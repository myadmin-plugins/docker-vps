<?php

declare(strict_types=1);

namespace Detain\MyAdminDocker\Tests;

use Detain\MyAdminDocker\Plugin;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Tests for the DOCKER VPS Plugin class.
 *
 * @package Detain\MyAdminDocker\Tests
 */
class PluginTest extends TestCase
{
    /** A service type this plugin owns. */
    private const OWNED_TYPE = 'define.DOCKER';

    /** A service type belonging to some other VPS plugin. */
    private const FOREIGN_TYPE = 'define.KVM';

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__.'/Stubs.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        FrameworkState::reset();
    }

    /**
     * Tests that the Plugin class can be instantiated.
     *
     * @return void
     */
    public function testCanBeInstantiated(): void
    {
        $plugin = new Plugin();
        $this->assertInstanceOf(Plugin::class, $plugin);
    }

    /**
     * Tests that a deactivation this plugin owns is attributed to it in the log.
     *
     * This replaces testNamePropertyIsDockerVps(), which asserted
     * Plugin::$name === 'DOCKER VPS' and broke when the display name was cased as
     * 'Docker VPS'. The literal casing of a display string is not behaviour. What the
     * property is actually FOR is identifying this plugin in the operational log when
     * one of its hooks fires, so that is what is asserted here.
     *
     * @return void
     */
    public function testDeactivationIsLoggedUnderThePluginName(): void
    {
        $event = new GenericEvent(new StubService(501, 4242), ['type' => self::OWNED_TYPE]);

        Plugin::getDeactivate($event);

        $this->assertStringContainsString(
            Plugin::$name,
            FrameworkState::logText(),
            'a deactivation should be attributed to this plugin by name'
        );
        $this->assertStringContainsString('Deactivation', FrameworkState::logText());
        $this->assertSame(
            [Plugin::$module],
            array_unique(array_column(FrameworkState::$logs, 'module')),
            'the log entry should be filed against the module this plugin extends'
        );
    }

    /**
     * Tests that deactivating a service this plugin owns enqueues a delete for the host.
     *
     * @return void
     */
    public function testDeactivationEnqueuesDeleteForTheService(): void
    {
        $event = new GenericEvent(new StubService(501, 4242), ['type' => self::OWNED_TYPE]);

        Plugin::getDeactivate($event);

        $this->assertCount(1, RecordingHistory::$entries);
        $this->assertSame('vpsqueue', RecordingHistory::$entries[0]['queue']);
        $this->assertSame('delete', RecordingHistory::$entries[0]['action']);
        $this->assertSame(501, RecordingHistory::$entries[0]['id']);
        $this->assertSame(4242, RecordingHistory::$entries[0]['custid']);
    }

    /**
     * Tests that the plugin keeps its hands off service types it does not own.
     *
     * Every handler is guarded by a get_service_define() membership check. Getting that
     * wrong would make this plugin queue Docker shell scripts against KVM or Windows
     * guests, so the guard is the most important behaviour it has.
     *
     * @return void
     */
    public function testDeactivationIgnoresForeignServiceTypes(): void
    {
        $event = new GenericEvent(new StubService(501, 4242), ['type' => self::FOREIGN_TYPE]);

        Plugin::getDeactivate($event);

        $this->assertSame([], FrameworkState::$logs, 'a foreign service type must not be logged by this plugin');
        $this->assertSame([], RecordingHistory::$entries, 'a foreign service type must not be enqueued');
    }

    /**
     * Tests that both Docker service types this plugin sells are handled.
     *
     * @return void
     */
    public function testBothDockerServiceTypesAreHandled(): void
    {
        foreach (['define.DOCKER', 'define.DOCKER_STORAGE'] as $type) {
            FrameworkState::reset();
            $event = new GenericEvent(new StubService(), ['type' => $type]);

            Plugin::getDeactivate($event);

            $this->assertCount(1, RecordingHistory::$entries, "service type {$type} should be handled");
        }
    }

    /**
     * Tests that the static $description property is a non-empty string.
     *
     * @return void
     */
    public function testDescriptionPropertyIsNonEmptyString(): void
    {
        $this->assertIsString(Plugin::$description);
        $this->assertNotEmpty(Plugin::$description);
    }

    /**
     * Tests that a queued action renders this plugin's own shell template.
     *
     * This replaces testDescriptionMentionsDocker(), which grepped the marketing blurb
     * for the substring 'DOCKER'. Prose in a description property has no behaviour
     * attached and broke on a casing change. The behaviour worth pinning is that the
     * queue hook resolves an action to a template shipped by THIS package and appends
     * the rendered script to the event's output.
     *
     * @return void
     */
    public function testQueueRendersThisPackagesTemplateAndAppendsOutput(): void
    {
        $event = new GenericEvent($this->serviceInfo('delete'), [
            'type' => self::OWNED_TYPE,
            'output' => 'pre-existing;',
        ]);

        Plugin::getQueue($event);

        $this->assertCount(1, StubSmarty::$fetched, 'exactly one template should be rendered');
        $this->assertStringEndsWith('/templates/delete.sh.tpl', StubSmarty::$fetched[0]);
        $this->assertFileExists(
            StubSmarty::$fetched[0],
            'the queue hook must render a template that this package actually ships'
        );
        $this->assertSame(
            'pre-existing;#rendered:delete.sh.tpl',
            $event['output'],
            'rendered script must be appended to, not replace, the existing queue output'
        );
        $this->assertTrue($event->isPropagationStopped(), 'this plugin claims the event once it handles it');
    }

    /**
     * Tests that an action with no template is reported as an error and queues nothing.
     *
     * @return void
     */
    public function testQueueLogsErrorAndQueuesNothingForUnknownAction(): void
    {
        $event = new GenericEvent($this->serviceInfo('no_such_action'), [
            'type' => self::OWNED_TYPE,
            'output' => 'pre-existing;',
        ]);

        Plugin::getQueue($event);

        $this->assertSame([], StubSmarty::$fetched, 'no template should be rendered for an unknown action');
        $this->assertSame('pre-existing;', $event['output'], 'queue output must be left untouched');

        $errors = FrameworkState::logsAtLevel('error');
        $this->assertCount(1, $errors, 'a missing template should be logged as an error');
        $this->assertStringContainsString('no_such_action', $errors[0]['message']);
        $this->assertStringContainsString(Plugin::$name, $errors[0]['message']);
    }

    /**
     * Tests that the queue hook ignores service types this plugin does not own.
     *
     * @return void
     */
    public function testQueueIgnoresForeignServiceTypes(): void
    {
        $event = new GenericEvent($this->serviceInfo('delete'), [
            'type' => self::FOREIGN_TYPE,
            'output' => 'pre-existing;',
        ]);

        Plugin::getQueue($event);

        $this->assertSame([], StubSmarty::$fetched);
        $this->assertSame('pre-existing;', $event['output']);
        $this->assertFalse(
            $event->isPropagationStopped(),
            'another plugin must still get a chance to handle a service type this one does not own'
        );
    }

    /**
     * Builds the service info array the queue hook expects as its event subject.
     *
     * @param string $action
     * @return array<string, mixed>
     */
    private function serviceInfo(string $action): array
    {
        return [
            'action' => $action,
            'vps_id' => 501,
            'vps_custid' => 4242,
            'vps_vzid' => '77',
            'vps_hostname' => 'docker-host.example.com',
            'server_info' => ['vps_name' => 'dockernode1'],
        ];
    }

    /**
     * Tests that the static $help property is an empty string.
     *
     * @return void
     */
    public function testHelpPropertyIsEmptyString(): void
    {
        $this->assertSame('', Plugin::$help);
    }

    /**
     * Tests that the static $module property is 'vps'.
     *
     * @return void
     */
    public function testModulePropertyIsVps(): void
    {
        $this->assertSame('vps', Plugin::$module);
    }

    /**
     * Tests that the static $type property is 'service'.
     *
     * @return void
     */
    public function testTypePropertyIsService(): void
    {
        $this->assertSame('service', Plugin::$type);
    }

    /**
     * Tests that getHooks returns an array.
     *
     * @return void
     */
    public function testGetHooksReturnsArray(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertIsArray($hooks);
    }

    /**
     * Tests that getHooks returns non-empty array with expected keys.
     *
     * @return void
     */
    public function testGetHooksContainsExpectedKeys(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertNotEmpty($hooks);
        $this->assertArrayHasKey('vps.settings', $hooks);
        $this->assertArrayHasKey('vps.deactivate', $hooks);
        $this->assertArrayHasKey('vps.queue', $hooks);
    }

    /**
     * Tests that getHooks does not contain the commented-out activate hook.
     *
     * @return void
     */
    public function testGetHooksDoesNotContainActivateHook(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertArrayNotHasKey('vps.activate', $hooks);
    }

    /**
     * Tests that getHooks keys are prefixed with the module name.
     *
     * @return void
     */
    public function testGetHooksKeysArePrefixedWithModule(): void
    {
        $hooks = Plugin::getHooks();
        foreach (array_keys($hooks) as $key) {
            $this->assertStringStartsWith(Plugin::$module . '.', $key);
        }
    }

    /**
     * Tests that each hook value is a callable-style array with class and method.
     *
     * @return void
     */
    public function testGetHooksValuesAreCallableArrays(): void
    {
        $hooks = Plugin::getHooks();
        foreach ($hooks as $key => $value) {
            $this->assertIsArray($value, "Hook value for '{$key}' should be an array");
            $this->assertCount(2, $value, "Hook value for '{$key}' should have exactly 2 elements");
            $this->assertSame(Plugin::class, $value[0], "Hook '{$key}' should reference the Plugin class");
            $this->assertIsString($value[1], "Hook '{$key}' method name should be a string");
        }
    }

    /**
     * Tests that each hook callback method exists on the Plugin class.
     *
     * @return void
     */
    public function testGetHooksCallbackMethodsExist(): void
    {
        $hooks = Plugin::getHooks();
        foreach ($hooks as $key => $value) {
            $this->assertTrue(
                method_exists($value[0], $value[1]),
                "Method {$value[0]}::{$value[1]} referenced by hook '{$key}' does not exist"
            );
        }
    }

    /**
     * Tests that the settings hook references the getSettings method.
     *
     * @return void
     */
    public function testSettingsHookReferencesGetSettings(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertSame([Plugin::class, 'getSettings'], $hooks['vps.settings']);
    }

    /**
     * Tests that the deactivate hook references the getDeactivate method.
     *
     * @return void
     */
    public function testDeactivateHookReferencesGetDeactivate(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertSame([Plugin::class, 'getDeactivate'], $hooks['vps.deactivate']);
    }

    /**
     * Tests that the queue hook references the getQueue method.
     *
     * @return void
     */
    public function testQueueHookReferencesGetQueue(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertSame([Plugin::class, 'getQueue'], $hooks['vps.queue']);
    }

    /**
     * Tests that exactly three hooks are registered.
     *
     * @return void
     */
    public function testGetHooksReturnsExactlyThreeHooks(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertCount(3, $hooks);
    }

    /**
     * Tests that the getActivate method exists and accepts a GenericEvent parameter.
     *
     * @return void
     */
    public function testGetActivateMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(Plugin::class, 'getActivate');
        $this->assertTrue($reflection->isStatic());
        $this->assertTrue($reflection->isPublic());
        $params = $reflection->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('event', $params[0]->getName());
        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertSame(GenericEvent::class, $type->getName());
    }

    /**
     * Tests that the getDeactivate method exists and accepts a GenericEvent parameter.
     *
     * @return void
     */
    public function testGetDeactivateMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(Plugin::class, 'getDeactivate');
        $this->assertTrue($reflection->isStatic());
        $this->assertTrue($reflection->isPublic());
        $params = $reflection->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('event', $params[0]->getName());
        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertSame(GenericEvent::class, $type->getName());
    }

    /**
     * Tests that the getSettings method exists and accepts a GenericEvent parameter.
     *
     * @return void
     */
    public function testGetSettingsMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(Plugin::class, 'getSettings');
        $this->assertTrue($reflection->isStatic());
        $this->assertTrue($reflection->isPublic());
        $params = $reflection->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('event', $params[0]->getName());
        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertSame(GenericEvent::class, $type->getName());
    }

    /**
     * Tests that the getQueue method exists and accepts a GenericEvent parameter.
     *
     * @return void
     */
    public function testGetQueueMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(Plugin::class, 'getQueue');
        $this->assertTrue($reflection->isStatic());
        $this->assertTrue($reflection->isPublic());
        $params = $reflection->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('event', $params[0]->getName());
        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertSame(GenericEvent::class, $type->getName());
    }

    /**
     * Tests that the Plugin class exists in the correct namespace.
     *
     * @return void
     */
    public function testPluginClassExistsInCorrectNamespace(): void
    {
        $this->assertTrue(class_exists(Plugin::class));
        $reflection = new \ReflectionClass(Plugin::class);
        $this->assertSame('Detain\MyAdminDocker', $reflection->getNamespaceName());
    }

    /**
     * Tests that the Plugin class is not abstract or final.
     *
     * @return void
     */
    public function testPluginClassIsConcreteAndNotFinal(): void
    {
        $reflection = new \ReflectionClass(Plugin::class);
        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isFinal());
    }

    /**
     * Tests that the constructor takes no required parameters.
     *
     * @return void
     */
    public function testConstructorHasNoRequiredParameters(): void
    {
        $reflection = new \ReflectionClass(Plugin::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $this->assertCount(0, $constructor->getParameters());
    }

    /**
     * Tests that all static properties are publicly accessible.
     *
     * @return void
     */
    public function testAllStaticPropertiesArePublic(): void
    {
        $reflection = new \ReflectionClass(Plugin::class);
        $staticProperties = $reflection->getStaticProperties();
        $expectedProperties = ['name', 'description', 'help', 'module', 'type'];
        foreach ($expectedProperties as $prop) {
            $this->assertArrayHasKey($prop, $staticProperties, "Static property '{$prop}' should exist");
            $refProp = $reflection->getProperty($prop);
            $this->assertTrue($refProp->isPublic(), "Property '{$prop}' should be public");
            $this->assertTrue($refProp->isStatic(), "Property '{$prop}' should be static");
        }
    }

    /**
     * Tests that all static properties are strings.
     *
     * @return void
     */
    public function testAllStaticPropertiesAreStrings(): void
    {
        $this->assertIsString(Plugin::$name);
        $this->assertIsString(Plugin::$description);
        $this->assertIsString(Plugin::$help);
        $this->assertIsString(Plugin::$module);
        $this->assertIsString(Plugin::$type);
    }

    /**
     * Tests that the description contains a URL to the DOCKER project site.
     *
     * @return void
     */
    public function testDescriptionContainsDockerUrl(): void
    {
        $this->assertStringContainsString('https://www.linux-docker.org/', Plugin::$description);
    }

    /**
     * Tests that the Plugin class has exactly the expected public static methods.
     *
     * @return void
     */
    public function testExpectedPublicStaticMethods(): void
    {
        $reflection = new \ReflectionClass(Plugin::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_STATIC);
        $methodNames = array_map(fn(\ReflectionMethod $m) => $m->getName(), $methods);
        $expectedMethods = ['getHooks', 'getActivate', 'getDeactivate', 'getSettings', 'getQueue'];
        foreach ($expectedMethods as $method) {
            $this->assertContains($method, $methodNames, "Public static method '{$method}' should exist");
        }
    }

    /**
     * Tests that the hook keys use dot notation consistently.
     *
     * @return void
     */
    public function testHookKeysUseDotNotation(): void
    {
        $hooks = Plugin::getHooks();
        foreach (array_keys($hooks) as $key) {
            $this->assertMatchesRegularExpression('/^[a-z]+\.[a-z]+$/', $key, "Hook key '{$key}' should use dot notation");
        }
    }

    /**
     * Tests that the settings hook contributes this plugin's own settings only.
     *
     * This replaces testDescriptionMentionsSellingVps(), which asserted the description
     * prose contained 'selling of DOCKER VPS' and broke on a casing change. What makes
     * this plugin sellable is not the blurb, it is the settings hook: it must register a
     * per-slice cost, default servers and out-of-stock switches, all scoped to the vps
     * module and namespaced to docker so they cannot collide with a sibling VPS plugin.
     *
     * @return void
     */
    public function testSettingsHookRegistersDockerScopedSalesSettings(): void
    {
        $settings = new class {
            /** @var list<array{kind: string, module: string, name: string}> */
            public array $registered = [];

            /** @var list<string> */
            public array $targets = [];

            public function setTarget($target)
            {
                $this->targets[] = (string) $target;
            }

            public function get_setting($name)
            {
                return '';
            }

            public function add_text_setting($module, $group, $name, $label = '', $help = '', $value = null)
            {
                $this->registered[] = ['kind' => 'text', 'module' => (string) $module, 'name' => (string) $name];
            }

            public function add_dropdown_setting($module, $group, $name, $label = '', $help = '', $value = null, $options = [], $labels = [])
            {
                $this->registered[] = ['kind' => 'dropdown', 'module' => (string) $module, 'name' => (string) $name];
            }

            public function add_select_master($module, $group, $module2, $name, $label = '', $value = null, $type = null, $location = null)
            {
                $this->registered[] = ['kind' => 'select_master', 'module' => (string) $module, 'name' => (string) $name];
            }
        };

        Plugin::getSettings(new GenericEvent($settings));

        $names = array_column($settings->registered, 'name');
        $this->assertNotEmpty($names, 'the settings hook must register settings');
        $this->assertContains('vps_slice_docker_cost', $names, 'per-slice pricing must be configurable');
        $this->assertContains('new_vps_docker_server', $names, 'a default server must be configurable');
        $this->assertContains('outofstock_docker', $names, 'sales must be switchable off');

        foreach ($names as $name) {
            $this->assertMatchesRegularExpression(
                '/docker/',
                $name,
                "setting '{$name}' must be namespaced to docker so it cannot collide with another VPS plugin"
            );
        }
        foreach ($settings->registered as $setting) {
            $this->assertSame(
                Plugin::$module,
                $setting['module'],
                "setting '{$setting['name']}' must be scoped to the ".Plugin::$module.' module'
            );
        }

        // The hook scopes itself to the module and hands the global scope back afterwards,
        // otherwise every setting a later plugin registers would land under this module.
        $this->assertSame(['module', 'global'], $settings->targets);
    }

    /**
     * Tests that the module value matches what hook keys use as prefix.
     *
     * @return void
     */
    public function testModuleValueMatchesHookKeyPrefix(): void
    {
        $hooks = Plugin::getHooks();
        foreach (array_keys($hooks) as $key) {
            $prefix = explode('.', $key)[0];
            $this->assertSame(Plugin::$module, $prefix);
        }
    }

    /**
     * Tests that multiple Plugin instances are independent.
     *
     * @return void
     */
    public function testMultipleInstancesAreIndependent(): void
    {
        $plugin1 = new Plugin();
        $plugin2 = new Plugin();
        $this->assertNotSame($plugin1, $plugin2);
        $this->assertInstanceOf(Plugin::class, $plugin1);
        $this->assertInstanceOf(Plugin::class, $plugin2);
    }

    /**
     * Tests that getHooks returns consistent results across multiple calls.
     *
     * @return void
     */
    public function testGetHooksIsIdempotent(): void
    {
        $hooks1 = Plugin::getHooks();
        $hooks2 = Plugin::getHooks();
        $this->assertSame($hooks1, $hooks2);
    }

    /**
     * Tests that the Plugin class does not implement any interfaces.
     *
     * @return void
     */
    public function testPluginDoesNotImplementInterfaces(): void
    {
        $reflection = new \ReflectionClass(Plugin::class);
        $this->assertEmpty($reflection->getInterfaceNames());
    }

    /**
     * Tests that the Plugin class does not extend another class.
     *
     * @return void
     */
    public function testPluginDoesNotExtendAnyClass(): void
    {
        $reflection = new \ReflectionClass(Plugin::class);
        $this->assertFalse($reflection->getParentClass());
    }

    /**
     * Tests that the description mentions both Intel VT and AMD-V.
     *
     * @return void
     */
    public function testDescriptionMentionsVirtualizationExtensions(): void
    {
        $this->assertStringContainsString('Intel VT', Plugin::$description);
        $this->assertStringContainsString('AMD-V', Plugin::$description);
    }
}
