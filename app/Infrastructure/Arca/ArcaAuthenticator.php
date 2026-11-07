<?php

declare(strict_types=1);

namespace App\Infrastructure\Arca;

final class ArcaAuthenticator
{
    public function __construct(
        private readonly WsaaClient $client,
        private readonly string $storagePath,
        private readonly string $environment,
        private readonly string $certPath,
        private readonly string $keyPath,
    ) {
    }

    public function ticket(): array
    {
        $cached = $this->readCache();
        if ($cached !== null) {
            return $cached;
        }

        $ticket = $this->client->login('wsfe', $this->certPath, $this->keyPath);
        $this->writeCache($ticket);

        return $ticket;
    }

    private function readCache(): ?array
    {
        $file = $this->cacheFile();
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || !isset($data['token'], $data['sign'], $data['expires_at'])) {
            return null;
        }
        if (!array_key_exists('represented_cuits', $data)) {
            return null;
        }
        if ((int) $data['expires_at'] <= time() + 300) {
            return null;
        }

        return $data;
    }

    private function writeCache(array $ticket): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0775, true);
        }
        file_put_contents($this->cacheFile(), json_encode($ticket, JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private function cacheFile(): string
    {
        return rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . 'ta_' . $this->environment . '.json';
    }
}
