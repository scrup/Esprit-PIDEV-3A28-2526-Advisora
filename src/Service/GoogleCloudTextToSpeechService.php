<?php

namespace App\Service;

use Afaya\EdgeTTS\Service\EdgeTTS;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class GoogleCloudTextToSpeechService
{
    public function __construct(
        #[Autowire('%env(string:EDGE_TTS_VOICE_NAME)%')]
        private readonly string $voiceName = 'fr-FR-DeniseNeural',
        #[Autowire('%env(string:EDGE_TTS_OUTPUT_FORMAT)%')]
        private readonly string $outputFormat = 'audio-24khz-48kbitrate-mono-mp3',
    ) {
    }

    public function synthesize(string $text): string
    {
        try {
            $edgeTTS = $this->createEdgeTTS();
            $edgeTTS->synthesize(trim($text), $this->voiceName, [
                'outputFormat' => $this->outputFormat,
            ]);
            $audio = $edgeTTS->toRaw();
        } catch (\Throwable $exception) {
            throw new \RuntimeException('La synthese vocale Edge TTS a echoue.', 0, $exception);
        }

        if ($audio === '') {
            throw new \RuntimeException('Edge TTS n a retourne aucun audio.');
        }

        return $audio;
    }

    protected function createEdgeTTS(): EdgeTTS
    {
        return new EdgeTTS();
    }
}
