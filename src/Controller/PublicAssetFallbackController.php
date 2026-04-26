<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Attribute\Route;

final class PublicAssetFallbackController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/css/{path}', name: 'app_public_asset_css', requirements: ['path' => '.+'], defaults: ['bucket' => 'css'], methods: ['GET'])]
    #[Route('/js/{path}', name: 'app_public_asset_js', requirements: ['path' => '.+'], defaults: ['bucket' => 'js'], methods: ['GET'])]
    #[Route('/assets/{path}', name: 'app_public_asset_assets', requirements: ['path' => '.+'], defaults: ['bucket' => 'assets'], methods: ['GET'])]
    public function serve(string $bucket, string $path): BinaryFileResponse
    {
        $baseDirectory = realpath($this->resolveBaseDirectory($bucket));
        $assetPath = $baseDirectory !== false ? realpath($baseDirectory . DIRECTORY_SEPARATOR . $path) : false;

        if ($baseDirectory === false || $assetPath === false || !str_starts_with($assetPath, $baseDirectory . DIRECTORY_SEPARATOR) || !is_file($assetPath)) {
            throw $this->createNotFoundException('Asset introuvable.');
        }

        $response = new BinaryFileResponse($assetPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($assetPath));

        $mimeType = MimeTypes::getDefault()->guessMimeType($assetPath);

        if ($mimeType !== null) {
            $response->headers->set('Content-Type', $mimeType);
        }

        return $response;
    }

    private function resolveBaseDirectory(string $bucket): string
    {
        return match ($bucket) {
            'css' => $this->projectDir . '/public/css',
            'js' => $this->projectDir . '/public/js',
            'assets' => $this->projectDir . '/public/assets',
            default => throw $this->createNotFoundException('Zone d\'asset non supportee.'),
        };
    }
}
