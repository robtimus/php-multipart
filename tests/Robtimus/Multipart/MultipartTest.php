<?php
namespace Robtimus\Multipart;

class MultipartTest extends MultipartTestBase {

    public function testAddWhenFinished() {
        $multipart = new TestMultipart();
        $multipart->finish();

        try {
            $multipart->add('Hello World');

            $this->fail('Expected a LogicException');
        } catch (\LogicException $e) {
            // expected
        }
    }

    public function testReadBeforeFinish() {
        $multipart = new TestMultipart();
        $multipart->add('Hello World');

        try {
            $multipart->read(20);

            $this->fail('Expected a LogicException');
        } catch (\LogicException $e) {
            // expected
        }
    }

    public function testBufferBeforeFinish() {
        $multipart = new TestMultipart();
        $multipart->add('Hello World');

        try {
            $multipart->buffer();

            $this->fail('Expected a LogicException');
        } catch (\LogicException $e) {
            // expected
        }
    }

    public function testReadEmpty() {
        $multipart = new TestMultipart('test-boundary');
        $multipart->finish();
        $expected = "--test-boundary--\r\n";

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);
    }

    public function testReadOnlyStrings() {
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

    public function testReadOnlyFileResources() {
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

    public function testReadOnlyUrlResources() {
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

    public function testReadOnlyCallables() {
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

    public function testReadMixed() {
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

    public function testReadClosedResource() {
        $multipart = new TestMultipart();
        $file = fopen(__FILE__, 'rb');
        $multipart->add($file);
        $multipart->finish();

        fclose($file);

        try {
            $multipart->read(20);

            $this->fail('Expected an UnexpectedValueException');
        } catch (\UnexpectedValueException $e) {
            // expected
        }
    }

    public function testContentLength() {
        $multipart = new TestMultipart('');

        $this->assertEquals(0, $multipart->getContentLength());
    }

    public function testContentLengthOnlyStrings() {
        $multipart = new TestMultipart();
        $expected = 0;
        for ($i = 0; $i < 100; $i++) {
            $s = "This is test line $i\n";
            $multipart->add($s);
            $expected += strlen($s);
        }

        $this->assertEquals($expected, $multipart->getContentLength());
    }

    public function testContentLengthOnlyNonStringsNoContentLengthGiven() {
        $multipart = new TestMultipart();
        $resource = fopen(__FILE__, 'rb');
        $multipart->add($resource);

        $this->assertEquals(-1, $multipart->getContentLength());
    }

    public function testContentLengthOnlyNonStringsContentLengthGiven() {
        $multipart = new TestMultipart();
        $resource = fopen(__FILE__, 'rb');
        $length = filesize(__FILE__);
        $multipart->add($resource, $length);

        $this->assertEquals($length, $multipart->getContentLength());
    }

    public function testContentLengthMixedContentLengthsGiven() {
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

    public function testContentLengthMixedNoContentLengthsGiven() {
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

    public function testBuffer() {
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

        $result = $multipart->buffer();

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

    public function testToString() {
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

        $result = (string) $multipart;

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

    public function testToStringNoFinish() {
        $multipart = new TestMultipart();
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

        $result = (string) $multipart;

        fclose($resource);
        fclose($resource2);

        $this->assertEquals($expected, $result);

        $result = (string) $multipart;

        $this->assertEquals($expected, $result);
    }
}

class TestMultipart extends Multipart {

    public function __construct($boundary = '') {
        parent::__construct($boundary, 'multipart/test');
    }

    public function add($content, $length = -1) {
        $this->addContent($content, $length);
    }
}
