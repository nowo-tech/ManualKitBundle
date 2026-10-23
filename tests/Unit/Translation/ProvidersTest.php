<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Translation;

use Nowo\ManualKitBundle\Translation\DeepLTranslator;
use Nowo\ManualKitBundle\Translation\LibreTranslateTranslator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ProvidersTest extends TestCase
{
    public function testDeepLFreeKeyRequest(): void
    {
        $request = [];
        $client  = new MockHttpClient(static function (string $method, string $url, array $options) use (&$request): JsonMockResponse {
            $request = ['method' => $method, 'url' => $url, 'body' => json_decode((string) $options['body'], true), 'headers' => $options['normalized_headers']['authorization'][0] ?? ''];

            return new JsonMockResponse(['translations' => [['text' => 'Hola'], ['text' => 'Adiós']]]);
        });

        $out = new DeepLTranslator($client, 'secret:fx', '', 'less')->translate(['Hello', 'Bye'], 'en', 'pt');

        self::assertSame(['Hola', 'Adiós'], $out);
        self::assertSame('POST', $request['method']);
        self::assertSame('https://api-free.deepl.com/v2/translate', $request['url']);
        self::assertSame('Authorization: DeepL-Auth-Key secret:fx', $request['headers']);
        self::assertSame(['text' => ['Hello', 'Bye'], 'source_lang' => 'EN', 'target_lang' => 'PT-PT', 'tag_handling' => 'xml', 'ignore_tags' => ['x'], 'preserve_formatting' => true, 'formality' => 'prefer_less'], $request['body']);
    }

    public function testDeepLProKeyCustomLanguageAndUrl(): void
    {
        $urls   = [];
        $bodies = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$urls, &$bodies): JsonMockResponse {
            $urls[]   = $url;
            $bodies[] = json_decode((string) $options['body'], true);

            return new JsonMockResponse(['translations' => [['text' => 'x']]]);
        });

        new DeepLTranslator($client, 'pro')->translate(['a'], 'en', 'de_CH');
        new DeepLTranslator($client, 'pro', 'https://deepl.example/', languages: ['pt' => 'PT-BR'])->translate(['a'], 'en', 'pt');

        self::assertSame(['https://api.deepl.com/v2/translate', 'https://deepl.example/v2/translate'], $urls);
        self::assertSame('DE-CH', $bodies[0]['target_lang']);
        self::assertArrayNotHasKey('formality', $bodies[0]);
        self::assertSame('PT-BR', $bodies[1]['target_lang']);
    }

    public function testNothingToTranslateMakesNoRequest(): void
    {
        $client = new MockHttpClient(static fn (): never => throw new RuntimeException('no request expected'));

        self::assertSame([], new DeepLTranslator($client, 'k')->translate([], 'en', 'es'));
        self::assertSame([], new LibreTranslateTranslator($client, 'http://libre')->translate([], 'en', 'es'));
    }

    public function testDeepLRejectsAMalformedAnswer(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('without text');
        new DeepLTranslator(new MockHttpClient(new JsonMockResponse(['translations' => [['detected' => 'EN']]])), 'k')->translate(['a'], 'en', 'es');
    }

    public function testDeepLRejectsAShortAnswer(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('0 translations for 1 texts');
        new DeepLTranslator(new MockHttpClient(new JsonMockResponse([])), 'k')->translate(['a'], 'en', 'es');
    }

    public function testLibreTranslateRequest(): void
    {
        $request = [];
        $client  = new MockHttpClient(static function (string $method, string $url, array $options) use (&$request): JsonMockResponse {
            $request = ['url' => $url, 'body' => json_decode((string) $options['body'], true)];

            return new JsonMockResponse(['translatedText' => ['Hola']]);
        });

        self::assertSame(['Hola'], new LibreTranslateTranslator($client, 'http://libre:5000/', 'key', ['pt' => 'pb'])->translate(['Hello'], 'en', 'pt'));
        self::assertSame('http://libre:5000/translate', $request['url']);
        self::assertSame(['q' => ['Hello'], 'source' => 'en', 'target' => 'pb', 'format' => 'html', 'api_key' => 'key'], $request['body']);

        new LibreTranslateTranslator($client, 'http://libre')->translate(['Hello'], 'en', 'es');
        self::assertArrayNotHasKey('api_key', $request['body']);
    }

    public function testLibreTranslateRejectsAMalformedAnswer(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not a string');
        new LibreTranslateTranslator(new MockHttpClient(new JsonMockResponse(['translatedText' => [1]])), 'http://libre')->translate(['a'], 'en', 'es');
    }

    public function testLibreTranslateRejectsAShortAnswer(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('0 translations for 1 texts');
        new LibreTranslateTranslator(new MockHttpClient(new JsonMockResponse(['translatedText' => 'Hola'])), 'http://libre')->translate(['a'], 'en', 'es');
    }
}
