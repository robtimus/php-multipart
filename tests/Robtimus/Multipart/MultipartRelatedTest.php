<?php
namespace Robtimus\Multipart;

class MultipartRelatedTest extends MultipartTestBase
{
    public function testAddPartInvalidTypeOfContent()
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addPart(0, 'text/plain');

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$content is incorrectly typed', $e->getMessage());
        }
    }

    public function testAddPartInvalidTypeOfContentType()
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addPart('Hello World', 0);

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$contentType is incorrectly typed', $e->getMessage());
        }
    }

    public function testAddPartEmptyContentType()
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addPart('Hello World', '');

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$contentType must be non-empty', $e->getMessage());
        }
    }

    public function testAddPartInvalidTypeOfContentLength()
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addPart('Hello World', 'text/plain', '');

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$contentLength is incorrectly typed', $e->getMessage());
        }
    }

    public function testAddPartInvalidTypeOfContentTransferEncoding()
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addPart('Hello World', 'text/plain', -1, 0);

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$contentTransferEncoding is incorrectly typed', $e->getMessage());
        }
    }

    public function testAddInlineFileInvalidTypeOfContentID()
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addInlineFile(0, 'file.txt', 'Hello World', 'text/plain');

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$contentID is incorrectly typed', $e->getMessage());
        }
    }

    public function testAddInlineFileEmptyContentID()
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addInlineFile('', 'file.txt', 'Hello World', 'text/plain');

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$contentID must be non-empty', $e->getMessage());
        }
    }

    public function testAddInlineFileInvalidTypeOfFilename()
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addInlineFile('cid', 0, 'Hello World', 'text/plain');

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$filename is incorrectly typed', $e->getMessage());
        }
    }

    public function testAddInlineFileEmptyFilename()
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addInlineFile('cid', '', 'Hello World', 'text/plain');

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$filename must be non-empty', $e->getMessage());
        }
    }

    public function testAddInlineFileInvalidTypeOfContent()
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addInlineFile('cid', 'file.txt', 0, 'text/plain');

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$content is incorrectly typed', $e->getMessage());
        }
    }

    public function testAddInlineFileInvalidTypeOfContentType()
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addInlineFile('cid', 'file.txt', 'Hello World', 0);

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$contentType is incorrectly typed', $e->getMessage());
        }
    }

    public function testAddInlineFileEmptyContentType()
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addInlineFile('cid', 'file.txt', 'Hello World', '');

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$contentType must be non-empty', $e->getMessage());
        }
    }

    public function testAddInlineFileInvalidTypeOfContentLength()
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addInlineFile('cid', 'file.txt', 'Hello World', 'text/plain', '');

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$contentLength is incorrectly typed', $e->getMessage());
        }
    }

    public function testAddInlineFileInvalidTypeOfContentTransferEncoding()
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addInlineFile('cid', 'file.txt', 'Hello World', 'text/plain', -1, 0);

            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('$contentTransferEncoding is incorrectly typed', $e->getMessage());
        }
    }

    public function testRead()
    {
        $multipart = new MultipartRelated('test-boundary');
        $multipart->addPart("<html>\nHello World\n</html>", 'text/html');
        $multipart->addInlineFile('inline_file', 'file.txt', 'Hello World', 'text/plain');
        $multipart->finish();

        $expected = <<<'EOS'
--test-boundary
Content-Type: text/html

<html>Hello World</html>
--test-boundary
Content-Type: text/plain
Content-ID: inline_file
Content-Disposition: inline; filename="file.txt"

Hello World
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
        $this->assertEquals('multipart/related; boundary=test-boundary', $multipart->getContentType());
        $this->assertEquals(strlen($expected), $multipart->getContentLength());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testMail()
    {
        $fromAddress = $this->getConfigValue('mail.from', false);
        $toAddress = $this->getConfigValue('mail.to', false);
        if (is_null($fromAddress) || is_null($toAddress)) {
            $this->markTestSkipped('mail.from and/or mail.to is missing');
        }

        $this->setIniFromConfig('mail.smtp.server', 'SMTP', false);
        $this->setIniFromConfig('mail.smtp.port', 'smtp_port', false);
        $this->setIniFromConfig('mail.sendmail.path', 'sendmail_path', false);

        $imagePath = dirname(__FILE__) . '/../../test.png';
        $imageContent = base64_encode(file_get_contents($imagePath));
        $imageSize = filesize($imagePath);

        $multipart = new MultipartRelated();
        $multipart->addPart('<html>Hello World <img src="cid:inline_file">', 'text/html');
        $multipart->addInlineFile('inline_file', 'test.png', $imageContent, 'image/png', $imageSize, 'base64');
        $multipart->finish();

        $headers = ['From: ' . $fromAddress, 'Content-Type: ' . $multipart->getContentType()];
        mail($toAddress, 'MultipartRelatedTest.testMail', (string) $multipart, implode("\r\n", $headers));
    }
}
