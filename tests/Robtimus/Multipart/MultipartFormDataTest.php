<?php
namespace Robtimus\Multipart;

class MultipartFormDataTest extends MultipartTestBase {

    public function testReadStringsOnly() {
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

    public function testUploadStringsOnly() {
        $multipart = new MultipartFormData();
        $multipart->addValue('name1', 'value1');
        $multipart->addValue('name2', 'value2');
        $multipart->finish();

        $ch = $this->setupCurl($multipart);

        $responseString = curl_exec($ch);
        $info = curl_getinfo($ch);

        $this->assertEquals(200, $info['http_code']);
        $this->assertEquals('application/json', $info['content_type']);

        $response = json_decode($responseString);

        $this->assertObjectHasAttribute('files', $response);
        $this->assertEquals(new \stdClass(), $response->files);

        $this->assertObjectHasAttribute('form', $response);

        $this->assertObjectHasAttribute('name1', $response->form);
        $this->assertEquals('value1', $response->form->name1);

        $this->assertObjectHasAttribute('name2', $response->form);
        $this->assertEquals('value2', $response->form->name2);
    }

    public function testReadSingleFileOnly() {
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

    public function testUploadSingleFileOnly() {
        $multipart = new MultipartFormData();
        $multipart->addFile('file', 'file.txt', 'Hello World', 'text/plain');
        $multipart->finish();

        $ch = $this->setupCurl($multipart);

        $responseString = curl_exec($ch);
        $info = curl_getinfo($ch);

        $this->assertEquals(200, $info['http_code']);
        $this->assertEquals('application/json', $info['content_type']);

        $response = json_decode($responseString);

        $this->assertObjectHasAttribute('files', $response);

        $this->assertObjectHasAttribute('file', $response->files);
        $this->assertEquals('Hello World', $response->files->file);

        $this->assertObjectHasAttribute('form', $response);
        $this->assertEquals(new \stdClass(), $response->form);
    }

    public function testReadMultipleFilesOnly() {
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

    public function testUploadMultipleFilesOnly() {
        $multipart = new MultipartFormData();
        $multipart->addFile('file1', 'file.txt', 'Hello World', 'text/plain');
        $multipart->addFile('file2', 'file.html', "<html>\nHello World\n</html>", 'text/html');
        $multipart->finish();

        $ch = $this->setupCurl($multipart);

        $responseString = curl_exec($ch);
        $info = curl_getinfo($ch);

        $this->assertEquals(200, $info['http_code']);
        $this->assertEquals('application/json', $info['content_type']);

        $response = json_decode($responseString);

        $this->assertObjectHasAttribute('files', $response);

        $this->assertObjectHasAttribute('file1', $response->files);
        $this->assertEquals('Hello World', $response->files->file1);

        $this->assertObjectHasAttribute('file2', $response->files);
        $this->assertEquals("<html>\nHello World\n</html>", $response->files->file2);

        $this->assertObjectHasAttribute('form', $response);
        $this->assertEquals(new \stdClass(), $response->form);
    }

    public function testReadMixed() {
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

    public function testUploadMixed() {
        $multipart = new MultipartFormData();
        $multipart->addValue('name1', 'value1');
        $multipart->addValue('name2', 'value2');
        $multipart->addFile('file1', 'file.txt', 'Hello World', 'text/plain');
        $multipart->addFile('file2', 'file.html', "<html>\nHello World\n</html>", 'text/html');
        $multipart->finish();

        $ch = $this->setupCurl($multipart);

        $responseString = curl_exec($ch);
        $info = curl_getinfo($ch);

        $this->assertEquals(200, $info['http_code']);
        $this->assertEquals('application/json', $info['content_type']);

        $response = json_decode($responseString);

        $this->assertObjectHasAttribute('files', $response);

        $this->assertObjectHasAttribute('file1', $response->files);
        $this->assertEquals('Hello World', $response->files->file1);

        $this->assertObjectHasAttribute('file2', $response->files);
        $this->assertEquals("<html>\nHello World\n</html>", $response->files->file2);

        $this->assertObjectHasAttribute('form', $response);

        $this->assertObjectHasAttribute('name1', $response->form);
        $this->assertEquals('value1', $response->form->name1);

        $this->assertObjectHasAttribute('name2', $response->form);
        $this->assertEquals('value2', $response->form->name2);

        $this->assertObjectNotHasAttribute('file1', $response->form);

        $this->assertObjectNotHasAttribute('file2', $response->form);
    }

    public function testReadMixedWithDuplicateParameterNames() {
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

    public function testUploadMixedWithDuplicateParameterNames() {
        $multipart = new MultipartFormData();
        $multipart->addValue('name', 'value1');
        $multipart->addFile('file', 'file.txt', 'Hello World', 'text/plain');
        $multipart->addValue('name', 'value2');
        $multipart->addFile('file', 'file.html', "<html>\nHello World\n</html>", 'text/html');
        $multipart->finish();

        $ch = $this->setupCurl($multipart);

        $responseString = curl_exec($ch);
        $info = curl_getinfo($ch);

        $this->assertEquals(200, $info['http_code']);
        $this->assertEquals('application/json', $info['content_type']);

        $response = json_decode($responseString);

        $this->assertObjectHasAttribute('files', $response);

        $this->assertObjectHasAttribute('file', $response->files);
        // httpbin.org ignores subsequent files with the same name...
        $this->assertEquals('Hello World', $response->files->file);

        $this->assertObjectHasAttribute('form', $response);

        $this->assertObjectHasAttribute('name', $response->form);
        $this->assertEquals(['value1', 'value2'], $response->form->name);
    }

    private function setupCurl($multipart) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_URL, 'http://httpbin.org/post');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_READFUNCTION, array($multipart, 'curl_read'));

        $headers = ['Content-Type: ' . $multipart->getContentType()];

        $contentLength = $multipart->getContentLength();
        if ($contentLength >= 0) {
            $headers[] = 'Content-Length: ' .  $contentLength;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        return $ch;
    }
}
