<?php
namespace Robtimus\Multipart;

use InvalidArgumentException;
use LogicException;
use UnexpectedValueException;

class MultipartTest extends MultipartTestBase
{
    public function testConstructWithEmptyContentType(): void
    {
        try {
            new TestMultipart('', '');

            $this->fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('$contentType must be non-empty', $e->getMessage());
        }
    }

    public function testAddWhenFinished(): void
    {
        $multipart = new TestMultipart();
        $multipart->finish();

        try {
            $multipart->add('Hello World');

            $this->fail('Expected a LogicException');
        } catch (LogicException $e) {
            $this->assertEquals('can\'t add to a finished multipart object', $e->getMessage());
        }
    }

    public function testAddInvalidType(): void
    {
        $multipart = new TestMultipart();

        try {
            // @phpstan-ignore argument.type
            $multipart->add(0);

            $this->fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('non-supported part type: integer', $e->getMessage());
        }
    }

    public function testReadBeforeFinish(): void
    {
        $multipart = new TestMultipart();
        $multipart->add('Hello World');

        try {
            $multipart->read(20);

            $this->fail('Expected a LogicException');
        } catch (LogicException $e) {
            $this->assertEquals('can\'t read from a non-finished multipart object', $e->getMessage());
        }
    }

    public function testBufferBeforeFinish(): void
    {
        $multipart = new TestMultipart();
        $multipart->add('Hello World');

        try {
            $multipart->buffer();

            $this->fail('Expected a LogicException');
        } catch (LogicException $e) {
            $this->assertEquals('can\'t buffer a non-finished multipart object', $e->getMessage());
        }
    }

    public function testReadNonPositiveLength(): void
    {
        $multipart = new TestMultipart('test-boundary');
        $multipart->add('Hello World');
        $multipart->finish();

        $this->assertEquals('', $multipart->read(0));
        $this->assertEquals('', $multipart->read(-1));

        $expected = "Hello World--test-boundary--\r\n";

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);
    }

    public function testReadEmpty(): void
    {
        $multipart = new TestMultipart('test-boundary');
        $multipart->finish();
        $expected = "--test-boundary--\r\n";

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);
    }

    public function testReadOnlyStrings(): void
    {
        $multipart = new TestMultipart('test-boundary');
        $expected = '';
        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
            $expected .= $s;
        }
        $multipart->finish();
        $expected .= "--test-boundary--\r\n";

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);
    }

    public function testReadOnlyFileResources(): void
    {
        $multipart = new TestMultipart('test-boundary');
        $resource = fopen(__FILE__, 'rb');
        // @phpstan-ignore argument.type
        $multipart->add($resource);
        $expected = file_get_contents(__FILE__);
        $multipart->finish();
        $expected .= "--test-boundary--\r\n";

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);

        // @phpstan-ignore argument.type
        fclose($resource);
    }

    public function testReadOnlyUrlResources(): void
    {
        $url = 'http://www.example.org/';
        $multipart = new TestMultipart('test-boundary');
        $resource = fopen($url, 'rb');
        // @phpstan-ignore argument.type
        $multipart->add($resource);
        $expected = file_get_contents($url);
        $multipart->finish();
        $expected .= "--test-boundary--\r\n";

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);

        // @phpstan-ignore argument.type
        fclose($resource);
    }

    public function testReadOnlyCallables(): void
    {
        $resource = fopen(__FILE__, 'rb');
        $callable = function ($length) use ($resource) {
            // @phpstan-ignore argument.type
            return fread($resource, $length);
        };

        $multipart = new TestMultipart('test-boundary');
        // @phpstan-ignore argument.type
        $multipart->add($callable);
        $expected = file_get_contents(__FILE__);
        $multipart->finish();
        $expected .= "--test-boundary--\r\n";

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);

        // @phpstan-ignore argument.type
        fclose($resource);
    }

    public function testReadMixed(): void
    {
        $multipart = new TestMultipart('test-boundary');
        $expected = '';
        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
            $expected .= $s;
        }

        $resource = fopen(__FILE__, 'rb');
        // @phpstan-ignore argument.type
        $multipart->add($resource);
        $expected .= file_get_contents(__FILE__);

        $resource2 = fopen(__FILE__, 'rb');
        $callable = function ($length) use ($resource2) {
            // @phpstan-ignore argument.type
            return fread($resource2, $length);
        };

        // @phpstan-ignore argument.type
        $multipart->add($callable);
        $expected .= file_get_contents(__FILE__);

        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
            $expected .= $s;
        }
        $multipart->finish();
        $expected .= "--test-boundary--\r\n";

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);

        // @phpstan-ignore argument.type
        fclose($resource);
        // @phpstan-ignore argument.type
        fclose($resource2);
    }

    public function testReadClosedResource(): void
    {
        $multipart = new TestMultipart();
        $file = fopen(__FILE__, 'rb');
        // @phpstan-ignore argument.type
        $multipart->add($file);
        $multipart->finish();

        // @phpstan-ignore argument.type
        fclose($file);

        try {
            $multipart->read(20);

            $this->fail('Expected an UnexpectedValueException');
        } catch (UnexpectedValueException $e) {
            $this->assertEquals('non-supported part type: ' . gettype($file), $e->getMessage());
        }
    }

    public function testContentTypeWithGeneratedBoundary(): void
    {
        $multipart = new TestMultipart();

        $uuidRegExp = '[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{8}';
        $expected = '/multipart\/test; boundary=' . $uuidRegExp . '/';
        $this->assertMatchesRegularExpression($expected, $multipart->getContentType());

        $boundary = $multipart->getBoundary();
        $this->assertEquals('multipart/test; boundary=' . $boundary, $multipart->getContentType());
    }

    public function testContentTypeWithCustomBoundary(): void
    {
        $multipart = new TestMultipart('test-boundary');

        $this->assertEquals('multipart/test; boundary=test-boundary', $multipart->getContentType());
        $this->assertEquals('test-boundary', $multipart->getBoundary());
    }

    public function testContentLength(): void
    {
        $multipart = new TestMultipart('');

        $this->assertEquals(0, $multipart->getContentLength());
    }

    public function testContentLengthOnlyStrings(): void
    {
        $multipart = new TestMultipart();
        $expected = 0;
        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
            $expected += strlen($s);
        }

        $this->assertEquals($expected, $multipart->getContentLength());
    }

    public function testContentLengthOnlyNonStringsNoContentLengthGiven(): void
    {
        $multipart = new TestMultipart();
        $resource = fopen(__FILE__, 'rb');
        // @phpstan-ignore argument.type
        $multipart->add($resource);

        $this->assertEquals(-1, $multipart->getContentLength());
    }

    public function testContentLengthOnlyNonStringsContentLengthGiven(): void
    {
        $multipart = new TestMultipart();
        $resource = fopen(__FILE__, 'rb');
        $length = filesize(__FILE__);
        // @phpstan-ignore argument.type, argument.type
        $multipart->add($resource, $length);

        $this->assertEquals($length, $multipart->getContentLength());
    }

    public function testContentLengthMixedContentLengthsGiven(): void
    {
        $multipart = new TestMultipart();
        $expected = 0;
        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
            $expected += strlen($s);
        }

        $resource = fopen(__FILE__, 'rb');
        $length = filesize(__FILE__);
        // @phpstan-ignore argument.type, argument.type
        $multipart->add($resource, $length);
        $expected += $length;

        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
            $expected += strlen($s);
        }

        $this->assertEquals($expected, $multipart->getContentLength());

        // @phpstan-ignore argument.type
        fclose($resource);
    }

    public function testContentLengthMixedNoContentLengthsGiven(): void
    {
        $multipart = new TestMultipart();
        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
        }

        $resource = fopen(__FILE__, 'rb');
        // @phpstan-ignore argument.type
        $multipart->add($resource);

        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
        }

        $this->assertEquals(-1, $multipart->getContentLength());

        // @phpstan-ignore argument.type
        fclose($resource);
    }

    public function testBufferNonPositiveLength(): void
    {
        $multipart = new TestMultipart('test-boundary');
        $multipart->add('Hello World');
        $multipart->finish();

        try {
            $multipart->buffer(0);

            $this->fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('$bufferSize <= 0', $e->getMessage());
        }

        try {
            $multipart->buffer(-1);

            $this->fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('$bufferSize <= 0', $e->getMessage());
        }
    }

    public function testBuffer(): void
    {
        $multipart = new TestMultipart('test-boundary');
        $expected = '';
        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
            $expected .= $s;
            $this->assertEquals($i === 0, $multipart->isBuffered());
            $this->assertFalse($multipart->isFinished());
        }

        $resource = fopen(__FILE__, 'rb');
        // @phpstan-ignore argument.type
        $multipart->add($resource);
        $expected .= file_get_contents(__FILE__);
        $this->assertFalse($multipart->isBuffered());
        $this->assertFalse($multipart->isFinished());

        $resource2 = fopen(__FILE__, 'rb');
        $callable = function ($length) use ($resource2) {
            // @phpstan-ignore argument.type
            return fread($resource2, $length);
        };

        // @phpstan-ignore argument.type
        $multipart->add($callable);
        $expected .= file_get_contents(__FILE__);
        $this->assertFalse($multipart->isBuffered());
        $this->assertFalse($multipart->isFinished());

        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
            $expected .= $s;
            $this->assertFalse($multipart->isBuffered());
            $this->assertFalse($multipart->isFinished());
        }

        $multipart->finish();
        $expected .= "--test-boundary--\r\n";
        $this->assertFalse($multipart->isBuffered());
        $this->assertTrue($multipart->isFinished());

        $result = $multipart->buffer();
        $this->assertTrue($multipart->isBuffered());

        // @phpstan-ignore argument.type
        fclose($resource);
        // @phpstan-ignore argument.type
        fclose($resource2);

        $this->assertEquals($expected, $result);
        $this->assertEquals(strlen($expected), $multipart->getContentLength());

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);

        $result = $multipart->buffer();

        $this->assertEquals($expected, $result);
        $this->assertEquals(strlen($expected), $multipart->getContentLength());

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);
    }

    public function testToString(): void
    {
        $multipart = new TestMultipart('test-boundary');
        $expected = '';
        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
            $expected .= $s;
            $this->assertEquals($i === 0, $multipart->isBuffered());
            $this->assertFalse($multipart->isFinished());
        }

        $resource = fopen(__FILE__, 'rb');
        // @phpstan-ignore argument.type
        $multipart->add($resource);
        $expected .= file_get_contents(__FILE__);
        $this->assertFalse($multipart->isBuffered());
        $this->assertFalse($multipart->isFinished());

        $resource2 = fopen(__FILE__, 'rb');
        $callable = function ($length) use ($resource2) {
            // @phpstan-ignore argument.type
            return fread($resource2, $length);
        };

        // @phpstan-ignore argument.type
        $multipart->add($callable);
        $expected .= file_get_contents(__FILE__);
        $this->assertFalse($multipart->isBuffered());
        $this->assertFalse($multipart->isFinished());

        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
            $expected .= $s;
            $this->assertFalse($multipart->isBuffered());
            $this->assertFalse($multipart->isFinished());
        }

        $multipart->finish();
        $expected .= "--test-boundary--\r\n";
        $this->assertFalse($multipart->isBuffered());
        $this->assertTrue($multipart->isFinished());

        $result = (string) $multipart;
        $this->assertTrue($multipart->isBuffered());

        // @phpstan-ignore argument.type
        fclose($resource);
        // @phpstan-ignore argument.type
        fclose($resource2);

        $this->assertEquals($expected, $result);
        $this->assertEquals(strlen($expected), $multipart->getContentLength());

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);

        $result = (string) $multipart;

        $this->assertEquals($expected, $result);
        $this->assertEquals(strlen($expected), $multipart->getContentLength());

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);
    }

    public function testToStringNoFinish(): void
    {
        $multipart = new TestMultipart();
        $expected = '';
        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
            $expected .= $s;
            $this->assertEquals($i === 0, $multipart->isBuffered());
            $this->assertFalse($multipart->isFinished());
        }

        $resource = fopen(__FILE__, 'rb');
        // @phpstan-ignore argument.type
        $multipart->add($resource);
        $expected .= file_get_contents(__FILE__);
        $this->assertFalse($multipart->isBuffered());
        $this->assertFalse($multipart->isFinished());

        $resource2 = fopen(__FILE__, 'rb');
        $callable = function ($length) use ($resource2) {
            // @phpstan-ignore argument.type
            return fread($resource2, $length);
        };

        // @phpstan-ignore argument.type
        $multipart->add($callable);
        $expected .= file_get_contents(__FILE__);
        $this->assertFalse($multipart->isBuffered());
        $this->assertFalse($multipart->isFinished());

        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
            $expected .= $s;
            $this->assertFalse($multipart->isBuffered());
            $this->assertFalse($multipart->isFinished());
        }

        $result = (string) $multipart;
        $this->assertTrue($multipart->isBuffered());
        $this->assertFalse($multipart->isFinished());

        // @phpstan-ignore argument.type
        fclose($resource);
        // @phpstan-ignore argument.type
        fclose($resource2);

        $this->assertEquals($expected, $result);

        $result = (string) $multipart;

        $this->assertEquals($expected, $result);
    }
}

class TestMultipart extends Multipart
{
    /**
     * @param string $boundary
     * @param string $contentType
     *
     * @throws InvalidArgumentException If the given content type is empty.
     */
    public function __construct(string $boundary = '', string $contentType = 'multipart/test')
    {
        parent::__construct($boundary, $contentType);
    }

    /**
     * @param string|resource|callable(int):string $content
     */
    public function add(mixed $content, int $length = -1): void
    {
        $this->addContent($content, $length);
    }
}
