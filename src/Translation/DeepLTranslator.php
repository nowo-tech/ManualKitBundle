<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Translation;

use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function count;
use function is_array;
use function is_string;
use function sprintf;

/**
 * DeepL API (`/v2/translate`, XML tag handling). A key ending in `:fx` is a DeepL API Free key and goes to
 * api-free.deepl.com.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class DeepLTranslator implements MachineTranslatorInterface
{
    /** DeepL needs a regional variant for these target languages. */
    private const array TARGETS = ['en' => 'EN-GB', 'pt' => 'PT-PT'];

    /**
     * @param string $formality `default`, `less` or `more` (sent as `prefer_*`, so languages
     *                          without formality still translate)
     * @param array<string, string> $languages locale => DeepL target language, over the defaults
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $url = '',
        private string $formality = 'default',
        private array $languages = [],
    ) {
    }

    public function translate(array $texts, string $sourceLocale, string $targetLocale): array
    {
        if ($texts === []) {
            return [];
        }
        $endpoint = $this->url !== '' ? rtrim($this->url, '/') : (str_ends_with($this->apiKey, ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com');
        $body     = [
            'text'                => $texts,
            'source_lang'         => $this->language($sourceLocale),
            'target_lang'         => $this->languages[$targetLocale] ?? self::TARGETS[$targetLocale] ?? $this->language($targetLocale),
            'tag_handling'        => 'xml',
            'ignore_tags'         => ['x'],
            'preserve_formatting' => true,
        ];
        if ($this->formality !== 'default') {
            $body['formality'] = 'prefer_' . $this->formality;
        }

        $data = $this->httpClient->request('POST', $endpoint . '/v2/translate', [
            'headers' => ['Authorization' => 'DeepL-Auth-Key ' . $this->apiKey],
            'json'    => $body,
        ])->toArray();

        $translations = [];
        foreach (is_array($data['translations'] ?? null) ? $data['translations'] : [] as $row) {
            if (!is_array($row) || !is_string($row['text'] ?? null)) {
                throw new RuntimeException('DeepL returned a translation without text.');
            }
            $translations[] = $row['text'];
        }
        if (count($translations) !== count($texts)) {
            throw new RuntimeException(sprintf('DeepL returned %d translations for %d texts.', count($translations), count($texts)));
        }

        return $translations;
    }

    private function language(string $locale): string
    {
        return strtoupper(str_replace('_', '-', $locale));
    }
}
