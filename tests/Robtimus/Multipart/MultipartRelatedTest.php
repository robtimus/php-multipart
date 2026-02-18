<?php
namespace Robtimus\Multipart;

use InvalidArgumentException;

class MultipartRelatedTest extends MultipartTestBase
{
    public function testAddPartInvalidTypeOfContent(): void
    {
        $multipart = new MultipartRelated();

        try {
            // @phpstan-ignore argument.type
            $multipart->addPart(0, 'text/plain');

            $this->fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('$content is incorrectly typed', $e->getMessage());
        }
    }

    public function testAddPartEmptyContentType(): void
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addPart('Hello World', '');

            $this->fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('$contentType must be non-empty', $e->getMessage());
        }
    }

    public function testAddInlineFileEmptyContentID(): void
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addInlineFile('', 'file.txt', 'Hello World', 'text/plain');

            $this->fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('$contentID must be non-empty', $e->getMessage());
        }
    }

    public function testAddInlineFileEmptyFilename(): void
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addInlineFile('cid', '', 'Hello World', 'text/plain');

            $this->fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('$filename must be non-empty', $e->getMessage());
        }
    }

    public function testAddInlineFileInvalidTypeOfContent(): void
    {
        $multipart = new MultipartRelated();

        try {
            // @phpstan-ignore argument.type
            $multipart->addInlineFile('cid', 'file.txt', 0, 'text/plain');

            $this->fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('$content is incorrectly typed', $e->getMessage());
        }
    }

    public function testAddInlineFileEmptyContentType(): void
    {
        $multipart = new MultipartRelated();

        try {
            $multipart->addInlineFile('cid', 'file.txt', 'Hello World', '');

            $this->fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('$contentType must be non-empty', $e->getMessage());
        }
    }

    public function testRead(): void
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
    public function testMail(): void
    {
        $fromAddress = $this->getStringConfigValue('mail.from', false);
        $toAddress = $this->getStringConfigValue('mail.to', false);
        if (is_null($fromAddress) || is_null($toAddress)) {
            $this->markTestSkipped('mail.from and/or mail.to is missing');
        }

        $this->setIniFromConfig('mail.smtp.server', 'SMTP', false);
        $this->setIniFromConfig('mail.smtp.port', 'smtp_port', false);
        $this->setIniFromConfig('mail.sendmail.path', 'sendmail_path', false);

        $imagePath = dirname(__FILE__) . '/../../test.png';
        // @phpstan-ignore argument.type
        $imageContent = base64_encode(file_get_contents($imagePath));
        $imageSize = filesize($imagePath);

        $multipart = new MultipartRelated();
        $multipart->addPart('<html>Hello World <img src="cid:inline_file">', 'text/html');
        // @phpstan-ignore argument.type
        $multipart->addInlineFile('inline_file', 'test.png', $imageContent, 'image/png', $imageSize, 'base64');
        $multipart->finish();

        $headers = ['From: ' . $fromAddress, 'Content-Type: ' . $multipart->getContentType()];
        mail($toAddress, 'MultipartRelatedTest.testMail', (string) $multipart, implode("\r\n", $headers));
    }
}
