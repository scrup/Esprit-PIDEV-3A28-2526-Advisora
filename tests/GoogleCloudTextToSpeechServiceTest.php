<?php

namespace App\Tests;

use Afaya\EdgeTTS\Service\EdgeTTS;
use App\Service\GoogleCloudTextToSpeechService;
use PHPUnit\Framework\TestCase;

class GoogleCloudTextToSpeechServiceTest extends TestCase
{
    public function testSynthesizeUsesEdgeTtsAndReturnsRawAudio(): void
    {
        $edgeTTS = $this->createMock(EdgeTTS::class);
        $edgeTTS->expects(self::once())
            ->method('synthesize')
            ->with('Bonjour Advisora', 'fr-FR-DeniseNeural', [
                'outputFormat' => 'audio-24khz-48kbitrate-mono-mp3',
            ]);
        $edgeTTS->expects(self::once())
            ->method('toRaw')
            ->willReturn('mp3-binary-payload');

        $service = new class($edgeTTS, 'fr-FR-DeniseNeural', 'audio-24khz-48kbitrate-mono-mp3') extends GoogleCloudTextToSpeechService {
            public function __construct(
                private readonly EdgeTTS $edgeTTS,
                string $voiceName,
                string $outputFormat,
            ) {
                parent::__construct($voiceName, $outputFormat);
            }

            protected function createEdgeTTS(): EdgeTTS
            {
                return $this->edgeTTS;
            }
        };

        self::assertSame('mp3-binary-payload', $service->synthesize('Bonjour Advisora'));
    }

    public function testSynthesizeThrowsWhenAudioContentIsMissing(): void
    {
        $edgeTTS = $this->createMock(EdgeTTS::class);
        $edgeTTS->expects(self::once())
            ->method('synthesize');
        $edgeTTS->expects(self::once())
            ->method('toRaw')
            ->willReturn('');

        $service = new class($edgeTTS, 'fr-FR-DeniseNeural', 'audio-24khz-48kbitrate-mono-mp3') extends GoogleCloudTextToSpeechService {
            public function __construct(
                private readonly EdgeTTS $edgeTTS,
                string $voiceName,
                string $outputFormat,
            ) {
                parent::__construct($voiceName, $outputFormat);
            }

            protected function createEdgeTTS(): EdgeTTS
            {
                return $this->edgeTTS;
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Edge TTS n a retourne aucun audio');

        $service->synthesize('Bonjour Advisora');
    }
}
