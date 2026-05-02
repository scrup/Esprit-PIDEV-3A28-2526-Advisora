<?php

// src/Service/LibreTranslateService.php

namespace App\Service;

use Jefs42\LibreTranslate;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class LibreTranslateService
{
    private ?LibreTranslate $translator = null;

    public function __construct(
        #[Autowire('%env(LIBRETRANSLATE_URL)%')] string $libreTranslateUrl
    ) {
        // Initialize the client. Fall back gracefully if the endpoint/client is unavailable.
        try {
            $this->translator = new LibreTranslate($libreTranslateUrl);
        } catch (\Throwable) {
            $this->translator = null;
        }
    }

    public function translate(string $text, string $targetLang, ?string $sourceLang = 'auto'): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if ($targetLang === '' || ($sourceLang !== null && $targetLang === $sourceLang)) {
            return $text;
        }

        if ($this->translator === null) {
            return $text;
        }

        try {
            return $this->translator->translate($text, $sourceLang, $targetLang);
        } catch (\Exception) {
            return $text;
        }
    }

    /**
     * @param list<string> $texts
     *
     * @return list<string>
     */
    public function translateBatch(array $texts, string $targetLang, ?string $sourceLang = 'auto'): array
    {
        if ($texts === [] || $targetLang === '' || ($sourceLang !== null && $targetLang === $sourceLang)) {
            return $texts;
        }

        $translated = $texts;
        $pending = [];

        foreach ($translated as $index => $text) {
            $trimmed = trim($text);

            if ($trimmed === '') {
                continue;
            }

            $pending[$index] = $trimmed;
        }

        if ($pending === []) {
            return $texts;
        }

        if ($this->translator === null) {
            return $texts;
        }

        try {
            $result = $this->translator->translate(array_values($pending), $sourceLang, $targetLang);

            if (!is_array($result)) {
                return $texts;
            }

            $pendingIndexes = array_keys($pending);

            foreach ($pendingIndexes as $position => $index) {
                $candidate = $result[$position] ?? null;

                if (is_string($candidate) && trim($candidate) !== '') {
                    $translated[$index] = trim($candidate);
                }
            }
        } catch (\Throwable) {
            return $texts;
        }

        /** @var list<string> $normalizedTranslated */
        $normalizedTranslated = array_values($translated);

        return $normalizedTranslated;
    }
}