<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Translation;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function count;
use function is_array;
use function is_string;
use function sprintf;

/**
 * LibreTranslate (`/translate`, HTML format) — self-hosted, no account: `docker run -p 5000:5000 libretranslate/libretranslate`.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class LibreTranslateTranslator implements MachineTranslatorInterface
{
    private LoggerInterface $logger;

    /**
     * @param array<string, string> $languages locale => LibreTranslate language code
     * @param float $timeout idle timeout of the request, in seconds (HttpClient `timeout`)
     * @param float $maxDuration hard deadline of the request, in seconds (HttpClient `max_duration`)
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $url,
        private string $apiKey = '',
        private array $languages = [],
        private float $timeout = 30.0,
        private float $maxDuration = 120.0,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritDoc}
     */
    public function translate(array $texts, string $sourceLocale, string $targetLocale): array
    {
        if ($texts === []) {
            return [];
        }
        $body = [
            'q'      => $texts,
            'source' => $this->languages[$sourceLocale] ?? $sourceLocale,
            'target' => $this->languages[$targetLocale] ?? $targetLocale,
            'format' => 'html',
        ];
        if ($this->apiKey !== '') {
            $body['api_key'] = $this->apiKey;
        }

        // The log context never carries the key (sent in the body) nor the texts.
        $context = ['bundle' => 'manual_kit', 'provider' => 'libretranslate', 'target_locale' => $targetLocale, 'texts' => count($texts)];
        $this->logger->info('Manual machine translation request started.', $context);
        try {
            $data = $this->httpClient->request('POST', rtrim($this->url, '/') . '/translate', [
                'json'         => $body,
                'timeout'      => $this->timeout,
                'max_duration' => $this->maxDuration,
            ])->toArray();
            $translations = [];
            foreach (is_array($data['translatedText'] ?? null) ? $data['translatedText'] : [] as $text) {
                if (!is_string($text)) {
                    throw new RuntimeException('LibreTranslate returned a translation that is not a string.');
                }
                $translations[] = $text;
            }
            if (count($translations) !== count($texts)) {
                throw new RuntimeException(sprintf('LibreTranslate returned %d translations for %d texts.', count($translations), count($texts)));
            }
        } catch (ExceptionInterface $e) {
            $this->logger->error('Manual machine translation request failed.', $context + ['error' => $e::class]);

            throw new RuntimeException(sprintf('LibreTranslate request failed: %s', $e->getMessage()), 0, $e);
        } catch (RuntimeException $e) {
            $this->logger->error('Manual machine translation request failed.', $context + ['error' => $e::class]);

            throw $e;
        }
        $this->logger->info('Manual machine translation request succeeded.', $context);

        return $translations;
    }
}
