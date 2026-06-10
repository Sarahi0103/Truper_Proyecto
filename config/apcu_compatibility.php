<?php
/**
 * APCu Compatibility Polyfill for environments without apcu extension (like Render production).
 * Uses request-lifetime memory and $_SESSION for persistence across requests.
 */

// If extension is already loaded, do nothing
if (!extension_loaded('apcu')) {

    // Global in-memory request-lifetime cache
    $GLOBALS['_APCU_FALLBACK_MEM'] = [];

    // Define APCUIterator class if it doesn't exist
    if (!class_exists('APCUIterator')) {
        class APCUIterator implements Iterator {
            private $keys = [];
            private $position = 0;
            private $pattern;

            public function __construct($pattern = null, $format = 0, $chunk_size = 100, $list = 0) {
                $this->pattern = $pattern;
                $this->position = 0;
                
                // Populate keys matching the pattern from session
                if (isset($_SESSION['__apcu_fallback']) && is_array($_SESSION['__apcu_fallback'])) {
                    foreach ($_SESSION['__apcu_fallback'] as $key => $data) {
                        if (empty($pattern) || @preg_match($pattern, $key)) {
                            // Check if not expired
                            if ($data['expires'] >= time()) {
                                $this->keys[$key] = $data['value'];
                            }
                        }
                    }
                }
            }

            public function getPattern() {
                return $this->pattern;
            }

            #[\ReturnTypeWillChange]
            public function rewind() {
                $this->position = 0;
            }

            #[\ReturnTypeWillChange]
            public function current() {
                $keys = array_keys($this->keys);
                $key = $keys[$this->position];
                return [
                    'key' => $key,
                    'value' => $this->keys[$key],
                ];
            }

            #[\ReturnTypeWillChange]
            public function key() {
                $keys = array_keys($this->keys);
                return $keys[$this->position];
            }

            #[\ReturnTypeWillChange]
            public function next() {
                ++$this->position;
            }

            #[\ReturnTypeWillChange]
            public function valid() {
                $keys = array_keys($this->keys);
                return isset($keys[$this->position]);
            }
        }
    }

    if (!function_exists('apcu_enabled')) {
        function apcu_enabled() {
            return true;
        }
    }

    if (!function_exists('apcu_store')) {
        function apcu_store($key, $var, $ttl = 0) {
            $expires = ($ttl > 0) ? (time() + $ttl) : (time() + 86400 * 30); // 30 days default if 0
            
            // Store in request memory
            $GLOBALS['_APCU_FALLBACK_MEM'][$key] = [
                'value' => $var,
                'expires' => $expires
            ];

            // Store in Session if session is active
            if (session_status() === PHP_SESSION_ACTIVE) {
                if (!isset($_SESSION['__apcu_fallback']) || !is_array($_SESSION['__apcu_fallback'])) {
                    $_SESSION['__apcu_fallback'] = [];
                }
                $_SESSION['__apcu_fallback'][$key] = [
                    'value' => $var,
                    'expires' => $expires
                ];
            }

            return true;
        }
    }

    if (!function_exists('apcu_fetch')) {
        function apcu_fetch($key, &$success = null) {
            $success = false;

            // 1. Try request-lifetime memory
            if (isset($GLOBALS['_APCU_FALLBACK_MEM'][$key])) {
                $data = $GLOBALS['_APCU_FALLBACK_MEM'][$key];
                if ($data['expires'] >= time()) {
                    $success = true;
                    return $data['value'];
                }
                unset($GLOBALS['_APCU_FALLBACK_MEM'][$key]);
            }

            // 2. Try session
            if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['__apcu_fallback'][$key])) {
                $data = $_SESSION['__apcu_fallback'][$key];
                if ($data['expires'] >= time()) {
                    $success = true;
                    // Cache in request memory for future calls in same request
                    $GLOBALS['_APCU_FALLBACK_MEM'][$key] = $data;
                    return $data['value'];
                }
                unset($_SESSION['__apcu_fallback'][$key]);
            }

            return false;
        }
    }

    if (!function_exists('apcu_delete')) {
        function apcu_delete($key) {
            if ($key instanceof APCUIterator) {
                $pattern = $key->getPattern();
                $keysToDelete = [];

                if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['__apcu_fallback']) && is_array($_SESSION['__apcu_fallback'])) {
                    foreach (array_keys($_SESSION['__apcu_fallback']) as $k) {
                        if (empty($pattern) || @preg_match($pattern, $k)) {
                            $keysToDelete[] = $k;
                        }
                    }
                }

                // Also check request memory
                foreach (array_keys($GLOBALS['_APCU_FALLBACK_MEM']) as $k) {
                    if (empty($pattern) || @preg_match($pattern, $k)) {
                        if (!in_array($k, $keysToDelete, true)) {
                            $keysToDelete[] = $k;
                        }
                    }
                }

                foreach ($keysToDelete as $k) {
                    unset($GLOBALS['_APCU_FALLBACK_MEM'][$k]);
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        unset($_SESSION['__apcu_fallback'][$k]);
                    }
                }
                return true;
            } elseif (is_array($key)) {
                $success = true;
                foreach ($key as $k) {
                    if (!apcu_delete($k)) {
                        $success = false;
                    }
                }
                return $success;
            } else {
                unset($GLOBALS['_APCU_FALLBACK_MEM'][$key]);
                if (session_status() === PHP_SESSION_ACTIVE) {
                    unset($_SESSION['__apcu_fallback'][$key]);
                }
                return true;
            }
        }
    }

    if (!function_exists('apcu_clear_cache')) {
        function apcu_clear_cache() {
            $GLOBALS['_APCU_FALLBACK_MEM'] = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['__apcu_fallback'] = [];
            }
            return true;
        }
    }
}
