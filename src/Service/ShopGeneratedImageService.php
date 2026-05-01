<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ShopGeneratedImageService
{
    private const SOURCE_API = 'api';
    private const SOURCE_SVG = 'svg';

    /**
     * @var array<string, string>
     */
    private const EXTENSIONS_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%shop_generated_images_directory%')]
        private readonly string $generatedImagesDirectory,
        #[Autowire('%shop_generated_images_public_prefix%')]
        private readonly string $generatedImagesPublicPrefix,
    ) {
    }

    public function generateResourceImagePath(int $resourceId, string $resourceName): string
    {
        return $this->urlGenerator->generate(
            'app_shop_generated_resource_image',
            [
                'resourceId' => $resourceId,
                'slug' => $this->buildSlug($resourceId, $resourceName),
            ],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );
    }

    public function generateLegacySvgPath(int $resourceId, string $resourceName): string
    {
        return $this->urlGenerator->generate(
            'app_shop_generated_resource_image_svg',
            [
                'resourceId' => $resourceId,
                'slug' => $this->buildSlug($resourceId, $resourceName),
            ],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );
    }

    /**
     * @return array{source:string,path:string,content_type:string}
     */
    public function resolveGeneratedImage(int $resourceId, string $resourceName): array
    {
        $cached = $this->findCachedImage($resourceId, $resourceName);
        if ($cached !== null) {
            return [
                'source' => self::SOURCE_API,
                'path' => $cached['absolute_path'],
                'content_type' => $cached['content_type'],
            ];
        }

        $downloaded = $this->downloadGeneratedImage($resourceId, $resourceName);
        if ($downloaded !== null) {
            return [
                'source' => self::SOURCE_API,
                'path' => $downloaded['absolute_path'],
                'content_type' => $downloaded['content_type'],
            ];
        }

        return [
            'source' => self::SOURCE_SVG,
            'path' => '',
            'content_type' => 'image/svg+xml; charset=UTF-8',
        ];
    }

    public function buildPublicCachePath(int $resourceId, string $resourceName, string $extension): string
    {
        return rtrim($this->generatedImagesPublicPrefix, '/') . '/' . $this->buildBaseFilename($resourceId, $resourceName) . '.' . $extension;
    }

    /**
     * @return array{absolute_path:string,content_type:string}|null
     */
    private function findCachedImage(int $resourceId, string $resourceName): ?array
    {
        $basePath = $this->buildAbsoluteBasePath($resourceId, $resourceName);
        foreach (self::EXTENSIONS_BY_MIME as $contentType => $extension) {
            $candidate = $basePath . '.' . $extension;
            if (is_file($candidate)) {
                return [
                    'absolute_path' => $candidate,
                    'content_type' => $contentType,
                ];
            }
        }

        return null;
    }

    /**
     * @return array{absolute_path:string,content_type:string}|null
     */
    private function downloadGeneratedImage(int $resourceId, string $resourceName): ?array
    {
        $prompt = $this->buildPrompt($resourceName);
        $seed = $this->buildSeed($resourceId, $resourceName);
        $content = null;
        $contentType = null;

        if ($this->hasPollinationsApiKey()) {
            [$content, $contentType] = $this->requestPollinationsUnified($prompt, $seed);
        } else {
            [$content, $contentType] = $this->requestPollinationsLegacy($prompt, $seed);
        }

        if (!is_string($content) || $content === '' || !is_string($contentType)) {
            return null;
        }

        $normalizedContentType = strtolower(trim(explode(';', $contentType)[0]));
        $extension = self::EXTENSIONS_BY_MIME[$normalizedContentType] ?? null;
        if ($extension === null) {
            return null;
        }

        $absolutePath = $this->buildAbsoluteBasePath($resourceId, $resourceName) . '.' . $extension;
        $directory = dirname($absolutePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return null;
        }

        if (@file_put_contents($absolutePath, $content) === false) {
            return null;
        }

        return [
            'absolute_path' => $absolutePath,
            'content_type' => $normalizedContentType,
        ];
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function requestPollinationsUnified(string $prompt, string $seed): array
    {
        $size = $this->normalizeSize();
        $url = '' . rawurlencode($prompt)
            . '?size=' . rawurlencode($size)
            . '&seed=' . rawurlencode($seed)
            . '&model=' . rawurlencode($this->getGeneratorModel());

        return $this->executeImageRequest($url, [
            'Authorization: Bearer ' . $this->getPollinationsApiKey(),
            'Accept: image/jpeg,image/png,image/webp',
        ]);
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function requestPollinationsLegacy(string $prompt, string $seed): array
    {
        [$width, $height] = $this->normalizeDimensions();
        $url = '' . rawurlencode($prompt)
            . '?width=' . $width
            . '&height=' . $height
            . '&seed=' . rawurlencode($seed);

        if ($this->getGeneratorModel() !== '') {
            $url .= '&model=' . rawurlencode($this->getGeneratorModel());
        }

        return $this->executeImageRequest($url, [
            'Accept: image/jpeg,image/png,image/webp',
        ]);
    }

    /**
     * @param string[] $headers
     *
     * @return array{0:?string,1:?string}
     */
    private function executeImageRequest(string $url, array $headers): array
    {
        if (!function_exists('curl_init')) {
            return [null, null];
        }

        $curl = curl_init($url);
        if ($curl === false) {
            return [null, null];
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $body = curl_exec($curl);
        if ($body === false) {
            curl_close($curl);

            return [null, null];
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        curl_close($curl);

        if ($statusCode < 200 || $statusCode >= 300) {
            return [null, null];
        }

        return [(string) $body, $contentType];
    }

    private function buildPrompt(string $resourceName): string
    {
        $cleanName = trim($resourceName) !== '' ? trim($resourceName) : 'resource';

        return sprintf(
            'Professional catalog photo of %s, realistic product or place shot, centered subject, clean composition, premium lighting, subtle background, no text, no watermark, no logo',
            $cleanName
        );
    }

    private function buildSeed(int $resourceId, string $resourceName): string
    {
        return 'shop-resource-' . $resourceId . '-' . $this->buildSlug($resourceId, $resourceName);
    }

    private function buildSlug(int $resourceId, string $resourceName): string
    {
        $slug = strtolower((string) $this->slugger->slug(trim($resourceName)));

        return $slug !== '' ? $slug : 'resource-' . $resourceId;
    }

    private function buildBaseFilename(int $resourceId, string $resourceName): string
    {
        return 'resource-' . $resourceId . '-' . $this->buildSlug($resourceId, $resourceName);
    }

    private function buildAbsoluteBasePath(int $resourceId, string $resourceName): string
    {
        return rtrim($this->generatedImagesDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->buildBaseFilename($resourceId, $resourceName);
    }

    private function hasPollinationsApiKey(): bool
    {
        return $this->getPollinationsApiKey() !== '';
    }

    private function normalizeSize(): string
    {
        $size = trim($this->getEnvValue('SHOP_IMAGE_GENERATOR_SIZE', '1536x1024'));

        return preg_match('/^\d+x\d+$/', $size) === 1 ? $size : '1536x1024';
    }

    /**
     * @return array{0:int,1:int}
     */
    private function normalizeDimensions(): array
    {
        if (preg_match('/^(?<width>\d+)x(?<height>\d+)$/', $this->normalizeSize(), $matches) === 1) {
            return [(int) $matches['width'], (int) $matches['height']];
        }

        return [1536, 1024];
    }

    private function getPollinationsApiKey(): string
    {
        return $this->getEnvValue('POLLINATIONS_API_KEY');
    }

    private function getGeneratorModel(): string
    {
        return $this->getEnvValue('SHOP_IMAGE_GENERATOR_MODEL', 'zimage');
    }

    private function getEnvValue(string $name, string $default = ''): string
    {
        $value = getenv($name);
        if (!is_string($value) || $value === '') {
            $value = $_SERVER[$name] ?? $_ENV[$name] ?? $default;
        }

        return trim((string) $value);
    }
}
