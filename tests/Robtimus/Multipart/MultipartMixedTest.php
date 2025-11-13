<?php
namespace Robtimus\Multipart;

use InvalidArgumentException;
use ValueError;

class MultipartMixedTest extends MultipartTestBase
{
    public function testAddPartInvalidTypeOfContent(): void
    {
        $multipart = new MultipartMixed();

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
        $multipart = new MultipartMixed();

        try {
            $multipart->addPart('Hello World', '');

            $this->fail('Expected a ValueError');
        } catch (ValueError $e) {
            $this->assertEquals('$contentType must be non-empty', $e->getMessage());
        }
    }

    public function testAddAttachmentEmptyFilename(): void
    {
        $multipart = new MultipartMixed();

        try {
            $multipart->addAttachment('', 'Hello World', 'text/plain');

            $this->fail('Expected a ValueError');
        } catch (ValueError $e) {
            $this->assertEquals('$filename must be non-empty', $e->getMessage());
        }
    }

    public function testAddAttachmentInvalidTypeOfContent(): void
    {
        $multipart = new MultipartMixed();

        try {
            // @phpstan-ignore argument.type
            $multipart->addAttachment('file.txt', 0, 'text/plain');

            $this->fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('$content is incorrectly typed', $e->getMessage());
        }
    }

    public function testAddAttachmentEmptyContentType(): void
    {
        $multipart = new MultipartMixed();

        try {
            $multipart->addAttachment('file.txt', 'Hello World', '');

            $this->fail('Expected a ValueError');
        } catch (ValueError $e) {
            $this->assertEquals('$contentType must be non-empty', $e->getMessage());
        }
    }

    public function testReadPlainTextOnly(): void
    {
        $multipart = new MultipartMixed('test-boundary');
        $multipart->addPart('Hello World', 'text/plain');
        $multipart->addAttachment('file.txt', 'Hello World', 'text/plain');
        $multipart->addAttachment('file.html', "<html>\nHello World\n</html>", 'text/html');
        $multipart->addAttachment('file.base64', base64_encode("<html>\nHello World\n</html>"), 'text/html', -1, 'base64');
        $multipart->finish();

        $expected = <<<'EOS'
--test-boundary
Content-Type: text/plain

Hello World
--test-boundary
Content-Type: text/plain
Content-Disposition: attachment; filename="file.txt"

Hello World
--test-boundary
Content-Type: text/html
Content-Disposition: attachment; filename="file.html"

<html>Hello World</html>
--test-boundary
Content-Type: text/html
Content-Transfer-Encoding: base64
Content-Disposition: attachment; filename="file.base64"

PGh0bWw+CkhlbGxvIFdvcmxkCjwvaHRtbD4=
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
        $this->assertEquals('multipart/mixed; boundary=test-boundary', $multipart->getContentType());
        $this->assertEquals(strlen($expected), $multipart->getContentLength());
    }

    public function testReadWithAlternative(): void
    {
        $alternative = new MultipartAlternative('alternative-boundary');
        $alternative->addPart('Hello World', 'text/plain');
        $alternative->addPart("<html>\nHello World\n</html>", 'text/html');
        $alternative->finish();

        $multipart = new MultipartMixed('test-boundary');
        $multipart->addMultipart($alternative);
        $multipart->addAttachment('file.txt', 'Hello World', 'text/plain');
        $multipart->addAttachment('file.html', "<html>\nHello World\n</html>", 'text/html');
        $multipart->addAttachment('file.base64', base64_encode("<html>\nHello World\n</html>"), 'text/html', -1, 'base64');
        $multipart->finish();

        $expected = <<<'EOS'
--test-boundary
Content-Type: multipart/alternative; boundary=alternative-boundary

--alternative-boundary
Content-Type: text/plain

Hello World
--alternative-boundary
Content-Type: text/html

<html>Hello World</html>
--alternative-boundary--

--test-boundary
Content-Type: text/plain
Content-Disposition: attachment; filename="file.txt"

Hello World
--test-boundary
Content-Type: text/html
Content-Disposition: attachment; filename="file.html"

<html>Hello World</html>
--test-boundary
Content-Type: text/html
Content-Transfer-Encoding: base64
Content-Disposition: attachment; filename="file.base64"

PGh0bWw+CkhlbGxvIFdvcmxkCjwvaHRtbD4=
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
        $this->assertEquals('multipart/mixed; boundary=test-boundary', $multipart->getContentType());
        $this->assertEquals(strlen($expected), $multipart->getContentLength());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testMailPlainTextOnly(): void
    {
        $fromAddress = $this->getStringConfigValue('mail.from', false);
        $toAddress = $this->getStringConfigValue('mail.to', false);
        if (is_null($fromAddress) || is_null($toAddress)) {
            $this->markTestSkipped('mail.from and/or mail.to is missing');
        }

        $this->setIniFromConfig('mail.smtp.server', 'SMTP', false);
        $this->setIniFromConfig('mail.smtp.port', 'smtp_port', false);
        $this->setIniFromConfig('mail.sendmail.path', 'sendmail_path', false);

        $multipart = new MultipartMixed();
        $multipart->addPart('Hello World', 'text/plain');
        $multipart->addAttachment('file.txt', 'Hello World', 'text/plain');
        $multipart->addAttachment('file.html', "<html>\nHello World\n</html>", 'text/html');
        $multipart->addAttachment('file.base64', base64_encode("<html>\nHello World\n</html>"), 'text/html', -1, 'base64');
        $multipart->finish();

        $headers = ['From: ' . $fromAddress, 'Content-Type: ' . $multipart->getContentType()];
        mail($toAddress, 'MultipartMixedTest.testMailPlainTextOnly', (string) $multipart, implode("\r\n", $headers));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testMailWithRelatedOnly(): void
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

        $related = new MultipartRelated();
        $related->addPart('<html>Hello World <img src="cid:inline_file">', 'text/html');
        // @phpstan-ignore argument.type
        $related->addInlineFile('inline_file', 'test.png', $imageContent, 'image/png', $imageSize, 'base64');
        $related->finish();

        $multipart = new MultipartMixed();
        $multipart->addMultipart($related);
        $multipart->addAttachment('file.txt', 'Hello World', 'text/plain');
        $multipart->addAttachment('file.html', "<html>\nHello World\n</html>", 'text/html');
        $multipart->addAttachment('file.base64', base64_encode("<html>\nHello World\n</html>"), 'text/html', -1, 'base64');
        $multipart->finish();

        $headers = ['From: ' . $fromAddress, 'Content-Type: ' . $multipart->getContentType()];
        mail($toAddress, 'MultipartMixedTest.testMailWithRelatedOnly', (string) $multipart, implode("\r\n", $headers));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testMailWithAlternativeAndRelated(): void
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

        $related = new MultipartRelated();
        $related->addPart('<html>Hello World <img src="cid:inline_file">', 'text/html');
        // @phpstan-ignore argument.type
        $related->addInlineFile('inline_file', 'test.png', $imageContent, 'image/png', $imageSize, 'base64');
        $related->finish();

        $alternative = new MultipartAlternative();
        $alternative->addPart('Hello World', 'text/plain');
        $alternative->addMultipart($related);
        $alternative->finish();

        $multipart = new MultipartMixed();
        $multipart->addMultipart($alternative);
        $multipart->addAttachment('file.txt', 'Hello World', 'text/plain');
        $multipart->addAttachment('file.html', "<html>\nHello World\n</html>", 'text/html');
        $multipart->addAttachment('file.base64', base64_encode("<html>\nHello World\n</html>"), 'text/html', -1, 'base64');
        $multipart->finish();

        $headers = ['From: ' . $fromAddress, 'Content-Type: ' . $multipart->getContentType()];
        mail($toAddress, 'MultipartMixedTest.testMailWithAlternativeAndRelated', (string) $multipart, implode("\r\n", $headers));
    }
}
