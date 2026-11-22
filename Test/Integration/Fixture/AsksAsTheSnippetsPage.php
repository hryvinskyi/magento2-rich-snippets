<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Fixture;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Acl\Builder as AclBuilder;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Request\PathInfo;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Authorization\RoleLocatorInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/**
 * Asks the Snippets page's endpoints the way its island does — AJAX, a JSON body, the form key in the query string —
 * and narrows the signed-in administrator's permissions for a test
 *
 * For a class extending the platform's backend controller test case. Every request starts a new exchange with the
 * admin's secret keys off, as the backend controller test starts each test.
 */
trait AsksAsTheSnippetsPage
{
    /**
     * GET an admin path as the island does and decode the JSON answer
     *
     * @param string $path as `backend/frontname/controller/action`
     * @param array<string, string> $query
     * @param int $status the status the answer must have
     * @return array<mixed>
     */
    private function read(string $path, array $query = [], int $status = 200): array
    {
        $this->newExchange();
        $request = $this->httpRequest();
        $request->setMethod(HttpRequest::METHOD_GET);
        $request->setQueryValue('isAjax', 'true');
        foreach ($query as $name => $value) {
            $request->setQueryValue($name, $value);
        }
        $this->follow($path);

        return $this->answer($status);
    }

    /**
     * POST a JSON body to an admin path as the island does and decode the JSON answer
     *
     * @param string $path as `backend/frontname/controller/action`
     * @param mixed $body
     * @param int $status the status the answer must have
     * @return array<mixed>
     */
    private function post(string $path, mixed $body, int $status = 200): array
    {
        $this->newExchange();
        $this->askAsTheBrowser(HttpRequest::METHOD_POST, $body);
        $this->follow($path);

        return $this->answer($status);
    }

    /**
     * POST an admin path without the form key and prove the platform refused it before the action ran
     *
     * The test framework writes a valid key into the body of every POST it dispatches, so the key is left out where
     * the island puts it — an empty query value, which the platform reads in place of the body's.
     *
     * @param string $path
     * @param mixed $body
     * @return void
     */
    private function assertPostWithoutFormKeyIsRefused(string $path, mixed $body): void
    {
        $this->newExchange();
        $request = $this->httpRequest();
        $request->setMethod(HttpRequest::METHOD_POST);
        $request->setQueryValue('isAjax', 'true');
        $request->setQueryValue('form_key', '');
        $request->setContent((string) json_encode($body, JSON_THROW_ON_ERROR));
        $this->follow($path);
        $answer = json_decode((string) $this->httpResponse()->getBody(), true);
        self::assertIsArray($answer, (string) $this->httpResponse()->getBody());
        self::assertTrue(
            ($answer['error'] ?? null) === true || isset($answer['ajaxExpired']),
            (string) json_encode($answer)
        );
    }

    /**
     * Prepare the request as the browser sends it: AJAX, the form key in the query string, a JSON body
     *
     * @param string $method
     * @param mixed $body encoded as JSON unless it is text already
     * @return void
     */
    private function askAsTheBrowser(string $method, mixed $body = ''): void
    {
        $request = $this->httpRequest();
        $request->setMethod($method);
        $request->setQueryValue('isAjax', 'true');
        $request->setQueryValue('form_key', $this->formKey());
        $request->setContent(is_string($body) ? $body : (string) json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * The decoded answer of the last request, which must have a status
     *
     * @param int $status
     * @return array<mixed>
     */
    private function answer(int $status): array
    {
        $response = $this->httpResponse();
        self::assertSame($status, $response->getHttpResponseCode(), (string) $response->getBody());
        $answer = json_decode((string) $response->getBody(), true);
        self::assertIsArray($answer, (string) $response->getBody());

        return $answer;
    }

    /**
     * Start a new request and a clean response with the admin's secret keys off
     *
     * @return void
     */
    private function newExchange(): void
    {
        $this->httpRequest()->_resetState();
        $response = $this->httpResponse();
        $response->clearHeaders();
        $response->setHttpResponseCode(200);
        $response->setBody('');
        $url = $this->testObjectManager()->get(UrlInterface::class);
        self::assertInstanceOf(UrlInterface::class, $url);
        $url->turnOffSecretKey();
    }

    /**
     * Dispatch an admin path, working its path out from the address as the platform does for an admin address
     *
     * @param string $path
     * @return void
     */
    private function follow(string $path): void
    {
        $request = $this->httpRequest();
        $request->setRequestUri($path);
        $pathInfo = $this->testObjectManager()->get(PathInfo::class);
        self::assertInstanceOf(PathInfo::class, $pathInfo);
        $request->setPathInfo(
            $pathInfo->getPathInfo((string) $request->getRequestUri(), (string) $request->getBaseUrl())
        );
        $this->dispatch($path);
    }

    /**
     * Take an access rule away from the signed-in administrator's role for the rest of the test
     *
     * @param string $rule
     * @return void
     */
    private function deny(string $rule): void
    {
        $locator = $this->testObjectManager()->get(RoleLocatorInterface::class);
        self::assertInstanceOf(RoleLocatorInterface::class, $locator);
        $roleId = $locator->getAclRoleId();
        self::assertIsScalar($roleId, 'No administrator is signed in.');
        $builder = $this->testObjectManager()->get(AclBuilder::class);
        self::assertInstanceOf(AclBuilder::class, $builder);
        self::assertTrue($builder->getAcl()->hasResource($rule), $rule . ' is declared.');
        $builder->getAcl()->deny((string) $roleId, $rule);
    }

    /**
     * The member of a decoded JSON document at a path of names, or null when there is none
     *
     * @param mixed $document
     * @param string|int ...$path
     * @return mixed
     */
    private function at(mixed $document, string|int ...$path): mixed
    {
        foreach ($path as $name) {
            if (!is_array($document) || !array_key_exists($name, $document)) {
                return null;
            }
            $document = $document[$name];
        }

        return $document;
    }

    /**
     * The session's form key
     *
     * @return string
     */
    private function formKey(): string
    {
        $formKey = $this->testObjectManager()->get(FormKey::class);
        self::assertInstanceOf(FormKey::class, $formKey);

        return $formKey->getFormKey();
    }

    /**
     * @return HttpRequest
     */
    private function httpRequest(): HttpRequest
    {
        $request = $this->getRequest();
        self::assertInstanceOf(HttpRequest::class, $request);

        return $request;
    }

    /**
     * @return HttpResponse
     */
    private function httpResponse(): HttpResponse
    {
        $response = $this->getResponse();
        self::assertInstanceOf(HttpResponse::class, $response);

        return $response;
    }

    /**
     * @return ObjectManager
     */
    private function testObjectManager(): ObjectManager
    {
        $objectManager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $objectManager);

        return $objectManager;
    }
}
