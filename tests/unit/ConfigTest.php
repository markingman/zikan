<?php declare(strict_types=1);

namespace Zikan;

use Zikan\Exception\ConfigUnexpectedValueException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

class ConfigTest extends TestCase
{
	use TestHelpersTrait;

	public static string $config1;
	public static string $config2;
	public static string $serialized;
	protected ConfigInterface $Config;

	public static function setUpBeforeClass(): void
	{
		static::tmpdir_make();

		$configs = [
			'config1' => [
				'a' => 'A',
				'b' => 'B',
				'c' => 'C',
				'd' => 'D',
			],
			'config2' => [
				'E' => 'E',
				'd' => 'D-NEW',
			]
		];

		foreach (['config1' => 100, 'config2' => 30] as $name => $n) {
			foreach (range(1, $n) as $i) {
				$configs[$name]['a_b_c_' . $i] = md5((string)$i);
			}

			self::$$name = static::$tmpdir . '/' . $name . '.php';

			if (!file_put_contents(self::$$name, '<?php return ' . var_export($configs[$name], true) . ';')) {
				throw new RuntimeException("Could not write ConfigTest fixture file '$name'");
			}
		}
	}

	public static function tearDownAfterClass(): void
	{
		static::tmpdir_remove();
	}

	public function setUp(): void
	{
		try {
			$this->Config = new Config([self::$config1, self::$config2]);
		} catch (Throwable $e) {
			throw new RuntimeException('Could not instantiate Config', previous: $e);
		}
	}

	public function testCreateWithExtraParameters(): void
	{
		/**
		 * @property string $adhoc1
		 * @property string $adhoc2
		 */
// 		$Config = new class ([self::$config1], ['adhoc1' => 'value1', 'adhoc2' => 'value2']) extends Config{};
		$Config = new Config([self::$config1], ['adhoc1' => 'value1', 'adhoc2' => 'value2']);
		$this->assertSame('value1', $Config->get('adhoc1'));
		$this->assertSame('value2', $Config->get('adhoc2'));
	}

	public function testGet(): void
	{
		$this->assertSame('A', $this->Config->get('a'));
		$this->assertSame('B', $this->Config->get('b'));
	}

	public function testList(): void
	{
		$config = $this->Config->list();
		$this->assertCount(105, $config);
	}

	public function testIsset(): void
	{
		$this->assertTrue($this->Config->isset('a'));
		$this->assertFalse($this->Config->isset('z'));
	}

	public function testSerializable(): void
	{
		$serialized = serialize($this->Config);
		$this->assertNotFalse($serialized);
	}

	public function testUnserializable(): void
	{
		$value = $this->Config->get('a');
		$serialized = serialize($this->Config);
		$Config2 = unserialize($serialized);
		$this->assertInstanceOf(ConfigInterface::class, $Config2);
		$this->assertSame($Config2->get('a'), $value);
	}

	public function testUnserializeEmpty(): void
	{
		$config = new Config();

		$this->expectException(ConfigUnexpectedValueException::class);
		$this->expectExceptionMessage('Config requires a "config" array');

		$config->__unserialize([]);
	}

	public function testUnserializeNotArray(): void
	{
		$this->expectException(ConfigUnexpectedValueException::class);
		$this->expectExceptionMessage('Config requires a "config" array');

		$config = new Config();

		$config->__unserialize(['config' => 'string']);
	}

	public function testUnserializeKeyNotString(): void
	{
		$this->expectException(ConfigUnexpectedValueException::class);
		$this->expectExceptionMessage('Tried to load non-string Config key');

		$config = new Config();

		$config->__unserialize([
			'config' => [
				0 => 'value',
			]
		]);
	}

	public function testUnserializeValueNotString(): void
	{
		$this->expectException(ConfigUnexpectedValueException::class);
		$this->expectExceptionMessage('Tried to load non-string Config value for key \'key\'');

		$config = new Config();

		$config->__unserialize([
			'config' => [
				'key' => 100,
			]
		]);
	}
}
