<?php
namespace Robtimus\Multipart;

use CurlHandle;
use InvalidArgumentException;
use stdClass;
use ValueError;

class MultipartFormDataTest extends MultipartTestBase
{
    public function testAddValueEmptyName(): void
    {
        $multipart = new MultipartFormData();

        try {
            $multipart->addValue('', 'value');

            $this->fail('Expected a ValueError');
        } catch (ValueError $e) {
            $this->assertEquals('$name must be non-empty', $e->getMessage());
        }
    }

    public function testAddFileEmptyName(): void
    {
        $multipart = new MultipartFormData();

        try {
            $multipart->addFile('', 'file.txt', 'Hello World', 'text/plain');

            $this->fail('Expected a ValueError');
        } catch (ValueError $e) {
            $this->assertEquals('$name must be non-empty', $e->getMessage());
        }
    }

    public function testAddFileEmptyFilename(): void
    {
        $multipart = new MultipartFormData();

        try {
            $multipart->addFile('name', '', 'Hello World', 'text/plain');

            $this->fail('Expected a ValueError');
        } catch (ValueError $e) {
            $this->assertEquals('$filename must be non-empty', $e->getMessage());
        }
    }

    public function testAddFileInvalidTypeOfContent(): void
    {
        $multipart = new MultipartFormData();

        try {
            // @phpstan-ignore argument.type
            $multipart->addFile('name', 'file.txt', 0, 'text/plain');

            $this->fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('$content is incorrectly typed', $e->getMessage());
        }
    }

    public function testAddFileEmptyContentType(): void
    {
        $multipart = new MultipartFormData();

        try {
            $multipart->addFile('name', 'file.txt', 'Hello World', '');

            $this->fail('Expected a ValueError');
        } catch (ValueError $e) {
            $this->assertEquals('$contentType must be non-empty', $e->getMessage());
        }
    }

    public function testReadStringsOnly(): void
    {
        $multipart = new MultipartFormData('test-boundary');
        $multipart->addValue('name1', 'value1');
        $multipart->addValue('name2', 'value2');
        $multipart->finish();

        $expected = <<<'EOS'
--test-boundary
Content-Disposition: form-data; name="name1"

value1
--test-boundary
Content-Disposition: form-data; name="name2"

value2
--test-boundary--

EOS;
        $expected = str_replace("\r\n", "\n", $expected);
        $expected = str_replace("\n", "\r\n", $expected);

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);
        $this->assertEquals('multipart/form-data; boundary=test-boundary', $multipart->getContentType());
        $this->assertEquals(strlen($expected), $multipart->getContentLength());
    }

    public function testUploadStringsOnly(): void
    {
        $this->_skipUploadIfNeeded();

        $multipart = new MultipartFormData();
        $multipart->addValue('name1', 'value1');
        $multipart->addValue('name2', 'value2');
        $multipart->finish();

        $ch = $this->_setupCurl($multipart);

        $responseString = curl_exec($ch);
        $info = curl_getinfo($ch);

        $this->assertEquals(200, $info['http_code']);
        $this->assertEquals('application/json', $info['content_type']);

        // @phpstan-ignore argument.type
        $response = json_decode($responseString, false);

        // @phpstan-ignore argument.type
        $this->assertObjectHasProperty('files', $response);
        // @phpstan-ignore property.nonObject
        $this->assertEquals(new stdClass(), $response->files);

        // @phpstan-ignore argument.type
        $this->assertObjectHasProperty('form', $response);

        // @phpstan-ignore property.nonObject
        $this->assertObjectHasProperty('name1', $response->form);
        // @phpstan-ignore property.nonObject
        $this->assertEquals('value1', $response->form->name1);

        // @phpstan-ignore property.nonObject
        $this->assertObjectHasProperty('name2', $response->form);
        // @phpstan-ignore property.nonObject
        $this->assertEquals('value2', $response->form->name2);
    }

    public function testReadSingleFileOnly(): void
    {
        $multipart = new MultipartFormData('test-boundary');
        $multipart->addFile('file', 'file.txt', 'Hello World', 'text/plain');
        $multipart->finish();

        $expected = <<<'EOS'
--test-boundary
Content-Disposition: form-data; name="file"; filename="file.txt"
Content-Type: text/plain

Hello World
--test-boundary--

EOS;
        $expected = str_replace("\r\n", "\n", $expected);
        $expected = str_replace("\n", "\r\n", $expected);

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);
        $this->assertEquals('multipart/form-data; boundary=test-boundary', $multipart->getContentType());
        $this->assertEquals(strlen($expected), $multipart->getContentLength());
    }

    public function testUploadSingleFileOnly(): void
    {
        $this->_skipUploadIfNeeded();

        $multipart = new MultipartFormData();
        $multipart->addFile('file', 'file.txt', 'Hello World', 'text/plain');
        $multipart->finish();

        $ch = $this->_setupCurl($multipart);

        $responseString = curl_exec($ch);
        $info = curl_getinfo($ch);

        $this->assertEquals(200, $info['http_code']);
        $this->assertEquals('application/json', $info['content_type']);

        // @phpstan-ignore argument.type
        $response = json_decode($responseString, false);

        // @phpstan-ignore argument.type
        $this->assertObjectHasProperty('files', $response);

        // @phpstan-ignore property.nonObject
        $this->assertObjectHasProperty('file', $response->files);
        // @phpstan-ignore property.nonObject
        $this->assertEquals('Hello World', $response->files->file);

        // @phpstan-ignore argument.type
        $this->assertObjectHasProperty('form', $response);
        // @phpstan-ignore property.nonObject
        $this->assertEquals(new stdClass(), $response->form);
    }

    public function testReadMultipleFilesOnly(): void
    {
        $multipart = new MultipartFormData('test-boundary');
        $multipart->addFile('file1', 'file.txt', 'Hello World', 'text/plain');
        $multipart->addFile('file2', 'file.html', "<html>\nHello World\n</html>", 'text/html');
        $multipart->finish();

        $expected = <<<'EOS'
--test-boundary
Content-Disposition: form-data; name="file1"; filename="file.txt"
Content-Type: text/plain

Hello World
--test-boundary
Content-Disposition: form-data; name="file2"; filename="file.html"
Content-Type: text/html

<html>Hello World</html>
--test-boundary--

EOS;
        $expected = str_replace("\r\n", "\n", $expected);
        $expected = str_replace("\n", "\r\n", $expected);
        $expected = str_replace('<html>Hello World</html>', "<html>\nHello World\n</html>", $expected);

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);
        $this->assertEquals('multipart/form-data; boundary=test-boundary', $multipart->getContentType());
        $this->assertEquals(strlen($expected), $multipart->getContentLength());
    }

    public function testUploadMultipleFilesOnly(): void
    {
        $this->_skipUploadIfNeeded();

        $multipart = new MultipartFormData();
        $multipart->addFile('file1', 'file.txt', 'Hello World', 'text/plain');
        $multipart->addFile('file2', 'file.html', "<html>\nHello World\n</html>", 'text/html');
        $multipart->finish();

        $ch = $this->_setupCurl($multipart);

        $responseString = curl_exec($ch);
        $info = curl_getinfo($ch);

        $this->assertEquals(200, $info['http_code']);
        $this->assertEquals('application/json', $info['content_type']);

        // @phpstan-ignore argument.type
        $response = json_decode($responseString, false);

        // @phpstan-ignore argument.type
        $this->assertObjectHasProperty('files', $response);

        // @phpstan-ignore property.nonObject
        $this->assertObjectHasProperty('file1', $response->files);
        // @phpstan-ignore property.nonObject
        $this->assertEquals('Hello World', $response->files->file1);

        // @phpstan-ignore property.nonObject
        $this->assertObjectHasProperty('file2', $response->files);
        // @phpstan-ignore property.nonObject
        $this->assertEquals("<html>\nHello World\n</html>", $response->files->file2);

        // @phpstan-ignore argument.type
        $this->assertObjectHasProperty('form', $response);
        // @phpstan-ignore property.nonObject
        $this->assertEquals(new stdClass(), $response->form);
    }

    public function testReadMixed(): void
    {
        $multipart = new MultipartFormData('test-boundary');
        $multipart->addValue('name1', 'value1');
        $multipart->addValue('name2', 'value2');
        $multipart->addFile('file1', 'file.txt', 'Hello World', 'text/plain');
        $multipart->addFile('file2', 'file.html', "<html>\nHello World\n</html>", 'text/html');
        $multipart->finish();

        $expected = <<<'EOS'
--test-boundary
Content-Disposition: form-data; name="name1"

value1
--test-boundary
Content-Disposition: form-data; name="name2"

value2
--test-boundary
Content-Disposition: form-data; name="file1"; filename="file.txt"
Content-Type: text/plain

Hello World
--test-boundary
Content-Disposition: form-data; name="file2"; filename="file.html"
Content-Type: text/html

<html>Hello World</html>
--test-boundary--

EOS;
        $expected = str_replace("\r\n", "\n", $expected);
        $expected = str_replace("\n", "\r\n", $expected);
        $expected = str_replace('<html>Hello World</html>', "<html>\nHello World\n</html>", $expected);

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);
        $this->assertEquals('multipart/form-data; boundary=test-boundary', $multipart->getContentType());
        $this->assertEquals(strlen($expected), $multipart->getContentLength());
    }

    public function testUploadMixed(): void
    {
        $this->_skipUploadIfNeeded();

        $multipart = new MultipartFormData();
        $multipart->addValue('name1', 'value1');
        $multipart->addValue('name2', 'value2');
        $multipart->addFile('file1', 'file.txt', 'Hello World', 'text/plain');
        $multipart->addFile('file2', 'file.html', "<html>\nHello World\n</html>", 'text/html');
        $multipart->finish();

        $ch = $this->_setupCurl($multipart);

        $responseString = curl_exec($ch);
        $info = curl_getinfo($ch);

        $this->assertEquals(200, $info['http_code']);
        $this->assertEquals('application/json', $info['content_type']);

        // @phpstan-ignore argument.type
        $response = json_decode($responseString, false);

        // @phpstan-ignore argument.type
        $this->assertObjectHasProperty('files', $response);

        // @phpstan-ignore property.nonObject
        $this->assertObjectHasProperty('file1', $response->files);
        // @phpstan-ignore property.nonObject
        $this->assertEquals('Hello World', $response->files->file1);

        // @phpstan-ignore property.nonObject
        $this->assertObjectHasProperty('file2', $response->files);
        // @phpstan-ignore property.nonObject
        $this->assertEquals("<html>\nHello World\n</html>", $response->files->file2);

        // @phpstan-ignore argument.type
        $this->assertObjectHasProperty('form', $response);

        // @phpstan-ignore property.nonObject
        $this->assertObjectHasProperty('name1', $response->form);
        // @phpstan-ignore property.nonObject
        $this->assertEquals('value1', $response->form->name1);

        // @phpstan-ignore property.nonObject
        $this->assertObjectHasProperty('name2', $response->form);
        // @phpstan-ignore property.nonObject
        $this->assertEquals('value2', $response->form->name2);

        // @phpstan-ignore property.nonObject
        $this->assertObjectNotHasProperty('file1', $response->form);

        // @phpstan-ignore property.nonObject
        $this->assertObjectNotHasProperty('file2', $response->form);
    }

    public function testReadMixedWithDuplicateParameterNames(): void
    {
        $multipart = new MultipartFormData('test-boundary');
        $multipart->addValue('name', 'value1');
        $multipart->addFile('file', 'file.txt', 'Hello World', 'text/plain');
        $multipart->addValue('name', 'value2');
        $multipart->addFile('file', 'file.html', "<html>\nHello World\n</html>", 'text/html');
        $multipart->finish();

        $expected = <<<'EOS'
--test-boundary
Content-Disposition: form-data; name="name"

value1
--test-boundary
Content-Disposition: form-data; name="file"; filename="file.txt"
Content-Type: text/plain

Hello World
--test-boundary
Content-Disposition: form-data; name="name"

value2
--test-boundary
Content-Disposition: form-data; name="file"; filename="file.html"
Content-Type: text/html

<html>Hello World</html>
--test-boundary--

EOS;
        $expected = str_replace("\r\n", "\n", $expected);
        $expected = str_replace("\n", "\r\n", $expected);
        $expected = str_replace('<html>Hello World</html>', "<html>\nHello World\n</html>", $expected);

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);
        $this->assertEquals('multipart/form-data; boundary=test-boundary', $multipart->getContentType());
        $this->assertEquals(strlen($expected), $multipart->getContentLength());
    }

    public function testUploadMixedWithDuplicateParameterNames(): void
    {
        $this->_skipUploadIfNeeded();

        $multipart = new MultipartFormData();
        $multipart->addValue('name', 'value1');
        $multipart->addFile('file', 'file.txt', 'Hello World', 'text/plain');
        $multipart->addValue('name', 'value2');
        $multipart->addFile('file', 'file.html', "<html>\nHello World\n</html>", 'text/html');
        $multipart->finish();

        $ch = $this->_setupCurl($multipart);

        $responseString = curl_exec($ch);
        $info = curl_getinfo($ch);

        $this->assertEquals(200, $info['http_code']);
        $this->assertEquals('application/json', $info['content_type']);

        // @phpstan-ignore argument.type
        $response = json_decode($responseString, false);

        // @phpstan-ignore argument.type
        $this->assertObjectHasProperty('files', $response);

        // @phpstan-ignore property.nonObject
        $this->assertObjectHasProperty('file', $response->files);
        // httpbin.org ignores subsequent files with the same name...
        // @phpstan-ignore property.nonObject
        $this->assertEquals('Hello World', $response->files->file);

        // @phpstan-ignore argument.type
        $this->assertObjectHasProperty('form', $response);

        // @phpstan-ignore property.nonObject
        $this->assertObjectHasProperty('name', $response->form);
        // @phpstan-ignore property.nonObject
        $this->assertEquals(['value1', 'value2'], $response->form->name);
    }

    private function _skipUploadIfNeeded(): void
    {
        $skipUpload = $this->getConfigValue('http.upload.skip', false);
        if ($skipUpload === true) {
            $this->markTestSkipped('HTTP uploads skipped');
        }
    }

    private function _setupCurl(MultipartFormData $multipart): CurlHandle
    {
        $httpBinUrl = $this->getStringConfigValue('http.upload.httpBinUrl', false) ?: 'http://httpbin.org';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, $httpBinUrl . '/post');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_READFUNCTION, array($multipart, 'curlRead'));

        $headers = ['Content-Type: ' . $multipart->getContentType()];

        $contentLength = $multipart->getContentLength();
        if ($contentLength >= 0) {
            $headers[] = 'Content-Length: ' .  $contentLength;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        return $ch;
    }
}
