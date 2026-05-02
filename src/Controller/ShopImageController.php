<?php

namespace App\Controller;

use App\Service\ShopGeneratedImageService;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShopImageController extends AbstractController
{
    #[Route(
        '/boutique/media/resource/{resourceId}/{slug}',
        name: 'app_shop_generated_resource_image',
        methods: ['GET'],
        requirements: ['resourceId' => '\d+', 'slug' => '[^.]+']
    )]
    public function generatedResourceImage(
        int $resourceId,
        string $slug,
        Connection $connection,
        ShopGeneratedImageService $generatedImageService
    ): Response
    {
        $resourceName = $this->resolveResourceName($connection, $resourceId, $slug);
        $generated = $generatedImageService->resolveGeneratedImage($resourceId, $resourceName);

        if ($generated['source'] === 'api' && $generated['path'] !== '') {
            $response = new BinaryFileResponse($generated['path']);
            $response->setPublic();
            $response->setMaxAge(604800);
            $response->headers->set('Content-Type', $generated['content_type']);

            return $response;
        }

        return $this->buildSvgFallbackResponse($resourceName);
    }

    #[Route(
        '/boutique/media/resource/{resourceId}/{slug}.svg',
        name: 'app_shop_generated_resource_image_svg',
        methods: ['GET'],
        requirements: ['resourceId' => '\d+', 'slug' => '[^.]+']
    )]
    public function generatedResourceImageSvg(
        int $resourceId,
        string $slug,
        ShopGeneratedImageService $generatedImageService
    ): RedirectResponse
    {
        return new RedirectResponse(
            $generatedImageService->generateResourceImagePath($resourceId, str_replace('-', ' ', $slug)),
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }

    private function buildSvgFallbackResponse(string $resourceName): Response
    {
        $resourceName = trim($resourceName) !== '' ? trim($resourceName) : 'Ressource';

        $lines = $this->wrapLabel($resourceName, 22, 3);
        $escapedLines = array_map(
            static fn (string $line): string => htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $lines
        );

        $lineYs = [490, 570, 650];
        $svgText = '';
        foreach ($escapedLines as $index => $line) {
            $y = $lineYs[$index] ?? (430 + ($index * 70));
            $svgText .= sprintf(
                '<text x="110" y="%d" font-family="Segoe UI, Arial, sans-serif" font-size="68" font-weight="700" fill="#f8eee5">%s</text>',
                $y,
                $line
            );
        }

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1536" height="1024" viewBox="0 0 1536 1024">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#6f4a2f"/>
      <stop offset="45%" stop-color="#231a16"/>
      <stop offset="100%" stop-color="#0e0b0a"/>
    </linearGradient>
    <linearGradient id="panel" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#16110e" stop-opacity="0.88"/>
      <stop offset="100%" stop-color="#090807" stop-opacity="0.72"/>
    </linearGradient>
    <linearGradient id="shine" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0%" stop-color="#ca8d61" stop-opacity="0.96"/>
      <stop offset="100%" stop-color="#7f5438" stop-opacity="0.45"/>
    </linearGradient>
  </defs>
  <rect width="1536" height="1024" rx="44" fill="url(#bg)"/>
  <rect x="0" y="0" width="1536" height="218" fill="url(#shine)" opacity="0.60"/>
  <circle cx="1430" cy="120" r="112" fill="#ffffff" opacity="0.08"/>
  <rect x="90" y="300" width="1010" height="470" rx="42" fill="url(#panel)"/>
  <text x="110" y="400" font-family="Segoe UI, Arial, sans-serif" font-size="42" font-weight="600" fill="#f0ddcf">Image automatique - publication CLIENT</text>
  {$svgText}
</svg>
SVG;

        return new Response($svg, Response::HTTP_OK, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function resolveResourceName(Connection $connection, int $resourceId, string $slug): string
    {
        $resourceName = (string) $connection->fetchOne(
            'SELECT nomRs FROM resource WHERE idRs = ? LIMIT 1',
            [$resourceId]
        );

        if (trim($resourceName) === '') {
            $resourceName = str_replace('-', ' ', $slug);
        }

        return trim($resourceName) !== '' ? trim($resourceName) : 'Ressource';
    }

    /**
     * @return string[]
     */
    private function wrapLabel(string $text, int $lineLength, int $maxLines): array
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($clean === '') {
            return ['Ressource'];
        }

        $words = explode(' ', $clean);
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($candidate) <= $lineLength || $current === '') {
                $current = $candidate;
                continue;
            }

            $lines[] = $current;
            $current = $word;

            if (count($lines) >= $maxLines - 1) {
                break;
            }
        }

        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = $current;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
        }

        $lastIndex = count($lines) - 1;
        if ($lastIndex >= 0 && mb_strlen($lines[$lastIndex]) > $lineLength) {
            $lines[$lastIndex] = mb_substr($lines[$lastIndex], 0, max(1, $lineLength - 1)) . '…';
        }

        return $lines;
    }
}
