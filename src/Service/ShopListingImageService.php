<?php

namespace App\Service;

use Cloudinary\Cloudinary;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ShopListingImageService
{
    private const MAX_UPLOAD_BYTES = 6_000_000;
    private const CLOUDINARY_FOLDER = 'shop/listings';
    private const LOCAL_PUBLIC_PREFIX = '/uploads/shop/listings/';

    /**
     * @var string[]
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /**
     * @var string[]
     */
    private const ALLOWED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'gif',
    ];

    public function __construct(
        #[Autowire(service: 'shop.listing.storage')]
        private readonly FilesystemOperator $shopListingStorage,
        private readonly SluggerInterface $slugger,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function storeUploadedImage(UploadedFile $uploadedFile, string $resourceName): string
    {
        if (!$uploadedFile->isValid()) {
            throw new \InvalidArgumentException('Upload image invalide.');
        }

        $fileSize = $uploadedFile->getSize();
        if (is_int($fileSize) && $fileSize > self::MAX_UPLOAD_BYTES) {
            throw new \InvalidArgumentException('Image trop volumineuse (max 6 Mo).');
        }

        $mimeType = strtolower((string) $uploadedFile->getMimeType());
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('Format image non supporte (JPG, PNG, WEBP, GIF).');
        }

        $extension = $this->normalizeExtension(
            strtolower((string) ($uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension()))
        );

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Extension image non supportee.');
        }

        $safeResourceName = strtolower((string) $this->slugger->slug(trim($resourceName)));
        if ($safeResourceName === '') {
            $safeResourceName = 'ressource';
        }

        $publicStem = sprintf(
            '%s-%s',
            $safeResourceName,
            bin2hex(random_bytes(8))
        );

        $cloudinaryUrl = $this->getCloudinaryUrl();
        if ($cloudinaryUrl !== null) {
            return $this->storeInCloudinary($uploadedFile, $cloudinaryUrl, $publicStem);
        }

        $filename = sprintf(
            '%s.%s',
            $publicStem,
            $extension === 'jpeg' ? 'jpg' : $extension
        );

        $stream = fopen($uploadedFile->getPathname(), 'rb');
        if (!is_resource($stream)) {
            throw new \RuntimeException('Impossible de lire le fichier image uploade.');
        }

        try {
            $this->shopListingStorage->writeStream($filename, $stream);
        } finally {
            fclose($stream);
        }

        return self::LOCAL_PUBLIC_PREFIX . $filename;
    }

    public function generateResourceFallbackPath(int $resourceId, string $resourceName): string
    {
        $slug = strtolower((string) $this->slugger->slug(trim($resourceName)));
        if ($slug === '') {
            $slug = 'ressource';
        }

        return $this->urlGenerator->generate(
            'app_shop_generated_resource_image',
            [
                'resourceId' => $resourceId,
                'slug' => $slug,
            ],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );
    }

    public function isLocalShopListingPath(?string $path): bool
    {
        return is_string($path) && str_starts_with($path, self::LOCAL_PUBLIC_PREFIX);
    }

    private function normalizeExtension(string $extension): string
    {
        return $extension === 'jpg' ? 'jpeg' : $extension;
    }

    private function getCloudinaryUrl(): ?string
    {
        $value = getenv('CLOUDINARY_URL');
        if (!is_string($value) || trim($value) === '') {
            $value = $_SERVER['CLOUDINARY_URL'] ?? $_ENV['CLOUDINARY_URL'] ?? '';
        }

        $resolved = trim((string) $value);

        return $resolved !== '' ? $resolved : null;
    }

    private function storeInCloudinary(UploadedFile $uploadedFile, string $cloudinaryUrl, string $publicStem): string
    {
        try {
            $cloudinary = new Cloudinary($cloudinaryUrl);
            $result = $cloudinary->uploadApi->upload($uploadedFile->getPathname(), [
                'folder' => self::CLOUDINARY_FOLDER,
                'public_id' => $publicStem,
                'overwrite' => false,
                'resource_type' => 'image',
                'unique_filename' => false,
                'use_filename' => false,
            ]);
        } catch (\Throwable $throwable) {
            throw new \RuntimeException('Upload Cloudinary impossible: ' . $throwable->getMessage(), 0, $throwable);
        }

        $secureUrl = trim((string) ($result['secure_url'] ?? $result['url'] ?? ''));
        if ($secureUrl === '') {
            throw new \RuntimeException('Cloudinary n a pas retourne d URL exploitable.');
        }

        return $secureUrl;
    }
}
