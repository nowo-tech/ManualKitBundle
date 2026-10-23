<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Support;

use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * framework.http_client.mock_response_factory for the tests: a LibreTranslate that prefixes every text with
 * "[target] ".
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class EchoTranslationResponses
{
    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options): JsonMockResponse
    {
        /** @var array{q: list<string>, target: string} $body */
        $body = json_decode((string) $options['body'], true);

        return new JsonMockResponse(['translatedText' => array_map(static fn (string $text): string => '[' . $body['target'] . '] ' . $text, $body['q'])]);
    }
}
