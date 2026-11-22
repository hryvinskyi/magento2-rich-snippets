<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Config;

use Hryvinskyi\RichSnippets\Model\Config\Backend\CheckTime;
use Magento\Config\Model\Config;
use Magento\Config\Model\Config\Factory as ConfigFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The catalog check's settings saved the way Stores › Configuration saves them
 */
#[AppArea('adminhtml')]
#[DbIsolation(true)]
class CheckSettingsSaveTest extends TestCase
{
    private const SECTION = 'hryvinskyi_rich_snippets';

    public function testTheCronExpressionShipsWithTheDefaultTime(): void
    {
        $config = Bootstrap::getObjectManager()->get(ScopeConfigInterface::class);
        self::assertInstanceOf(ScopeConfigInterface::class, $config);

        self::assertSame('0 3 * * *', $config->getValue(CheckTime::CRON_EXPRESSION_PATH));
        self::assertSame('03:00', $config->getValue('hryvinskyi_rich_snippets/check/time'));
    }

    public function testAStartTimeIsStoredAndItsCronExpressionWritten(): void
    {
        $this->save(['enabled' => '1', 'time' => ' 22:05 ', 'email' => '']);

        self::assertSame('22:05', $this->stored('hryvinskyi_rich_snippets/check/time'));
        self::assertSame('5 22 * * *', $this->stored(CheckTime::CRON_EXPRESSION_PATH));
    }

    public function testAStartTimeThatIsNotHoursAndMinutesIsRefusedWithTheDesignedMessage(): void
    {
        try {
            $this->save(['enabled' => '1', 'time' => '3am', 'email' => '']);
            self::fail('The time was accepted.');
        } catch (LocalizedException $e) {
            self::assertSame('Enter a time such as 03:00 or 23:30.', $e->getMessage());
        }

        self::assertNull($this->stored('hryvinskyi_rich_snippets/check/time'));
        self::assertNull($this->stored(CheckTime::CRON_EXPRESSION_PATH));
    }

    public function testWithTheCheckSwitchedOffTheTimeIsNotCheckedAndNoExpressionIsWritten(): void
    {
        $this->save(['enabled' => '0', 'time' => '3am']);

        self::assertSame('0', $this->stored('hryvinskyi_rich_snippets/check/enabled'));
        self::assertNull($this->stored(CheckTime::CRON_EXPRESSION_PATH));
    }

    public function testRecipientsAreStoredSeparatedByACommaAndASpace(): void
    {
        $this->save(['enabled' => '1', 'time' => '03:00', 'email' => 'seo@example.com,ops@shop.co.uk ']);

        self::assertSame('seo@example.com, ops@shop.co.uk', $this->stored('hryvinskyi_rich_snippets/check/email'));
    }

    public function testARecipientThatIsNotACompleteAddressIsRefusedWithTheDesignedMessage(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Enter complete e-mail addresses, separated by commas.');

        $this->save(['enabled' => '1', 'time' => '03:00', 'email' => 'seo@linden-co']);
    }

    /**
     * Save the catalog check group at the default scope
     *
     * @param array<string,string> $values
     * @return void
     */
    private function save(array $values): void
    {
        $fields = [];
        foreach ($values as $field => $value) {
            $fields[$field] = ['value' => $value];
        }
        $factory = Bootstrap::getObjectManager()->get(ConfigFactory::class);
        self::assertInstanceOf(ConfigFactory::class, $factory);
        $config = $factory->create([
            'data' => [
                'section' => self::SECTION,
                'website' => null,
                'store' => null,
                'groups' => ['check' => ['fields' => $fields]],
            ],
        ]);
        self::assertInstanceOf(Config::class, $config);
        $config->save();
    }

    /**
     * The value stored at the default scope for a path
     *
     * @param string $path
     * @return string|null
     */
    private function stored(string $path): ?string
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        self::assertInstanceOf(ResourceConnection::class, $resource);
        $connection = $resource->getConnection();
        $values = $connection->fetchCol(
            $connection->select()
                ->from($resource->getTableName('core_config_data'), ['value'])
                ->where('path = ?', $path)
                ->where('scope = ?', 'default')
                ->where('scope_id = ?', 0)
        );

        $value = $values[0] ?? null;

        return is_string($value) ? $value : null;
    }
}
