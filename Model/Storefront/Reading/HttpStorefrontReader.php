<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront\Reading;

use Hryvinskyi\RichSnippets\Api\Data\ReadFailure;
use Hryvinskyi\RichSnippets\Api\Data\StorefrontPageInterface;
use Hryvinskyi\RichSnippets\Api\Data\StorefrontPageInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Exception\StorefrontReadException;
use Hryvinskyi\RichSnippets\Api\StorefrontPathInterface;
use Hryvinskyi\RichSnippets\Api\StorefrontReaderInterface;
use Hryvinskyi\RichSnippets\Model\Clock\MonotonicClockInterface;
use Hryvinskyi\RichSnippets\Model\Store\StoreAddresses;
use Magento\Framework\HTTP\ClientFactory;
use Magento\Framework\HTTP\ClientInterface;

/**
 * Reads a storefront page with the platform's HTTP client, guarding every request it makes
 *
 * - The address is the store view's base address followed by the normalised path; a base address that is not http
 *   or https is refused before any request.
 * - The client never follows a redirect itself. A redirect is followed here, to an address resolved against the
 *   current one the way a browser resolves it, only when its scheme, host and port equal the base address's and it
 *   carries no user name; at most a few times.
 * - Each request may take at most the connection time to connect and what is left of one deadline for the whole
 *   read; a request that would start after the deadline is not made.
 * - A body larger than the size limit is refused: the transfer is aborted as soon as it passes the limit, and an
 *   answer that arrives larger anyway is refused too.
 */
class HttpStorefrontReader implements StorefrontReaderInterface
{
    private const SCHEMES = ['http' => 80, 'https' => 443];
    private const ABSOLUTE = '~\A[a-z][a-z0-9+.-]*:~i';
    private const ORIGIN_PREFIX = '~\A([a-zA-Z][a-zA-Z0-9+.-]*)://[^/?#]*~';
    private const UNSAFE_LOCATION = '~[\x00-\x20\x7F]~';
    private const LOCATION_HEADER = 'location';
    private const USER_AGENT = 'Mozilla/5.0 (compatible; Rich Snippets page test)';
    private const ACCEPT = 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8';

    /**
     * @param ClientFactory $clients
     * @param StoreAddresses $addresses
     * @param StorefrontPathInterface $paths
     * @param VisitorCookies $cookies
     * @param MonotonicClockInterface $clock
     * @param StorefrontPageInterfaceFactory $pages
     * @param AddressParser $parser
     * @param int $connectTimeoutMs the most one request may take to connect
     * @param int $deadlineMs the most the whole read may take, redirects included
     * @param int $maxBytes the largest body read
     * @param int $maxRedirects the most redirects followed
     */
    public function __construct(
        private readonly ClientFactory $clients,
        private readonly StoreAddresses $addresses,
        private readonly StorefrontPathInterface $paths,
        private readonly VisitorCookies $cookies,
        private readonly MonotonicClockInterface $clock,
        private readonly StorefrontPageInterfaceFactory $pages,
        private readonly AddressParser $parser,
        private readonly int $connectTimeoutMs = 5000,
        private readonly int $deadlineMs = 15000,
        private readonly int $maxBytes = 5242880,
        private readonly int $maxRedirects = 3
    ) {
    }

    /**
     * @inheritDoc
     */
    public function read(int $storeId, string $path): StorefrontPageInterface
    {
        $path = $this->paths->normalize($path);
        $base = $this->addresses->page('', $storeId);
        $origin = $this->origin($base) ?? throw new StorefrontReadException(ReadFailure::NotHttp);
        $url = $this->addresses->page($path, $storeId);
        if ($this->origin($url) !== $origin) {
            throw new StorefrontReadException(ReadFailure::RedirectElsewhere);
        }
        $cookies = $this->cookies->forStore($storeId);
        $deadline = $this->clock->seconds() + $this->deadlineMs / 1000;
        for ($redirects = 0;; $redirects++) {
            $answer = $this->fetch($url, $cookies, $deadline);
            if ($answer['status'] >= 300 && $answer['status'] < 400 && $answer['location'] !== null) {
                if ($redirects >= $this->maxRedirects) {
                    throw new StorefrontReadException(ReadFailure::TooManyRedirects, $answer['status']);
                }
                $url = $this->resolve($url, $answer['location']);
                if ($url === null || $this->origin($url) !== $origin) {
                    throw new StorefrontReadException(ReadFailure::RedirectElsewhere, $answer['status']);
                }
                continue;
            }
            if ($answer['status'] < 200 || $answer['status'] > 299) {
                throw new StorefrontReadException(ReadFailure::HttpStatus, $answer['status']);
            }

            return $this->pages->create([
                'url' => $url,
                'path' => $this->pathOf($url, $base),
                'status' => $answer['status'],
                'body' => $answer['body'],
            ]);
        }
    }

    /**
     * Make one request, within what is left of the deadline
     *
     * @param string $url
     * @param array<string,string> $cookies
     * @param float $deadline on the monotonic clock
     * @return array{status: int, location: string|null, body: string}
     * @throws StorefrontReadException
     */
    private function fetch(string $url, array $cookies, float $deadline): array
    {
        $remainingMs = (int) floor(($deadline - $this->clock->seconds()) * 1000);
        if ($remainingMs <= 0) {
            throw new StorefrontReadException(ReadFailure::Timeout);
        }
        $tooLarge = false;
        $client = $this->clients->create();
        $client->setOptions($this->options($remainingMs, $tooLarge));
        $client->setHeaders(['User-Agent' => self::USER_AGENT, 'Accept' => self::ACCEPT]);
        if ($cookies !== []) {
            $client->setCookies($cookies);
        }
        try {
            $client->get($url);
        } catch (\Exception $e) {
            throw new StorefrontReadException($this->transportFailure($e, $tooLarge, $deadline), null, $e);
        }
        $body = (string) $client->getBody();
        if ($tooLarge || strlen($body) > $this->maxBytes) {
            throw new StorefrontReadException(ReadFailure::TooLarge, (int) $client->getStatus());
        }
        $status = (int) $client->getStatus();
        if ($status === 0) {
            throw new StorefrontReadException(ReadFailure::Unreachable);
        }

        return ['status' => $status, 'location' => $this->location($client), 'body' => $body];
    }

    /**
     * The client options of one request: no redirects, http and https only, the time limits and the size limit
     *
     * @param int $remainingMs
     * @param bool $tooLarge set once the transfer passes the size limit
     * @return array<int, mixed>
     */
    private function options(int $remainingMs, bool &$tooLarge): array
    {
        $maxBytes = $this->maxBytes;

        return [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT_MS => min($this->connectTimeoutMs, $remainingMs),
            CURLOPT_TIMEOUT_MS => $remainingMs,
            CURLOPT_MAXFILESIZE => $maxBytes,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => static function (
                mixed $handle,
                int $downloadTotal,
                int $downloaded
            ) use (
                &$tooLarge,
                $maxBytes
            ): int {
                if ($downloaded > $maxBytes || $downloadTotal > $maxBytes) {
                    $tooLarge = true;

                    return 1;
                }

                return 0;
            },
        ];
    }

    /**
     * Why a request the client gave up on failed
     *
     * @param \Exception $failure
     * @param bool $tooLarge
     * @param float $deadline
     * @return ReadFailure
     */
    private function transportFailure(\Exception $failure, bool $tooLarge, float $deadline): ReadFailure
    {
        if ($tooLarge) {
            return ReadFailure::TooLarge;
        }
        if ($this->clock->seconds() >= $deadline || stripos($failure->getMessage(), 'timed out') !== false) {
            return ReadFailure::Timeout;
        }

        return ReadFailure::Unreachable;
    }

    /**
     * The answer's redirect target as sent; null when it sent none
     *
     * @param ClientInterface $client
     * @return string|null
     */
    private function location(ClientInterface $client): ?string
    {
        foreach ((array) $client->getHeaders() as $name => $value) {
            if (strtolower((string) $name) === self::LOCATION_HEADER && is_string($value)) {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * A redirect target resolved against the address that sent it, the way a browser resolves it
     *
     * A backslash counts as a slash, as browsers count it. A target with white space or control characters is not
     * followed.
     *
     * @param string $current
     * @param string $location
     * @return string|null null when the target cannot be followed
     */
    private function resolve(string $current, string $location): ?string
    {
        $location = str_replace('\\', '/', $location);
        if ($location === '' || preg_match(self::UNSAFE_LOCATION, $location) === 1) {
            return null;
        }
        if (preg_match(self::ABSOLUTE, $location) === 1) {
            return $location;
        }
        if (preg_match(self::ORIGIN_PREFIX, $current, $prefix) !== 1) {
            return null;
        }
        if (str_starts_with($location, '//')) {
            return $prefix[1] . ':' . $location;
        }
        $origin = $prefix[0];
        $path = $this->parser->parse($current)['path'];
        $path = $path === '' ? '/' : $path;
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        if (str_starts_with($location, '?')) {
            return $origin . $path . $location;
        }
        if (str_starts_with($location, '#')) {
            return $current;
        }

        return $origin . substr($path, 0, (int) strrpos($path, '/') + 1) . $location;
    }

    /**
     * The path and query of an address of the store view, from its base address on when it is under it
     *
     * @param string $url
     * @param string $base
     * @return string
     */
    private function pathOf(string $url, string $base): string
    {
        if (str_starts_with($url, $base)) {
            return '/' . substr($url, strlen($base));
        }
        $parts = $this->parser->parse($url);
        $path = $parts['path'] === '' ? '/' : $parts['path'];

        return $path . ($parts['query'] === null ? '' : '?' . $parts['query']);
    }

    /**
     * The scheme, host and port of an http or https address without a user name; null for any other address
     *
     * @param string $url
     * @return string|null
     */
    private function origin(string $url): ?string
    {
        $parts = $this->parser->parse($url);
        $scheme = $parts['scheme'] ?? '';
        if (!isset(self::SCHEMES[$scheme]) || $parts['host'] === null || $parts['user'] !== null) {
            return null;
        }

        return $scheme . '://' . $parts['host'] . ':' . ($parts['port'] ?? self::SCHEMES[$scheme]);
    }
}
