<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Fixture;

use Magento\Framework\HTTP\ClientInterface;

/**
 * An HTTP client that answers from a script instead of the network, and records every request it is asked to make
 *
 * The platform's client factory creates a new client per request, so the script and the record are kept on the
 * class; reset() starts a test with both empty. An answer is `{"status", "headers", "body"}`, optionally with
 * `progress` (the downloaded byte counts passed to the transfer callback the reader set, as the client's transfer
 * would) and `advance` (seconds the given clock moves while the request runs), or `{"fail": message}` for a
 * request the client gives up on.
 */
class ScriptedHttpClient implements ClientInterface
{
    /**
     * @var list<array<string, mixed>>
     */
    private static array $script = [];

    /**
     * @var list<array{url: string, options: array<mixed>, headers: array<mixed>, cookies: array<mixed>}>
     */
    private static array $requests = [];

    /**
     * @var list<int> what the transfer callback answered, in call order
     */
    private static array $progressAnswers = [];

    /**
     * @var FakeMonotonicClock|null
     */
    private static ?FakeMonotonicClock $clock = null;

    /**
     * @var array<mixed>
     */
    private array $options = [];

    /**
     * @var array<mixed>
     */
    private array $headers = [];

    /**
     * @var array<mixed>
     */
    private array $cookies = [];

    /**
     * @var array<string, mixed>
     */
    private array $answer = [];

    /**
     * Start with an empty record and a script of answers, in order
     *
     * @param list<array<string, mixed>> $script
     * @param FakeMonotonicClock|null $clock moved by an answer's `advance`
     * @return void
     */
    public static function reset(array $script, ?FakeMonotonicClock $clock = null): void
    {
        self::$script = $script;
        self::$requests = [];
        self::$progressAnswers = [];
        self::$clock = $clock;
    }

    /**
     * The requests made so far
     *
     * @return list<array{url: string, options: array<mixed>, headers: array<mixed>, cookies: array<mixed>}>
     */
    public static function requests(): array
    {
        return self::$requests;
    }

    /**
     * What the transfer callback answered
     *
     * @return list<int>
     */
    public static function progressAnswers(): array
    {
        return self::$progressAnswers;
    }

    /**
     * Make the request: record it and take the script's next answer
     *
     * @param string $uri
     * @return array<mixed> nothing, as the platform's client returns nothing
     */
    public function get($uri)
    {
        self::$requests[] = [
            'url' => (string) $uri,
            'options' => $this->options,
            'headers' => $this->headers,
            'cookies' => $this->cookies,
        ];
        $answer = array_shift(self::$script);
        if ($answer === null) {
            throw new \LogicException('The script has no answer left for ' . $uri);
        }
        $advance = $answer['advance'] ?? 0;
        if (self::$clock !== null && is_numeric($advance)) {
            self::$clock->advance((float) $advance);
        }
        $progress = $this->options[CURLOPT_PROGRESSFUNCTION] ?? null;
        foreach (is_array($answer['progress'] ?? null) ? $answer['progress'] : [] as $downloaded) {
            if (is_callable($progress)) {
                $answered = $progress(null, 0, is_int($downloaded) ? $downloaded : 0, 0, 0);
                self::$progressAnswers[] = is_int($answered) ? $answered : -1;
                if ($answered !== 0) {
                    throw new \Exception('Callback aborted');
                }
            }
        }
        if (isset($answer['fail'])) {
            throw new \Exception(is_string($answer['fail']) ? $answer['fail'] : 'failed');
        }
        $this->answer = $answer;

        return [];
    }

    /**
     * The page test never posts
     *
     * @param string $uri
     * @param array<mixed>|string $params
     * @return void
     */
    public function post($uri, $params)
    {
        throw new \LogicException('The page test only reads.');
    }

    /**
     * The scripted answer's headers
     *
     * @return array<mixed>
     */
    public function getHeaders()
    {
        return is_array($this->answer['headers'] ?? null) ? $this->answer['headers'] : [];
    }

    /**
     * @inheritDoc
     */
    public function getBody()
    {
        return is_string($this->answer['body'] ?? null) ? $this->answer['body'] : '';
    }

    /**
     * @inheritDoc
     */
    public function getStatus()
    {
        return is_int($this->answer['status'] ?? null) ? $this->answer['status'] : 0;
    }

    /**
     * The answer sets no cookies
     *
     * @return array<mixed>
     */
    public function getCookies()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function setTimeout($value)
    {
        $this->options[CURLOPT_TIMEOUT] = $value;
    }

    /**
     * Set the request's headers
     *
     * @param array<mixed> $headers
     * @return void
     */
    public function setHeaders($headers)
    {
        $this->headers = (array) $headers;
    }

    /**
     * @inheritDoc
     */
    public function addHeader($name, $value)
    {
        $this->headers[$name] = $value;
    }

    /**
     * @inheritDoc
     */
    public function removeHeader($name)
    {
        unset($this->headers[$name]);
    }

    /**
     * @inheritDoc
     */
    public function setCredentials($login, $pass)
    {
        throw new \LogicException('The page test sends no credentials.');
    }

    /**
     * @inheritDoc
     */
    public function addCookie($name, $value)
    {
        $this->cookies[$name] = $value;
    }

    /**
     * @inheritDoc
     */
    public function removeCookie($name)
    {
        unset($this->cookies[$name]);
    }

    /**
     * Set the request's cookies
     *
     * @param array<mixed> $cookies
     * @return void
     */
    public function setCookies($cookies)
    {
        $this->cookies = (array) $cookies;
    }

    /**
     * @inheritDoc
     */
    public function removeCookies()
    {
        $this->cookies = [];
    }

    /**
     * @inheritDoc
     */
    public function setOption($key, $value)
    {
        $this->options[$key] = $value;
    }

    /**
     * Set the request's client options
     *
     * @param array<mixed> $arr
     * @return void
     */
    public function setOptions($arr)
    {
        $this->options = (array) $arr;
    }
}
