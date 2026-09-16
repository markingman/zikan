<?php

namespace Zikan;

use Zikan\Exception\ContainerError;
use Zikan\Exception\ContainerException;
use Zikan\Test\TestClassPlainSimple;
use Zikan\Test\TestClassWithArguments;
use Zikan\Test\TestClassWithException;
use Zikan\Test\TestClassWithNullableArguments;
use Zikan\Test\TestClassWithNullableUnionArgs;
use Zikan\Test\TestClassWithObjectArguments;
use Zikan\Test\TestClassWithSimpleMethods;
use Zikan\Test\TestClassWithUnionArgs;
use Zikan\Test\TestClassWithUnspecifiedArgs;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;

class ContainerTest extends TestCase
{
	use TestHelpersTrait;

	protected ContainerInterface $Container;
	private string $dir_cache;
	private string $dir_classes;
	private string $dir_registrations;

	public static function setUpBeforeClass(): void
	{
		static::tmpdir_make();
	}

	public static function tearDownAfterClass(): void
	{
		static::tmpdir_remove();
	}

	public function setUp(): void
	{
		$this->Container = new Container();

		if (is_string(static::$tmpdir)) {
			$this->dir_cache = static::$tmpdir;
		} else {
			throw new RuntimeException('Could not set cache dir');
		}

		if (!$dir_fixtures = (string)realpath(__DIR__ . '/../fixtures')) {
			$this->fail('Could not find fixtures dir');
		}

		if (!$this->dir_classes = (string)realpath($dir_fixtures . '/classes')) {
			$this->fail('Could not find classes dir');
		}

		if (!$this->dir_registrations = (string)realpath($dir_fixtures . '/registry')) {
			$this->fail('Could not find registrations dir');
		}
	}

	public function testCreate(): void
	{
		$this->assertInstanceOf(ContainerInterface::class, $this->Container);
	}

	public function testCreateWithRegistrationsPath(): void
	{
		$Container = new Container($this->dir_registrations);
		$this->assertSame($this->dir_registrations, $Container->get_registrations_path(), 'Can set registrations path on construct');
	}

	public function testRegistrationsPath(): void
	{
		$this->assertSame('', $this->Container->get_registrations_path(), 'Unset registrations path is empty');

		$this->Container->set_registrations_path($this->dir_registrations);
		$this->assertSame($this->dir_registrations, $this->Container->get_registrations_path(), 'Can set and get the registrations path');
	}

	public function testAlias(): void
	{
		$this->assertSame('Class1', $this->Container->get_alias('Class1'));

		$this->Container->set_alias('Class1', 'Class2');
		$this->assertSame('Class2', $this->Container->get_alias('Class1'));
	}

	public function testRegisterPath(): void
	{
		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());

		$this->Container->register_path('TestClassPlainSimple', $this->dir_classes . '/TestClassPlainSimple.php');

		$this->assertEquals(
			['TestClassPlainSimple' => [$this->dir_classes . '/TestClassPlainSimple.php']],
			$this->Container->list_registrations()
		);
	}

	public function testRegisterPathWithArguments(): void
	{
		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());

		$this->Container->register_path(
			'TestClassPlainSimple',
			$this->dir_classes . '/TestClassPlainSimple.php',
			['a' => 123, 'b' => 'test']
		);

		$this->assertEquals(
			[
				'TestClassPlainSimple' =>
					[$this->dir_classes . '/TestClassPlainSimple.php', ['a' => 123, 'b' => 'test']]
			],
			$this->Container->list_registrations()
		);
	}

	public function testRegister(): void
	{
		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());

		$this->Container->register('TestClassPlainSimple', function (): TestClassPlainSimple {
			return new TestClassPlainSimple();
		});

		$this->assertEquals(
			[
				'TestClassPlainSimple' => function (): TestClassPlainSimple {
					return new TestClassPlainSimple();
				}
			],
			$this->Container->list_registry(),
			'Can register a simple class'
		);
	}

	public function testCreateFromLocation(): void
	{
		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());

		$this->Container->register_path('TestClassPlainSimple', $this->dir_registrations . '/TestClassPlainSimple.php');

		$this->assertInstanceOf(
			TestClassPlainSimple::class,
			$this->Container->create('TestClassPlainSimple'),
			'Can create class from explicitly set location path'
		);
	}


	public function testCreateFromAssumedLocation(): void
	{
		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());

		$this->Container->set_registrations_path($this->dir_registrations);

		$this->assertInstanceOf(
			TestClassPlainSimple::class,
			$this->Container->create('TestClassPlainSimple'),
			'Can create class from explicitly set location path'
		);
	}

	public function testCreateFromLocationWithParseFailure(): void
	{
		// calling closure throws an exception

		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());

		$this->Container->set_registrations_path($this->dir_cache);
		if (!file_put_contents(
			static::$tmpdir . '/ParseFailure.php', '<?php PARSE FAILURE };'
		)) {
			$this->fail('Could not create parse failure test file');
		}

		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage(ContainerError::LOAD_FAILURE->value);
		$this->expectExceptionCode(500);

		try {
			$this->Container->create('ParseFailure');
		} catch (ContainerException $e) {
			$this->assertSame(ContainerError::LOAD_FAILURE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testCreateFromLocationWithImmedateExceptionFailure(): void
	{
		// calling closure throws an exception

		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());

		$this->Container->set_registrations_path($this->dir_registrations);

		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage('CONTAINER_LOAD_FAILURE');
		$this->expectExceptionCode(500);

		try {
			$this->Container->create('throw_exception_error');
		} catch (ContainerException $e) {
			$this->assertSame(ContainerError::LOAD_FAILURE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testCreateFromLocationWithReturnTypeFailure(): void
	{
		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());

		$this->Container->set_registrations_path($this->dir_registrations);

		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage('CONTAINER_TYPE_FAILURE');
		$this->expectExceptionCode(500);

		try {
			$this->Container->create('return_true');
		} catch (ContainerException $e) {
			$this->assertSame(ContainerError::TYPE_FAILURE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testCreateFromLocationWithClosureExceptionFailure(): void
	{
		// calling closure throws an exception

		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());

		$this->Container->set_registrations_path($this->dir_registrations);

		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage('CONTAINER_CREATE_FAILURE');
		$this->expectExceptionCode(500);

		try {
			$this->Container->create('TestClosureWithException');
		} catch (ContainerException $e) {
			$this->assertSame(ContainerError::CREATE_FAILURE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testCreateFromLocationWithClosureReturnNotObject(): void
	{
		// calling closure throws an exception

		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());

		$this->Container->set_registrations_path($this->dir_registrations);

		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage('CONTAINER_NOT_OBJECT');
		$this->expectExceptionCode(500);

		try {
			$this->Container->create('TestClosureBoolReturn');
		} catch (ContainerException $e) {
			$this->assertSame(ContainerError::NOT_OBJECT, $e->getErrorCode());
			throw $e;
		}
	}

	public function testCreateFromRegistry(): void
	{
		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());

		$this->Container->register('TestClassPlainSimple', function (): TestClassPlainSimple {
			return new TestClassPlainSimple();
		});

		$this->assertInstanceOf(
			TestClassPlainSimple::class,
			$this->Container->create('TestClassPlainSimple'),
			'Can create class from explicit registration'
		);
	}

	public function testCreateFromRegistryAndStore(): void
	{
		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());

		$this->Container->register('TestClassPlainSimple', function (): TestClassPlainSimple {
			return new TestClassPlainSimple();
		});

		$this->Container->create('TestClassPlainSimple');
		$this->assertArrayNotHasKey('TestClassPlainSimple', $this->Container->list_instances());

		$this->Container->create('TestClassPlainSimple', true);
		$this->assertArrayHasKey('TestClassPlainSimple', $this->Container->list_instances());
	}

	public function testCreateFromRegistryWithClosureExceptionFailure(): void
	{
		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());

		$this->Container->register('TestClassWithException', function (): void {
			$this->fail('Test runtime exception');
		});

		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage('CONTAINER_CREATE_FAILURE');
		$this->expectExceptionCode(500);

		try {
			$this->Container->create('TestClassWithException');
		} catch (ContainerException $e) {
			$this->assertSame(ContainerError::CREATE_FAILURE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testCreateFromRegistryWithClosureReturnNotObject(): void
	{
		// calling closure throws an exception

		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());

		$this->Container->register('TestClosureBoolReturn', function (): bool {
			return true;
		});

		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage('CONTAINER_NOT_OBJECT');
		$this->expectExceptionCode(500);

		try {
			$this->Container->create('TestClosureBoolReturn');
		} catch (ContainerException $e) {
			$this->assertSame(ContainerError::NOT_OBJECT, $e->getErrorCode());
			throw $e;
		}
	}

	public function testCreateFromInstantiate(): void
	{
		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());
		$this->assertSame('', $this->Container->get_registrations_path());

		$this->assertInstanceOf(
			TestClassPlainSimple::class,
			$this->Container->create(TestClassPlainSimple::class)
		);
	}

	public function testCreateFromInstantiateAndStore(): void
	{
		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());
		$this->assertSame('', $this->Container->get_registrations_path());

		$this->assertInstanceOf(
			TestClassPlainSimple::class,
			$this->Container->create(TestClassPlainSimple::class, true)
		);
	}

	public function testCreateFromInstantiateFailureNotExists(): void
	{
		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());
		$this->assertSame('', $this->Container->get_registrations_path());

		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage('CONTAINER_CLASS_NOT_FOUND');
		$this->expectExceptionCode(500);

		try {
			$this->Container->create('ClassDoesNotExist');
		} catch (ContainerException $e) {
			$this->assertSame(ContainerError::CLASS_NOT_FOUND, $e->getErrorCode());
			throw $e;
		}
	}

	public function testCreateFromInstantiateFailureException(): void
	{
		$this->assertSame([], $this->Container->list_registrations());
		$this->assertSame([], $this->Container->list_registry());
		$this->assertSame([], $this->Container->list_instances());
		$this->assertSame('', $this->Container->get_registrations_path());

		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage('CONTAINER_INSTANTIATE_FAILURE');
		$this->expectExceptionCode(500);

		try {
			$this->Container->create(TestClassWithException::class);
		} catch (ContainerException $e) {
			$this->assertSame(ContainerError::INSTANTIATE_FAILURE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testCall(): void
	{
		$this->assertTrue($this->Container->call(
			$this->get_test_class_with_simple_methods(), 'test'
		));
	}

	public function testCallNoMethod(): void
	{
		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage('CONTAINER_NOT_CALLABLE');
		$this->expectExceptionCode(500);

		try {
			$this->Container->call($this->get_test_class_with_simple_methods(), 'test2');
		} catch (ContainerException $e) {
			$this->assertSame(ContainerError::NOT_CALLABLE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testCallAndStoreReflection(): void
	{
		$obj = $this->get_test_class_with_simple_methods();

		$this->assertTrue($this->Container->call($obj, 'test', store_reflection: true));

		$this->assertTrue($this->Container->call($obj, 'test'));
	}

	public function testCallWithArgs(): void
	{
		$obj = $this->Container->create(TestClassWithArguments::class);

		if (!$obj instanceof TestClassWithArguments) {
			$this->fail('Test expects TestClassWithArguments object');
		}

		$this->assertEquals([
			1,
			'foo',
			['c' => 'C'],
			true,
			0.1,
			2,
			'S2',
			['A2'],
			false,
			0.2
		], $this->Container->call($obj, 'test', [
			1,
			'foo',
			['c' => 'C'],
			true,
			0.1
		]));
	}

	public function testCallWithNullableArgs(): void
	{
		$obj = $this->Container->create(TestClassWithNullableArguments::class);

		if (!$obj instanceof TestClassWithNullableArguments) {
			$this->fail('Test expects TestClassWithNullableArguments object');
		}

		$this->assertEquals([null, null, null, null], $this->Container->call($obj, 'test'));

		$this->assertEquals([1, null, null, null], $this->Container->call($obj, 'test', [1]));

		$this->assertEquals([null, 'a', null, null], $this->Container->call($obj, 'test', [null, 'a']));

		$this->assertEquals([null, null, ['A'], null], $this->Container->call($obj, 'test', [null, null, ['A']]));

		$this->assertEquals([null, null, null, true], $this->Container->call($obj, 'test', [null, null, null, true]));
	}

	public function testCallWithObjectArgs(): void
	{
		$this->Container->register_path('TestClassWithObjectArguments', $this->dir_registrations . '/TestClassWithObjectArguments.php');
		$this->Container->register_path('TestClassWithSimpleMethods', $this->dir_registrations . '/TestClassWithSimpleMethods.php');
		$obj = $this->Container->create('TestClassWithObjectArguments');

		if (!$obj instanceof TestClassWithObjectArguments) {
			$this->fail('Test expects TestClassWithObjectArguments object');
		}

		$this->assertEquals(
			'abc',
			$this->Container->call($obj, 'get_string')
		);
	}

	public function testCallWithObjectArgsStoreForceNew(): void
	{
		$this->Container->register_path('TestClassWithObjectArguments', $this->dir_registrations . '/TestClassWithObjectArguments.php');
		$this->Container->register_path('TestClassWithSimpleMethods', $this->dir_registrations . '/TestClassWithSimpleMethods.php');
		$obj = $this->Container->create('TestClassWithObjectArguments');

		if (!$obj instanceof TestClassWithObjectArguments) {
			$this->fail('Test expects TestClassWithObjectArguments object');
		}

		$this->assertEquals(0, $this->Container->call($obj, 'inc'), 'Should create new object');

		$this->assertEquals(0, $this->Container->call($obj, 'inc', store: true), 'Should create new object and store');

		$this->assertEquals(1, $this->Container->call($obj, 'inc'), 'Should call stored object');

		$this->assertEquals(2, $this->Container->call($obj, 'inc'), 'Should call stored object again');

		$this->assertEquals(0, $this->Container->call($obj, 'inc', force_new: true), 'Should create new object and leave stored object');

		$this->assertEquals(0, $this->Container->call($obj, 'inc', force_new: true), 'Should create new object and leave stored object again');

		$this->assertEquals(3, $this->Container->call($obj, 'inc'), 'Should call original stored object');

		$this->assertEquals(0, $this->Container->call($obj, 'inc', store: true, force_new: true), 'Should create new object and store');

		$this->assertEquals(1, $this->Container->call($obj, 'inc'), 'Should call new stored object');

		$this->assertEquals(0, $this->Container->call($obj, 'inc', force_new: true), 'Should create new object again');
	}

	public function testCallUnknownMethod(): void
	{
		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage('Could not call stdClass::test');
		$this->expectExceptionCode(500);

		try {
			$this->Container->call((object)[], 'test');
		} catch (ContainerException $e) {
			$this->assertSame(ContainerError::CALL_FAILURE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testCallWithObjectNullableUnionArgs(): void
	{
		$obj = $this->Container->create(TestClassWithNullableUnionArgs::class);

		if (!$obj instanceof TestClassWithNullableUnionArgs) {
			$this->fail('Test expects TestClassWithNullableUnionArgs object');
		}

		$this->assertNull($this->Container->call($obj, 'test'), 'Should default to NULL');

		$this->assertNull($this->Container->call($obj, 'test', [null]), 'Should be setable as NULL');

		$this->assertFalse($this->Container->call($obj, 'test', [false]), 'Should setable as FALSE');

		$this->assertEquals(100, $this->Container->call($obj, 'test', [100]), 'Should setable as INT');
	}

	public function testCallWithObjectUnspecifiedArgsFailure(): void
	{
		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage('CONTAINER_CALL_FAILURE');
		$this->expectExceptionCode(500);

		$obj = $this->Container->create(TestClassWithUnspecifiedArgs::class);
		if (!$obj instanceof TestClassWithUnspecifiedArgs) {
			$this->fail('Test expects TestClassWithUnspecifiedArgs object');
		}

		$this->Container->call($obj, 'test');
	}

	public function testCallWithObjectUnionArgsFailure(): void
	{
		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage('CONTAINER_CALL_FAILURE');

		$obj = $this->Container->create(TestClassWithUnionArgs::class);
		if (!$obj instanceof TestClassWithUnionArgs) {
			$this->fail('Test expects TestClassWithUnionArgs object');
		}

		$this->Container->call($obj, 'test');
	}

	public function testCallWithObjectReqArgsFailure(): void
	{
		$this->Container->register_path('TestClassWithSimpleMethods', $this->dir_registrations . '/TestClassWithSimpleMethods.php');

		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage('CONTAINER_CALL_FAILURE');

		$obj = $this->Container->create('TestClassWithSimpleMethods');
		if (!$obj instanceof TestClassWithSimpleMethods) {
			$this->fail('Test expects TestClassWithSimpleMethods object');
		}

		$this->Container->call($obj, 'req');
	}

	public function testCallWithReqObjectArg(): void
	{
		$this->Container->register('TestClassWithObjectArguments', function (): TestClassWithObjectArguments {
			return new TestClassWithObjectArguments();
		});

		$obj = $this->Container->create('TestClassWithObjectArguments');

		if (!$obj instanceof TestClassWithObjectArguments) {
			$this->fail('Test expects TestClassWithObjectArguments object');
		}

		$this->assertTrue(
			$this->Container->call($obj, 'test'),
			'Should create new object using paramater type');
	}

	public function testCallWithReqInterfaceArg(): void
	{
		$this->Container->set_registrations_path($this->dir_registrations);

		$obj = $this->Container->create('TestClassWithSimpleMethods');
		if (!$obj instanceof TestClassWithSimpleMethods) {
			$this->fail('Test expects TestClassWithSimpleMethods object');
		}

		$this->assertSame('iface',
			$this->Container->call($obj, 'iface'),
			'Should create new object using get_class_name_from_type()');
	}

	public function testGet(): void
	{
		$this->Container->register_path('TestClassPlainSimple', $this->dir_registrations . '/TestClassPlainSimple.php');

		$this->assertInstanceOf(TestClassPlainSimple::class, $this->Container->get('TestClassPlainSimple'));
	}

	public function testGetFromSet(): void
	{
		$this->Container->set('TestClassPlainSimple1', function (): TestClassPlainSimple {
			return new TestClassPlainSimple();
		});

		$this->assertInstanceOf(
			TestClassPlainSimple::class,
			$this->Container->get('TestClassPlainSimple1')
		);
	}

	public function testGetFromAssumedLocation(): void
	{
		$this->Container->set_registrations_path($this->dir_registrations);

		$this->assertInstanceOf(
			TestClassPlainSimple::class,
			$this->Container->get('TestClassPlainSimple'),
			'If class not registered or has registration path set, should assume register Closure in registrations path'
		);
	}

	public function testGetFromRegistry(): void
	{
		$this->Container->register('TestClassPlainSimple', function (): TestClassPlainSimple {
			return new TestClassPlainSimple();
		});

		$this->assertInstanceOf(
			TestClassPlainSimple::class,
			$this->Container->get('TestClassPlainSimple'),
		);
	}

	public function testGetAs(): void
	{
		$this->Container->register_path('TestClassPlainSimple', $this->dir_registrations . '/TestClassPlainSimple.php');

		$this->assertInstanceOf(TestClassPlainSimple::class, $this->Container->get_as('TestClassPlainSimple', TestClassPlainSimple::class));
	}

	public function testGetAsWrongClass(): void
	{
		$this->Container->register_path('TestClassPlainSimple', $this->dir_registrations . '/TestClassPlainSimple.php');

		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage('CONTAINER_UNEXPECTED_CLASS');
		$this->expectExceptionCode(500);

		try {
			$this->Container->get_as('TestClassPlainSimple', TestClassWithSimpleMethods::class);
		} catch (ContainerException $e) {
			$this->assertSame(ContainerError::UNEXPECTED_CLASS, $e->getErrorCode());
			throw $e;
		}
	}

	public function testSetAliasSameName(): void
	{
		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage('CONTAINER_ALIAS_RECURSION');
		$this->expectExceptionCode(500);

		try {
			$this->Container->set_alias('same', 'same');
		} catch (ContainerException $e) {
			$this->assertSame(ContainerError::ALIAS_RECURSION, $e->getErrorCode());
			throw $e;
		}
	}

	public function testSetAliasNameExists(): void
	{
		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage('CONTAINER_ALIAS_RECURSION');
		$this->expectExceptionCode(500);

		try {
			$this->Container->set_alias('same', 'exists');
			$this->Container->set_alias('exists', 'same');
		} catch (ContainerException $e) {
			$this->assertSame(ContainerError::ALIAS_RECURSION, $e->getErrorCode());
			throw $e;
		}
	}

	public function testGetFromAlias(): void
	{
		$this->Container->register_path('TestClassPlainSimple', $this->dir_registrations . '/TestClassPlainSimple.php');

		$this->Container->set_alias('TestClassAlias', 'TestClassPlainSimple');

		$this->assertInstanceOf(
			TestClassPlainSimple::class,
			$this->Container->get('TestClassAlias'),
		);

		$this->Container->set_alias('TestClassAlias2', 'TestClassPlainSimple');

		$this->assertInstanceOf(
			TestClassPlainSimple::class,
			$this->Container->get('TestClassAlias2'),
		);

	}

	public function testSet(): void
	{
		// by registry

		$this->assertFalse($this->Container->exists('TestClassPlainSimple1'));

		$this->Container->set('TestClassPlainSimple1', function (): TestClassPlainSimple {
			return new TestClassPlainSimple();
		});

		$this->assertTrue($this->Container->exists('TestClassPlainSimple1'));
		$this->assertArrayHasKey('TestClassPlainSimple1', $this->Container->list_registry());

		// by instance

		$this->assertFalse($this->Container->exists('TestClassPlainSimple2'));

		$this->Container->set('TestClassPlainSimple2', new TestClassPlainSimple());

		$this->assertTrue($this->Container->exists('TestClassPlainSimple2'));
		$this->assertArrayHasKey('TestClassPlainSimple2', $this->Container->list_instances());
	}

	public function testExists(): void
	{
		// by registry

		$this->assertFalse($this->Container->exists('TestClassPlainSimple1'));

		$this->Container->register('TestClassPlainSimple1', function (): TestClassPlainSimple {
			return new TestClassPlainSimple();
		});

		$this->assertTrue($this->Container->exists('TestClassPlainSimple1'));

		// by location

		$this->assertFalse($this->Container->exists('TestClassPlainSimple2'));

		$this->Container->register_path('TestClassPlainSimple2', $this->dir_registrations . '/TestClassPlainSimple.php');

		$this->assertTrue($this->Container->exists('TestClassPlainSimple2'));

		// by instance

		$this->assertFalse($this->Container->exists('TestClassPlainSimple3'));

		$this->Container->set('TestClassPlainSimple3', new TestClassPlainSimple());

		$this->assertTrue($this->Container->exists('TestClassPlainSimple3'));
	}

	public function testUnset(): void
	{
		$this->assertFalse($this->Container->exists('TestClassPlainSimple'));

		$this->Container->register('TestClassPlainSimple', function (): TestClassPlainSimple {
			return new TestClassPlainSimple();
		});

		$this->assertArrayNotHasKey('TestClassPlainSimple', $this->Container->list_instances());
		$this->Container->create('TestClassPlainSimple', true);

		$this->assertArrayHasKey('TestClassPlainSimple', $this->Container->list_instances());
		$this->Container->unset('TestClassPlainSimple');

		$this->assertArrayNotHasKey('TestClassPlainSimple', $this->Container->list_instances());
	}

	public function testSetParamsOptionalDefaultReflectionException(): void
	{
		$Container = new class extends Container {
			/**
			 * @param array<ReflectionParameter> $params
			 * @return array<mixed>
			 */
			public function testSetParams(array $params): array
			{
				return $this->set_params($params);
			}
		};
	
		$obj = new class {
			/** @param int ...$args */
			public function test(...$args): void
			{
			}
		};
	
		$reflection = new ReflectionMethod($obj, 'test');
		$params = $reflection->getParameters();
	
		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage(			"cannot resolve default value for parameter 'args'"	);
	
		try {
			$Container->testSetParams($params);
		} catch (ContainerException $e) {
			$this->assertSame(				ContainerError::INVALID_ARGUMENT,				$e->getErrorCode()			);
	
			throw $e;
		}
	}

	protected function get_test_class_with_simple_methods(): TestClassWithSimpleMethods
	{
		$obj = $this->Container->create(TestClassWithSimpleMethods::class);

		if (!$obj instanceof TestClassWithSimpleMethods) {
			$this->fail('Container::create() must return TestClassWithSimpleMethods object');
		}

		return $obj;
	}

}
