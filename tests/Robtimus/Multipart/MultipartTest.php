<?php
namespace Robtimus\Multipart;

class MultipartTest extends MultipartTestBase
{
    public function testConstructWithNonStringBoundary()
    {
        try {
            new TestMultipart(0);

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$boundary is incorrectly typed', $e->getMessage());
        }
    }

    public function testConstructWithNonStringContentType()
    {
        try {
            new TestMultipart('', 0);

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$contentType is incorrectly typed', $e->getMessage());
        }
    }

    public function testConstructWithEmptyContentType()
    {
        try {
            new TestMultipart('', '');

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$contentType must be non-empty', $e->getMessage());
        }
    }

    public function testAddWhenFinished()
    {
        $multipart = new TestMultipart();
        $multipart->finish();

        try {
            $multipart->add('Hello World');

            $this->fail('Expected a LogicException');
        } catch (\LogicException $e) {
            $this->assertEquals('can\'t add to a finished multipart object', $e->getMessage());
        }
    }

    public function testAddInvalidType()
    {
        $multipart = new TestMultipart();

        try {
            $multipart->add(0);

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('non-supported part type: integer', $e->getMessage());
        }
    }

    public function testReadBeforeFinish()
    {
        $multipart = new TestMultipart();
        $multipart->add('Hello World');

        try {
            $multipart->read(20);

            $this->fail('Expected a LogicException');
        } catch (\LogicException $e) {
            $this->assertEquals('can\'t read from a non-finished multipart object', $e->getMessage());
        }
    }

    public function testBufferBeforeFinish()
    {
        $multipart = new TestMultipart();
        $multipart->add('Hello World');

        try {
            $multipart->buffer();

            $this->fail('Expected a LogicException');
        } catch (\LogicException $e) {
            $this->assertEquals('can\'t buffer a non-finished multipart object', $e->getMessage());
        }
    }

    public function testReadNonIntegerLength()
    {
        $multipart = new TestMultipart('test-boundary');
        $multipart->add('Hello World');
        $multipart->finish();

        try {
            $multipart->read('');

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$length is incorrectly typed', $e->getMessage());
        }
    }

    public function testReadNonPositiveLength()
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

    public function testReadEmpty()
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

    public function testReadOnlyStrings()
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

    public function testReadOnlyFileResources()
    {
        $multipart = new TestMultipart('test-boundary');
        $resource = fopen(__FILE__, 'rb');
        $multipart->add($resource);
        $expected = file_get_contents(__FILE__);
        $multipart->finish();
        $expected .= "--test-boundary--\r\n";

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);

        fclose($resource);
    }

    public function testReadOnlyUrlResources()
    {
        $url = 'http://www.example.org/';
        $multipart = new TestMultipart('test-boundary');
        $resource = fopen($url, 'rb');
        $multipart->add($resource);
        $expected = file_get_contents($url);
        $multipart->finish();
        $expected .= "--test-boundary--\r\n";

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);

        fclose($resource);
    }

    public function testReadOnlyCallables()
    {
        $resource = fopen(__FILE__, 'rb');
        $callable = function ($length) use ($resource) {
            return fread($resource, $length);
        };

        $multipart = new TestMultipart('test-boundary');
        $multipart->add($callable);
        $expected = file_get_contents(__FILE__);
        $multipart->finish();
        $expected .= "--test-boundary--\r\n";

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);

        fclose($resource);
    }

    public function testReadMixed()
    {
        $multipart = new TestMultipart('test-boundary');
        $expected = '';
        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
            $expected .= $s;
        }

        $resource = fopen(__FILE__, 'rb');
        $multipart->add($resource);
        $expected .= file_get_contents(__FILE__);

        $resource2 = fopen(__FILE__, 'rb');
        $callable = function ($length) use ($resource2) {
            return fread($resource2, $length);
        };

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

        fclose($resource);
        fclose($resource2);
    }

    public function testReadClosedResource()
    {
        $multipart = new TestMultipart();
        $file = fopen(__FILE__, 'rb');
        $multipart->add($file);
        $multipart->finish();

        fclose($file);

        try {
            $multipart->read(20);

            $this->fail('Expected an UnexpectedValueException');
        } catch (\UnexpectedValueException $e) {
            $this->assertEquals('non-supported part type: ' . gettype($file), $e->getMessage());
        }
    }

    public function testContentTypeWithGeneratedBoundary()
    {
        $multipart = new TestMultipart();

        $uuidRegExp = '[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{8}';
        $expected = '/multipart\/test; boundary=' . $uuidRegExp . '/';
        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression($expected, $multipart->getContentType());
        } else {
            $this->assertRegExp($expected, $multipart->getContentType());
        }

        $boundary = $multipart->getBoundary();
        $this->assertEquals('multipart/test; boundary=' . $boundary, $multipart->getContentType());
    }

    public function testContentTypeWithCustomBoundary()
    {
        $multipart = new TestMultipart('test-boundary');

        $this->assertEquals('multipart/test; boundary=test-boundary', $multipart->getContentType());
        $this->assertEquals('test-boundary', $multipart->getBoundary());
    }

    public function testContentLength()
    {
        $multipart = new TestMultipart('');

        $this->assertEquals(0, $multipart->getContentLength());
    }

    public function testContentLengthOnlyStrings()
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

    public function testContentLengthOnlyNonStringsNoContentLengthGiven()
    {
        $multipart = new TestMultipart();
        $resource = fopen(__FILE__, 'rb');
        $multipart->add($resource);

        $this->assertEquals(-1, $multipart->getContentLength());
    }

    public function testContentLengthOnlyNonStringsContentLengthGiven()
    {
        $multipart = new TestMultipart();
        $resource = fopen(__FILE__, 'rb');
        $length = filesize(__FILE__);
        $multipart->add($resource, $length);

        $this->assertEquals($length, $multipart->getContentLength());
    }

    public function testContentLengthMixedContentLengthsGiven()
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
        $multipart->add($resource, $length);
        $expected += $length;

        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
            $expected += strlen($s);
        }

        $this->assertEquals($expected, $multipart->getContentLength());

        fclose($resource);
    }

    public function testContentLengthMixedNoContentLengthsGiven()
    {
        $multipart = new TestMultipart();
        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
        }

        $resource = fopen(__FILE__, 'rb');
        $multipart->add($resource);

        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
        }

        $this->assertEquals(-1, $multipart->getContentLength());

        fclose($resource);
    }

    public function testBufferNonIntegerLength()
    {
        $multipart = new TestMultipart('test-boundary');
        $multipart->add('Hello World');
        $multipart->finish();

        try {
            $multipart->buffer('');

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$bufferSize is incorrectly typed', $e->getMessage());
        }
    }

    public function testBufferNonPositiveLength()
    {
        $multipart = new TestMultipart('test-boundary');
        $multipart->add('Hello World');
        $multipart->finish();

        try {
            $multipart->buffer(0);

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$bufferSize <= 0', $e->getMessage());
        }

        try {
            $multipart->buffer(-1);

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$bufferSize <= 0', $e->getMessage());
        }
    }

    public function testBuffer()
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
        $multipart->add($resource);
        $expected .= file_get_contents(__FILE__);
        $this->assertFalse($multipart->isBuffered());
        $this->assertFalse($multipart->isFinished());

        $resource2 = fopen(__FILE__, 'rb');
        $callable = function ($length) use ($resource2) {
            return fread($resource2, $length);
        };

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

        fclose($resource);
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

    public function testToString()
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
        $multipart->add($resource);
        $expected .= file_get_contents(__FILE__);
        $this->assertFalse($multipart->isBuffered());
        $this->assertFalse($multipart->isFinished());

        $resource2 = fopen(__FILE__, 'rb');
        $callable = function ($length) use ($resource2) {
            return fread($resource2, $length);
        };

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

        fclose($resource);
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

    public function testToStringNoFinish()
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
        $multipart->add($resource);
        $expected .= file_get_contents(__FILE__);
        $this->assertFalse($multipart->isBuffered());
        $this->assertFalse($multipart->isFinished());

        $resource2 = fopen(__FILE__, 'rb');
        $callable = function ($length) use ($resource2) {
            return fread($resource2, $length);
        };

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

        fclose($resource);
        fclose($resource2);

        $this->assertEquals($expected, $result);

        $result = (string) $multipart;

        $this->assertEquals($expected, $result);
    }
}

class TestMultipart extends Multipart
{
    /**
     * @param string|int $boundary
     * @param string|int $contentType
     */
    public function __construct($boundary = '', $contentType = 'multipart/test')
    {
        parent::__construct($boundary, $contentType);
    }

    public function add($content, $length = -1)
    {
        $this->addContent($content, $length);
    }
}
