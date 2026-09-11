<?php
declare(strict_types=1);

/**
 * General helpers: escaping, CSRF, tokens, redirects, flash, pagination.
 */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    if (!str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
        $path = rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
    }
    header('Location: ' . $path);
    exit;
}

function url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function detect_lan_ip(): ?string
{
    // Prefer a real private LAN address (not 169.254.link-local).
    $candidates = [];
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $out = [];
        @exec('ipconfig', $out);
        $block = '';
        foreach ($out as $line) {
            if (preg_match('/adapter/i', $line)) {
                $block = $line;
            }
            if (preg_match('/IPv4.*:\s*([0-9.]+)/', $line, $m)) {
                $candidates[] = ['ip' => $m[1], 'adapter' => $block];
            }
        }
    } else {
        $out = [];
        @exec('hostname -I 2>/dev/null', $out);
        if (!empty($out[0])) {
            foreach (preg_split('/\s+/', trim($out[0])) as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $candidates[] = ['ip' => $ip, 'adapter' => ''];
                }
            }
        }
    }

    $pick = null;
    foreach ($candidates as $c) {
        $ip = $c['ip'];
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_RES_RANGE)) {
            continue;
        }
        // Skip APIPA
        if (str_starts_with($ip, '169.254.')) {
            continue;
        }
        // Prefer Wi-Fi / Ethernet
        $adapter = strtolower($c['adapter']);
        $score = 1;
        if (str_contains($adapter, 'wi-fi') || str_contains($adapter, 'wireless') || str_contains($adapter, 'ethernet')) {
            $score = 3;
        }
        if (str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.') || preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $ip)) {
            $score += 2;
        }
        if ($pick === null || $score > $pick['score']) {
            $pick = ['ip' => $ip, 'score' => $score];
        }
    }

    if ($pick) {
        return $pick['ip'];
    }

    // Fallback: UDP socket trick
    try {
        $socket = @stream_socket_client('udp://8.8.8.8:53', $errno, $errstr, 1);
        if ($socket) {
            $local = stream_socket_get_name($socket, false);
            fclose($socket);
            if ($local && preg_match('/([0-9.]+):/', $local, $m) && !str_starts_with($m[1], '127.')) {
                return $m[1];
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
    return null;
}

/**
 * Base URL safe to share with visitors (not localhost).
 */
function public_base_url(): string
{
    $configured = trim((string) PUBLIC_BASE_URL);
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $base = rtrim(BASE_URL, '/');
    $parts = parse_url($base) ?: [];
    $host = $parts['host'] ?? 'localhost';
    $scheme = $parts['scheme'] ?? 'http';
    $path = $parts['path'] ?? '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';

    // Ensure app path exists (e.g. /visitor_management) even if BASE_URL has no path
    if ($path === '' || $path === '/') {
        $doc = str_replace('\\', '/', (string) BASE_PATH);
        $docRoot = trim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $web = $docRoot !== ''
            ? str_replace('\\', '/', realpath($docRoot) ?: $docRoot)
            : 'C:/xampp/htdocs';
        $web = rtrim($web, '/');
        if ($web !== '' && str_starts_with($doc, $web) && strlen($doc) > strlen($web)) {
            $path = substr($doc, strlen($web));
        }
        if ($path === '' || $path === '/') {
            if (preg_match('#/(visitor_management|ResidenceManagement)$#i', $doc, $m)) {
                $path = $m[0];
            } elseif (preg_match('#/(visitor_management|ResidenceManagement)/#i', $doc, $m)) {
                $path = $m[0];
                $path = rtrim($path, '/');
            }
        }
        $path = rtrim(str_replace('\\', '/', (string) $path), '/');
    }

    // Already a shareable host/IP
    if ($host !== 'localhost' && $host !== '127.0.0.1') {
        return $scheme . '://' . $host . $port . $path;
    }

    $lan = detect_lan_ip();
    if (!$lan) {
        return $scheme . '://' . $host . $port . $path;
    }

    return $scheme . '://' . $lan . $port . $path;
}

function public_url(string $path = ''): string
{
    return rtrim(public_base_url(), '/') . '/' . ltrim($path, '/');
}


function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function input(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function post(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $default;
}

function get(string $key, mixed $default = null): mixed
{
    return $_GET[$key] ?? $default;
}

function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrf_field(): string
{
    return '<input type="hidden" name="' . e(CSRF_TOKEN_NAME) . '" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token = null): bool
{
    $token = $token ?? ($_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $session = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    return is_string($token) && is_string($session) && $session !== '' && hash_equals($session, $token);
}

function require_csrf(): void
{
    if (!verify_csrf()) {
        http_response_code(403);
        flash('error', 'Invalid security token. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? url('index.php'));
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

function generate_token(int $bytes = QR_TOKEN_BYTES): string
{
    return bin2hex(random_bytes($bytes));
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function format_datetime(?string $dt, string $format = 'd M Y H:i'): string
{
    if ($dt === null || $dt === '') {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date($format, $ts) : '—';
}

function format_date(?string $d, string $format = 'd M Y'): string
{
    return format_datetime($d, $format);
}

function int_id(mixed $value): int
{
    return max(0, (int) $value);
}

function paginate(int $total, int $page, int $perPage = ITEMS_PER_PAGE): array
{
    $perPage = max(1, $perPage);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;
    return [
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'pages'    => $pages,
        'offset'   => $offset,
    ];
}

function status_badge(string $status): string
{
    $map = [
        'active'       => 'success',
        'pending'      => 'warning',
        'approved'     => 'info',
        'checked_in'   => 'primary',
        'checked_out'  => 'secondary',
        'expired'      => 'dark',
        'rejected'     => 'danger',
        'suspended'    => 'danger',
        'inactive'     => 'secondary',
        'moved_out'    => 'secondary',
    ];
    $class = $map[$status] ?? 'secondary';
    $label = ucwords(str_replace('_', ' ', $status));
    return '<span class="badge text-bg-' . e($class) . '">' . e($label) . '</span>';
}

function normalize_phone(?string $phone): string
{
    $phone = preg_replace('/\s+/', '', (string) $phone) ?? '';
    return $phone;
}

function normalize_plate(?string $plate): string
{
    $plate = strtoupper(preg_replace('/\s+/', '', (string) $plate) ?? '');
    return $plate;
}

function validate_email(?string $email): bool
{
    if ($email === null || $email === '') {
        return true; // optional
    }
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function old(string $key, mixed $default = ''): mixed
{
    $old = $_SESSION['_old'][$key] ?? $default;
    return $old;
}

function store_old(array $data): void
{
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}
