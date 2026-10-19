<?php

declare(strict_types=1);

namespace Core\System;

use RuntimeException;

final class GuavasRegistration
{
    private const DEFAULT_API = 'https://meegbe.id/api/guavas/v1';
    private string $directory;
    private string $apiBase;

    /** @param null|callable(string, string, ?array, ?string): array{status:int, body:array} $requester */
    public function __construct(string $root, ?string $apiBase = null, private $requester = null)
    {
        $this->directory = rtrim($root, '/\\') . '/storage/guavas-updates';
        $this->apiBase = rtrim($apiBase ?? (getenv('GUAVAS_API_BASE_URL') ?: self::DEFAULT_API), '/');
    }

    private function assertApiUrl(): void
    {
        $parts = parse_url($this->apiBase);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('GUAVAS_API_BASE_URL harus berupa URL HTTPS tanpa kredensial, query, atau fragmen.');
        }
    }

    public function installationId(): string
    {
        $path = $this->directory . '/installation.json';
        if (is_file($path)) {
            $data = $this->readJson($path);
            $id = $data['installation_id'] ?? null;
            if (!is_string($id) || !self::isUuid($id)) {
                throw new RuntimeException('ID instalasi lokal tidak valid. Periksa storage/guavas-updates/installation.json.');
            }
            return $id;
        }

        $this->ensureDirectory();
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        $id = sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            if (is_file($path)) {
                return $this->installationId();
            }
            throw new RuntimeException('Gagal membuat ID instalasi lokal.');
        }
        try {
            if (fwrite($handle, json_encode(['installation_id' => $id], JSON_THROW_ON_ERROR) . "\n") === false) {
                throw new RuntimeException('Gagal menyimpan ID instalasi lokal.');
            }
        } finally {
            fclose($handle);
        }
        @chmod($path, 0600);
        return $id;
    }

    public function status(): array
    {
        $id = $this->installationId();
        $registration = $this->readRegistration();
        return [
            'installation_id' => $id,
            'registered' => $registration !== null,
            'customer' => $registration['customer'] ?? null,
            'registered_at' => $registration['registered_at'] ?? null,
        ];
    }

    public function register(string $key, ?string $label, string $version): array
    {
        $key = strtoupper(str_replace('-', '', trim($key)));
        if (!preg_match('/^[A-Z2-7]{25}$/D', $key)) {
            throw new RuntimeException('Format kunci customer tidak valid.');
        }
        $id = $this->installationId();
        $this->ensureWritable();
        $body = ['license_key' => $key, 'installation_id' => $id, 'framework_version' => $version];
        if ($label !== null && $label !== '') {
            $body['project_label'] = $label;
        }
        $response = $this->request('POST', '/registrations', $body);
        $data = $response['data'] ?? null;
        if (!is_array($data) || ($data['installation_id'] ?? null) !== $id) {
            throw new RuntimeException('Respons registrasi memiliki ID instalasi yang tidak sesuai.');
        }
        $this->saveRegistration($data);
        return $this->status();
    }

    public function refresh(): void
    {
        $registration = $this->requireRegistration();
        $this->ensureWritable();
        $response = $this->request('POST', '/registrations/refresh', [], $registration['token']);
        $data = $response['data'] ?? null;
        if (!is_array($data)) {
            throw new RuntimeException('Respons pembaruan token tidak valid.');
        }
        $this->saveRegistration(array_merge($registration, $data));
    }

    public function revoke(): void
    {
        $registration = $this->requireRegistration();
        $this->request('POST', '/registrations/revoke', [], $registration['token']);
        if (!@unlink($this->directory . '/registration.json')) {
            throw new RuntimeException('Registrasi dicabut di server, tetapi token lokal gagal dihapus. Hapus storage/guavas-updates/registration.json secara manual.');
        }
    }

    private function requireRegistration(): array
    {
        $registration = $this->readRegistration();
        if ($registration === null) {
            throw new RuntimeException('Instalasi belum terdaftar. Jalankan php guavas-cmd system register.');
        }
        return $registration;
    }

    private function readRegistration(): ?array
    {
        $path = $this->directory . '/registration.json';
        if (!is_file($path)) {
            return null;
        }
        $data = $this->readJson($path);
        if (($data['installation_id'] ?? null) !== $this->installationId() || !self::validToken($data['token'] ?? null)) {
            throw new RuntimeException('Data registrasi lokal tidak valid.');
        }
        return $data;
    }

    private function saveRegistration(array $data): void
    {
        if (($data['installation_id'] ?? null) !== $this->installationId() || !self::validToken($data['token'] ?? null)) {
            throw new RuntimeException('Token atau ID instalasi pada respons API tidak valid.');
        }
        $customer = $data['customer'] ?? null;
        if ($customer !== null && !is_array($customer)) {
            throw new RuntimeException('Identitas customer pada respons API tidak valid.');
        }
        $this->ensureDirectory();
        $path = $this->directory . '/registration.json';
        $temp = tempnam($this->directory, '.registration-');
        if ($temp === false) {
            throw new RuntimeException('Gagal menyiapkan penyimpanan token.');
        }
        try {
            @chmod($temp, 0600);
            $payload = json_encode([
                'installation_id' => $data['installation_id'],
                'token' => $data['token'],
                'customer' => $customer,
                'registered_at' => $data['registered_at'] ?? gmdate('c'),
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            if (file_put_contents($temp, $payload . "\n", LOCK_EX) === false || !@rename($temp, $path)) {
                throw new RuntimeException('Gagal menyimpan token instalasi.');
            }
            @chmod($path, 0600);
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    private function request(string $method, string $path, ?array $body, ?string $token = null): array
    {
        $this->assertApiUrl();
        $url = $this->apiBase . $path;
        if ($this->requester !== null) {
            $result = ($this->requester)($method, $url, $body, $token);
        } else {
            $headers = ['Accept: application/json', 'Content-Type: application/json'];
            if ($token !== null) {
                $headers[] = 'Authorization: Bearer ' . $token;
            }
            $payload = json_encode($body ?? [], JSON_THROW_ON_ERROR);
            $context = stream_context_create(['http' => [
                'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $payload,
                'timeout' => 10, 'ignore_errors' => true, 'follow_location' => 0,
            ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
            $raw = @file_get_contents($url, false, $context);
            if ($raw === false) {
                throw new RuntimeException('API MeeGbe tidak dapat dihubungi. Periksa koneksi dan sertifikat HTTPS.');
            }
            $statusLine = $http_response_header[0] ?? '';
            $status = preg_match('/^HTTP\/\S+\s+(\d{3})/', $statusLine, $match) ? (int) $match[1] : 0;
            $decoded = json_decode($raw, true);
            $result = ['status' => $status, 'body' => is_array($decoded) ? $decoded : []];
        }
        $status = $result['status'] ?? 0;
        $response = $result['body'] ?? [];
        if (!is_int($status) || !is_array($response)) {
            throw new RuntimeException('Respons API MeeGbe tidak valid.');
        }
        if ($status < 200 || $status >= 300 || ($response['status'] ?? '') !== 'success') {
            $code = $response['code'] ?? 'api_unavailable';
            if (!is_string($code) || !preg_match('/^[a-z0-9_]{1,64}$/', $code)) {
                $code = 'api_unavailable';
            }
            throw new RuntimeException("API MeeGbe menolak permintaan (HTTP {$status}, {$code}).");
        }
        return $response;
    }

    private function readJson(string $path): array
    {
        $data = json_decode((string) @file_get_contents($path), true);
        if (!is_array($data)) {
            throw new RuntimeException('Berkas registrasi lokal rusak atau tidak dapat dibaca.');
        }
        return $data;
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Gagal membuat folder registrasi lokal.');
        }
        @chmod($this->directory, 0700);
    }

    private function ensureWritable(): void
    {
        $this->ensureDirectory();
        $probe = tempnam($this->directory, '.write-test-');
        if ($probe === false) throw new RuntimeException('Folder registrasi lokal tidak dapat ditulis.');
        @unlink($probe);
    }

    private static function isUuid(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $id);
    }

    private static function validToken(mixed $token): bool
    {
        return is_string($token) && (bool) preg_match('/^[A-Za-z0-9_-]{43}$/D', $token);
    }
}
