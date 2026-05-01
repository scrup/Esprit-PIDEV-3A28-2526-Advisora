<?php

namespace App\Service;

use App\Entity\Booking;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class BookingStatusStore
{
    /**
     * @var string[]
     */
    private const ALLOWED_STATUSES = [
        Booking::STATUS_PENDING,
        Booking::STATUS_ACCEPTED,
        Booking::STATUS_REFUSED,
    ];

    private readonly string $storagePath;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
    ) {
        $this->storagePath = $projectDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'event-booking-statuses.json';
    }

    public function getStatusForBooking(int $bookingId): string
    {
        if ($bookingId <= 0) {
            return Booking::STATUS_PENDING;
        }

        $data = $this->readAll();
        $entry = $data[(string) $bookingId] ?? null;
        $status = is_array($entry) ? (string) ($entry['status'] ?? '') : '';

        return in_array($status, self::ALLOWED_STATUSES, true) ? $status : Booking::STATUS_PENDING;
    }

    public function initializePending(int $bookingId, ?int $userId): void
    {
        if ($bookingId <= 0) {
            return;
        }

        $this->writeStatus($bookingId, Booking::STATUS_PENDING, $userId, false);
    }

    public function markAccepted(int $bookingId, ?int $managerId): void
    {
        $this->writeStatus($bookingId, Booking::STATUS_ACCEPTED, $managerId);
    }

    public function markRefused(int $bookingId, ?int $managerId): void
    {
        $this->writeStatus($bookingId, Booking::STATUS_REFUSED, $managerId);
    }

    public function remove(int $bookingId): void
    {
        if ($bookingId <= 0) {
            return;
        }

        $this->mutateStore(function (array $data) use ($bookingId): array {
            unset($data[(string) $bookingId]);

            return $data;
        });
    }

    /**
     * @param iterable<Booking> $bookings
     */
    public function hydrateStatuses(iterable $bookings): void
    {
        $data = $this->readAll();

        foreach ($bookings as $booking) {
            if ($booking->getId() === null) {
                continue;
            }

            $entry = $data[(string) $booking->getId()] ?? null;
            $status = is_array($entry) ? (string) ($entry['status'] ?? '') : Booking::STATUS_PENDING;
            $booking->setWorkflowStatus($status);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readAll(): array
    {
        $this->ensureStorageFile();

        $handle = @fopen($this->storagePath, 'rb');
        if ($handle === false) {
            return [];
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                return [];
            }

            $raw = stream_get_contents($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function writeStatus(int $bookingId, string $status, ?int $updatedBy, bool $overwrite = true): void
    {
        if ($bookingId <= 0 || !in_array($status, self::ALLOWED_STATUSES, true)) {
            return;
        }

        $this->mutateStore(function (array $data) use ($bookingId, $status, $updatedBy, $overwrite): array {
            $key = (string) $bookingId;

            if (!$overwrite && array_key_exists($key, $data)) {
                return $data;
            }

            $data[$key] = [
                'status' => $status,
                'updated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'updated_by' => $updatedBy,
            ];

            return $data;
        });
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $callback
     */
    private function mutateStore(callable $callback): void
    {
        $this->ensureStorageFile();

        $handle = @fopen($this->storagePath, 'c+');
        if ($handle === false) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }

            $raw = stream_get_contents($handle);
            $decoded = [];

            if (is_string($raw) && trim($raw) !== '') {
                try {
                    $candidate = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                    $decoded = is_array($candidate) ? $candidate : [];
                } catch (\JsonException) {
                    $decoded = [];
                }
            }

            $updated = $callback($decoded);
            $json = json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                $json = '{}';
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $json);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    private function ensureStorageFile(): void
    {
        $directory = dirname($this->storagePath);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        if (!is_file($this->storagePath)) {
            @file_put_contents($this->storagePath, "{}\n");
        }
    }
}
