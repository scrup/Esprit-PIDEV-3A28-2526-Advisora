<?php
// src/Service/LibreTranslateService.php
namespace App\Service;

use Jefs42\LibreTranslate;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class LibreTranslateService
{
    private LibreTranslate $translator;

    public function __construct(
        #[Autowire('%env(LIBRETRANSLATE_URL)%')] string $libreTranslateUrl
    ) {
        // Initialize the client. It defaults to http://localhost:5000
        $this->translator = new LibreTranslate($libreTranslateUrl);
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

        try {
            return $this->translator->translate($text, $sourceLang, $targetLang);
        } catch (\Exception $e) {
            // Log error and return original text as fallback
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
            return array_values($texts);
        }

        $translated = array_values($texts);
        $pending = [];

        foreach ($translated as $index => $text) {
            $trimmed = trim((string) $text);

            if ($trimmed === '') {
                continue;
            }

            $pending[$index] = $trimmed;
        }

        if ($pending === []) {
            return $translated;
        }

        try {
            $result = $this->translator->translate(array_values($pending), $sourceLang, $targetLang);

            if (!is_array($result)) {
                return $translated;
            }

            $pendingIndexes = array_keys($pending);
            foreach ($pendingIndexes as $position => $index) {
                $candidate = $result[$position] ?? null;

                if (is_string($candidate) && trim($candidate) !== '') {
                    $translated[$index] = trim($candidate);
                }
            }
        } catch (\Throwable) {
            return $translated;
        }

        return $translated;
    }
}
