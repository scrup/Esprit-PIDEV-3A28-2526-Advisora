<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class FrenchSpellCorrector
{
    private const DEFAULT_ENDPOINT = 'https://api.languagetool.org/v2/check';
    private const DEFAULT_LANGUAGE = 'fr';

    private Client $client;

    public function __construct(
        private readonly string $endpoint = self::DEFAULT_ENDPOINT,
        private readonly string $defaultLanguage = self::DEFAULT_LANGUAGE
    ) {
        $this->client = new Client([
            'timeout' => 4.0,
            'connect_timeout' => 2.0,
        ]);
    }

    public function correctNullable(?string $text, ?string $language = null): ?string
    {
        if ($text === null) {
            return null;
        }

        return $this->correct($text, $language);
    }

    public function correct(string $text, ?string $language = null): string
    {
        $result = $this->correctWithStatus($text, $language);

        return (string) ($result['corrected'] ?? $text);
    }

    /**
     * @return array{status: string, corrected: string, changed: bool, error: ?string}
     */
    public function correctWithStatus(string $text, ?string $language = null): array
    {
        if (trim($text) === '') {
            return [
                'status' => 'ok',
                'corrected' => $text,
                'changed' => false,
                'error' => null,
            ];
        }

        $endpoint = trim($this->endpoint) !== '' ? trim($this->endpoint) : self::DEFAULT_ENDPOINT;
        $resolvedLanguage = trim((string) ($language ?? $this->defaultLanguage));
        if ($resolvedLanguage === '') {
            $resolvedLanguage = self::DEFAULT_LANGUAGE;
        }

        try {
            $response = $this->client->post($endpoint, [
                'http_errors' => false,
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'form_params' => [
                    'text' => $text,
                    'language' => $resolvedLanguage,
                    'enabledOnly' => 'false',
                ],
            ]);
        } catch (GuzzleException|\Throwable $exception) {
            return [
                'status' => 'unavailable',
                'corrected' => $text,
                'changed' => false,
                'error' => $exception->getMessage(),
            ];
        }

        if ($response->getStatusCode() !== 200) {
            return [
                'status' => 'unavailable',
                'corrected' => $text,
                'changed' => false,
                'error' => sprintf('LanguageTool HTTP status %d', $response->getStatusCode()),
            ];
        }

        $payload = json_decode((string) $response->getBody(), true);
        if (!is_array($payload)) {
            return [
                'status' => 'invalid_response',
                'corrected' => $text,
                'changed' => false,
                'error' => 'Invalid JSON response from LanguageTool.',
            ];
        }

        $matches = $payload['matches'] ?? [];
        if (!is_array($matches) || $matches === []) {
            return [
                'status' => 'ok',
                'corrected' => $text,
                'changed' => false,
                'error' => null,
            ];
        }

        $corrected = $this->applyMatches($text, $matches);

        return [
            'status' => 'ok',
            'corrected' => $corrected,
            'changed' => $corrected !== $text,
            'error' => null,
        ];
    }

    /**
     * @param array<int, mixed> $matches
     */
    private function applyMatches(string $originalText, array $matches): string
    {
        $textLength = $this->strLength($originalText);
        $patches = [];

        foreach ($matches as $match) {
            if (!is_array($match)) {
                continue;
            }

            $offset = isset($match['offset']) ? (int) $match['offset'] : -1;
            $length = isset($match['length']) ? (int) $match['length'] : 0;

            if ($offset < 0 || $length < 0 || $offset + $length > $textLength) {
                continue;
            }

            $replacements = $match['replacements'] ?? null;
            if (!is_array($replacements) || $replacements === []) {
                continue;
            }

            $replacement = null;
            foreach ($replacements as $candidate) {
                if (!is_array($candidate)) {
                    continue;
                }

                $value = $candidate['value'] ?? null;
                if (!is_string($value)) {
                    continue;
                }

                $trimmed = trim($value);
                if ($trimmed === '') {
                    continue;
                }

                $replacement = $trimmed;
                break;
            }

            if ($replacement === null) {
                continue;
            }

            $currentSegment = $this->strSubstr($originalText, $offset, $length);
            if ($currentSegment === $replacement) {
                continue;
            }

            $patches[] = [
                'offset' => $offset,
                'length' => $length,
                'replacement' => $replacement,
            ];
        }

        if ($patches === []) {
            return $originalText;
        }

        usort(
            $patches,
            static fn (array $left, array $right): int => $right['offset'] <=> $left['offset']
        );

        $corrected = $originalText;

        foreach ($patches as $patch) {
            $offset = (int) $patch['offset'];
            $length = (int) $patch['length'];
            $replacement = (string) $patch['replacement'];

            $before = $this->strSubstr($corrected, 0, $offset);
            $after = $this->strSubstr($corrected, $offset + $length);
            $corrected = $before . $replacement . $after;
        }

        return $corrected;
    }

    private function strLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }

    private function strSubstr(string $value, int $start, ?int $length = null): string
    {
        if (function_exists('mb_substr')) {
            $result = $length === null
                ? mb_substr($value, $start, null, 'UTF-8')
                : mb_substr($value, $start, $length, 'UTF-8');

            return $result === false ? '' : $result;
        }

        if ($length === null) {
            return substr($value, $start);
        }

        return substr($value, $start, $length);
    }
}
