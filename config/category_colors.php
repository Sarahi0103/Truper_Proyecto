<?php
/**
 * Configuración y Helper de Colores para Categorías
 * Truper Platform
 */

if (!function_exists('hex_to_rgba_helper')) {
    function hex_to_rgba_helper(string $hex, float $alpha = 0.18): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } elseif (strlen($hex) === 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        } else {
            return "rgba(255, 127, 0, {$alpha})";
        }
        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }
}

if (!function_exists('create_category_color_style')) {
    function create_category_color_style(string $hexColor): array {
        $hexColor = trim($hexColor);
        if ($hexColor !== '' && strpos($hexColor, '#') !== 0) {
            $hexColor = '#' . $hexColor;
        }
        if (!preg_match('/^#[0-9a-fA-F]{3,6}$/', $hexColor)) {
            $hexColor = '#ff7f00';
        }
        return [
            'bg' => hex_to_rgba_helper($hexColor, 0.18),
            'border' => $hexColor,
            'color' => $hexColor,
            'dot' => $hexColor
        ];
    }
}

if (!function_exists('get_category_color_map')) {
    function get_category_color_map(): array {
        return [
            'material electrico' => [
                'bg' => 'rgba(14, 165, 233, 0.18)',
                'border' => '#0ea5e9',
                'color' => '#38bdf8',
                'dot' => '#0ea5e9'
            ],
            'fontaneria' => [
                'bg' => 'rgba(20, 184, 166, 0.18)',
                'border' => '#14b8a6',
                'color' => '#2dd4bf',
                'dot' => '#14b8a6'
            ],
            'cerrajeria' => [
                'bg' => 'rgba(245, 158, 11, 0.18)',
                'border' => '#f59e0b',
                'color' => '#fbbf24',
                'dot' => '#f59e0b'
            ],
            'herreria' => [
                'bg' => 'rgba(239, 68, 68, 0.18)',
                'border' => '#ef4444',
                'color' => '#f87171',
                'dot' => '#ef4444'
            ],
            'herramientas' => [
                'bg' => 'rgba(168, 85, 247, 0.18)',
                'border' => '#a855f7',
                'color' => '#c084fc',
                'dot' => '#a855f7'
            ],
            'pintura' => [
                'bg' => 'rgba(16, 185, 129, 0.18)',
                'border' => '#10b981',
                'color' => '#34d399',
                'dot' => '#10b981'
            ],
            'automotriz' => [
                'bg' => 'rgba(236, 72, 153, 0.18)',
                'border' => '#ec4899',
                'color' => '#f472b6',
                'dot' => '#ec4899'
            ],
            'jardineria' => [
                'bg' => 'rgba(132, 204, 22, 0.18)',
                'border' => '#84cc16',
                'color' => '#a3e635',
                'dot' => '#84cc16'
            ],
            'fijacion' => [
                'bg' => 'rgba(99, 102, 241, 0.18)',
                'border' => '#6366f1',
                'color' => '#818cf8',
                'dot' => '#6366f1'
            ],
            'medicion' => [
                'bg' => 'rgba(251, 146, 60, 0.18)',
                'border' => '#fb923c',
                'color' => '#fdba74',
                'dot' => '#fb923c'
            ],
            'marketplace ce' => [
                'bg' => 'rgba(255, 102, 0, 0.2)',
                'border' => '#ff6600',
                'color' => '#ff9933',
                'dot' => '#ff6600'
            ]
        ];
    }
}

if (!function_exists('get_category_color_style')) {
    function get_category_color_style(string $categoryName, ?string $customColor = null): array {
        if (!empty($customColor)) {
            return create_category_color_style($customColor);
        }

        $map = get_category_color_map();
        $key = strtolower(trim($categoryName));
        $key = strtr($key, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        
        if (isset($map[$key])) {
            return $map[$key];
        }

        // Dynamic fallback based on string hash for unrecognized categories
        $hash = abs(crc32($key));
        $hue = $hash % 360;
        return [
            'bg' => "hsla({$hue}, 80%, 60%, 0.18)",
            'border' => "hsl({$hue}, 80%, 55%)",
            'color' => "hsl({$hue}, 85%, 75%)",
            'dot' => "hsl({$hue}, 80%, 55%)"
        ];
    }
}
