<?php

declare(strict_types=1);

namespace Deuteros\Tests\Unit\Common;

use Deuteros\Common\GuardrailEnforcer;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Deuteros\Common\GuardrailEnforcer
 */
class GuardrailEnforcerTest extends TestCase
{
    /**
     * @covers ::getUnsupportedMethods
     */
    public function testGetUnsupportedMethods(): void
    {
        $methods = GuardrailEnforcer::getUnsupportedMethods();

        $this->assertIsArray($methods);
        $this->assertArrayHasKey('save', $methods);
        $this->assertArrayHasKey('delete', $methods);
        $this->assertArrayHasKey('access', $methods);
        $this->assertArrayHasKey('getTranslation', $methods);
        $this->assertArrayHasKey('toUrl', $methods);
    }

    /**
     * @covers ::isUnsupportedMethod
     */
    public function testIsUnsupportedMethod(): void
    {
        $this->assertTrue(GuardrailEnforcer::isUnsupportedMethod('save'));
        $this->assertTrue(GuardrailEnforcer::isUnsupportedMethod('delete'));
        $this->assertTrue(GuardrailEnforcer::isUnsupportedMethod('access'));

        $this->assertFalse(GuardrailEnforcer::isUnsupportedMethod('id'));
        $this->assertFalse(GuardrailEnforcer::isUnsupportedMethod('bundle'));
        $this->assertFalse(GuardrailEnforcer::isUnsupportedMethod('nonexistent'));
    }

    /**
     * @covers ::createUnsupportedMethodException
     */
    public function testCreateUnsupportedMethodException(): void
    {
        $exception = GuardrailEnforcer::createUnsupportedMethodException('save');

        $this->assertInstanceOf(\LogicException::class, $exception);
        $this->assertStringContainsString("Method 'save' is not supported", $exception->getMessage());
        $this->assertStringContainsString('unit-test value object', $exception->getMessage());
        $this->assertStringContainsString('Kernel test', $exception->getMessage());
    }

    /**
     * @covers ::createMissingResolverException
     */
    public function testCreateMissingResolverException(): void
    {
        $exception = GuardrailEnforcer::createMissingResolverException(
            'getOwnerId',
            'EntityOwnerInterface'
        );

        $this->assertInstanceOf(\LogicException::class, $exception);
        $this->assertStringContainsString("Method 'getOwnerId'", $exception->getMessage());
        $this->assertStringContainsString("interface 'EntityOwnerInterface'", $exception->getMessage());
        $this->assertStringContainsString('methodOverrides', $exception->getMessage());
    }

    /**
     * @covers ::createMissingResolverExceptionGeneric
     */
    public function testCreateMissingResolverExceptionGeneric(): void
    {
        $exception = GuardrailEnforcer::createMissingResolverExceptionGeneric('customMethod');

        $this->assertInstanceOf(\LogicException::class, $exception);
        $this->assertStringContainsString("Method 'customMethod'", $exception->getMessage());
        $this->assertStringContainsString('methodOverrides', $exception->getMessage());
    }
}
