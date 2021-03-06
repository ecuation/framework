<?php

namespace Illuminate\Tests\Integration\Foundation;

use Exception;
use Orchestra\Testbench\TestCase;
use Illuminate\Contracts\Debug\ExceptionHandler;

/**
 * @group integration
 */
class FoundationHelpersTest extends TestCase
{
    public function test_rescue()
    {
        $this->assertEquals(rescue(function () {
            throw new Exception;
        }, 'rescued!'), 'rescued!');

        $this->assertEquals(rescue(function () {
            throw new Exception;
        }, function () {
            return 'rescued!';
        }), 'rescued!');

        $this->assertEquals(rescue(function () {
            return 'no need to rescue';
        }, 'rescued!'), 'no need to rescue');

        $testClass = new class {
            public function test(int $a)
            {
                return $a;
            }
        };

        $this->assertEquals(rescue(function () use ($testClass) {
            $testClass->test([]);
        }, 'rescued!'), 'rescued!');
    }

    public function testMixReportsExceptionWhenAssetIsMissingFromManifest()
    {
        $handler = new FakeHandler;
        $this->app->instance(ExceptionHandler::class, $handler);
        $manifest = $this->makeManifest();

        mix('missing.js');

        $this->assertInstanceOf(Exception::class, $handler->reported);
        $this->assertSame('Unable to locate Mix file: /missing.js.', $handler->reported->getMessage());

        unlink($manifest);
    }

    public function testMixSilentlyFailsWhenAssetIsMissingFromManifestWhenNotInDebugMode()
    {
        $this->app['config']->set('app.debug', false);
        $manifest = $this->makeManifest();

        $path = mix('missing.js');

        $this->assertSame('/missing.js', $path);

        unlink($manifest);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Undefined index: /missing.js
     */
    public function testMixThrowsExceptionWhenAssetIsMissingFromManifestWhenInDebugMode()
    {
        $this->app['config']->set('app.debug', true);
        $manifest = $this->makeManifest();

        try {
            mix('missing.js');
        } catch (\Exception $e) {
            throw $e;
        } finally { // make sure we can cleanup the file
            unlink($manifest);
        }
    }

    protected function makeManifest($directory = '')
    {
        $this->app->singleton('path.public', function () {
            return __DIR__;
        });

        $path = public_path(str_finish($directory, '/').'mix-manifest.json');

        touch($path);

        // Laravel mix prints JSON pretty and with escaped
        // slashes, so we are doing that here for consistency.
        $content = json_encode(['/unversioned.css' => '/versioned.css'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($path, $content);

        return $path;
    }
}

class FakeHandler
{
    public $reported;

    public function report($exception)
    {
        $this->reported = $exception;
    }
}
