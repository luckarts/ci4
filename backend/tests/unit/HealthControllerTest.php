<?php

use App\Controllers\HealthController;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Database\Config as DBConfig;

/**
 * @internal
 */
final class HealthControllerTest extends CIUnitTestCase
{
    use ControllerTestTrait;

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->resetDbInstances();
    }

    public function test_index_returns_200_when_database_is_reachable(): void
    {
        $this->injectMockDb(fn ($mock) => $mock->method('query')->willReturn(true));

        $result = $this->withUri('http://localhost/health')
            ->controller(HealthController::class)
            ->execute('index');

        $this->assertTrue($result->isOK());
        $body = json_decode($result->response()->getBody(), true);
        $this->assertEquals('ok', $body['status']);
        $this->assertEquals('ok', $body['checks']['database']);
    }

    public function test_index_returns_503_when_database_is_unreachable(): void
    {
        $this->injectMockDb(fn ($mock) => $mock->method('query')->willThrowException(
            new \RuntimeException('Connection refused')
        ));

        $result = $this->withUri('http://localhost/health')
            ->controller(HealthController::class)
            ->execute('index');

        $this->assertEquals(503, $result->response()->getStatusCode());
        $body = json_decode($result->response()->getBody(), true);
        $this->assertEquals('degraded', $body['status']);
        $this->assertStringStartsWith('error:', $body['checks']['database']);
    }

    // ---------------------------------------------------------------------------

    private function injectMockDb(callable $configure): void
    {
        $mock = $this->getMockBuilder(\CodeIgniter\Database\BaseConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['query'])
            ->getMockForAbstractClass();

        $configure($mock);

        $ref = new \ReflectionProperty(DBConfig::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, ['tests' => $mock]);
    }

    private function resetDbInstances(): void
    {
        $ref = new \ReflectionProperty(DBConfig::class, 'instances');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }
}
