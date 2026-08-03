<?php

/**
 * Test doubles that let the Plugin's event handlers be EXECUTED rather than described.
 *
 * The framework helpers the handlers call are unqualified, so PHP resolves them against
 * the Detain\MyAdminDocker namespace before falling back to global scope. Defining them
 * here therefore intercepts them for the code under test without shadowing anything the
 * plugin-installer package installs globally.
 *
 * Every definition is guarded so this file is safe to include from several test classes.
 */

namespace Detain\MyAdminDocker\Tests {
    /**
     * Mutable state shared with the stubs defined below.
     */
    final class FrameworkState
    {
        /** @var list<array{module: string, level: string, message: string}> myadmin_log() calls */
        public static array $logs = [];

        /** @var array<string, array<string, string>> settings returned by get_module_settings() */
        public static array $moduleSettings = ['vps' => ['PREFIX' => 'vps', 'TABLE' => 'vps', 'TITLE' => 'VPS']];

        public static function reset(): void
        {
            self::$logs = [];
            self::$moduleSettings = ['vps' => ['PREFIX' => 'vps', 'TABLE' => 'vps', 'TITLE' => 'VPS']];
            RecordingHistory::$entries = [];
            StubSmarty::$assigned = [];
            StubSmarty::$fetched = [];
        }

        /** Every logged message, of any level, as one string. */
        public static function logText(): string
        {
            return implode("\n", array_column(self::$logs, 'message'));
        }

        /** @return list<array{module: string, level: string, message: string}> */
        public static function logsAtLevel(string $level): array
        {
            return array_values(array_filter(
                self::$logs,
                static fn (array $entry): bool => $entry['level'] === $level
            ));
        }
    }

    /**
     * Recording stand-in for \MyAdmin\App::history(), which is how a deactivation
     * enqueues work for the host server.
     */
    final class RecordingHistory
    {
        /** @var list<array{queue: string, id: mixed, action: string, extra: mixed, custid: mixed}> */
        public static array $entries = [];

        public function add($queue, $id, $action, $extra = '', $custid = null)
        {
            self::$entries[] = [
                'queue' => (string) $queue,
                'id' => $id,
                'action' => (string) $action,
                'extra' => $extra,
                'custid' => $custid,
            ];
            return true;
        }
    }

    /**
     * Service stand-in for the subject of activate/deactivate events.
     */
    final class StubService
    {
        /** @var int */
        private $id;

        /** @var int */
        private $custid;

        public function __construct(int $id = 501, int $custid = 4242)
        {
            $this->id = $id;
            $this->custid = $custid;
        }

        public function getId()
        {
            return $this->id;
        }

        public function getCustid()
        {
            return $this->custid;
        }
    }

    /**
     * Smarty stand-in that renders a template by echoing back its path and the data it
     * was handed, so tests can see which template a queued action used.
     */
    class StubSmarty
    {
        /** @var list<array<string, mixed>> */
        public static array $assigned = [];

        /** @var list<string> */
        public static array $fetched = [];

        public function assign($data, $value = null)
        {
            if (is_array($data)) {
                self::$assigned[] = $data;
            } else {
                self::$assigned[] = [$data => $value];
            }
        }

        public function fetch($template)
        {
            self::$fetched[] = (string) $template;
            return '#rendered:'.basename((string) $template);
        }
    }
}

namespace MyAdmin {
    if (!\class_exists(App::class, false)) {
        /**
         * Minimal stand-in for \MyAdmin\App exposing only the statics this plugin uses.
         */
        class App
        {
            /** @return \Detain\MyAdminDocker\Tests\RecordingHistory */
            public static function history()
            {
                return new \Detain\MyAdminDocker\Tests\RecordingHistory();
            }
        }
    }
}

namespace Detain\MyAdminDocker {
    use Detain\MyAdminDocker\Tests\FrameworkState;

    if (!\function_exists(__NAMESPACE__.'\get_service_define')) {
        /**
         * Mirrors the framework helper: a service name maps to a stable identifier.
         *
         * @param string $service
         * @return string
         */
        function get_service_define($service)
        {
            return 'define.'.$service;
        }
    }

    if (!\function_exists(__NAMESPACE__.'\myadmin_log')) {
        /**
         * @param string $module
         * @param string $level
         * @param string $message
         * @return void
         */
        function myadmin_log($module, $level, $message, $line = 0, $file = '', ...$rest)
        {
            FrameworkState::$logs[] = [
                'module' => (string) $module,
                'level' => (string) $level,
                'message' => (string) $message,
            ];
        }
    }

    if (!\function_exists(__NAMESPACE__.'\get_module_settings')) {
        /**
         * @param string $module
         * @return array<string, string>
         */
        function get_module_settings($module = 'default')
        {
            return FrameworkState::$moduleSettings[$module] ?? [];
        }
    }
}

namespace Detain\MyAdminDocker {
    // gettext is optional in CI; fall through to the real _() when the extension is loaded.
    if (!\function_exists('_')) {
        /**
         * @param string $message
         * @return string
         */
        function _($message)
        {
            return $message;
        }
    }
}

namespace {
    // Default-server ids normally come from the settings system.
    foreach ([
        'NEW_VPS_DOCKER_SERVER' => 101,
        'NEW_VPS_LA_DOCKER_SERVER' => 102,
        'NEW_VPS_DOCKER_STORAGE_SERVER' => 103,
    ] as $constant => $value) {
        if (!defined($constant)) {
            define($constant, $value);
        }
    }

    if (!class_exists('TFSmarty')) {
        /**
         * The plugin instantiates \TFSmarty directly, so the global name has to exist.
         * It simply forwards to the recording stub.
         */
        class TFSmarty extends \Detain\MyAdminDocker\Tests\StubSmarty
        {
        }
    }
}
