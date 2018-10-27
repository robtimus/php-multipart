<?php
namespace Robtimus\Multipart;

class MultipartAlternativeTest extends MultipartTestBase {

    public function testRead() {
        $multipart = new MultipartAlternative('test-boundary');
        $multipart->addPart('Hello World', 'text/plain');
        $multipart->addPart("<html>\nHello World\n</html>", 'text/html');
        $multipart->finish();

        $expected = <<<'EOS'
--test-boundary
Content-Type: text/plain

Hello World
--test-boundary
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
        $this->assertEquals('multipart/alternative; boundary=test-boundary', $multipart->getContentType());
        $this->assertEquals(strlen($expected), $multipart->getContentLength());
    }

    public function testReadWithTransferEncoding() {
        $multipart = new MultipartAlternative('test-boundary');
        $multipart->addPart('Hello World', 'text/plain');
        $multipart->addPart(base64_encode("<html>\nHello World\n</html>"), 'text/html', -1, 'base64');
        $multipart->finish();

        $expected = <<<'EOS'
--test-boundary
Content-Type: text/plain

Hello World
--test-boundary
Content-Type: text/html
Content-Transfer-Encoding: base64

PGh0bWw+CkhlbGxvIFdvcmxkCjwvaHRtbD4=
--test-boundary--

EOS;
        $expected = str_replace("\r\n", "\n", $expected);
        $expected = str_replace("\n", "\r\n", $expected);

        $result = '';
        while ($data = $multipart->read(20)) {
            $result .= $data;
        }

        $this->assertEquals($expected, $result);
        $this->assertEquals('multipart/alternative; boundary=test-boundary', $multipart->getContentType());
        $this->assertEquals(strlen($expected), $multipart->getContentLength());
    }

    public function testReadWithRelated() {
        $related = new MultipartRelated('related-boundary');
        $related->addPart("<html>\nHello World\n</html>", 'text/html');
        $related->addInlineFile('inline_file', 'inline.txt', 'Inline Hello World', 'text/plain');
        $related->finish();

        $multipart = new MultipartAlternative('test-boundary');
        $multipart->addPart('Hello World', 'text/plain');
        $multipart->addMultipart($related);
        $multipart->finish();

        $expected = <<<'EOS'
--test-boundary
Content-Type: text/plain

Hello World
--test-boundary
Content-Type: multipart/related; boundary=related-boundary

--related-boundary
Content-Type: text/html

<html>Hello World</html>
--related-boundary
Content-Type: text/plain
Content-ID: inline_file
Content-Disposition: inline; filename="inline.txt"

Inline Hello World
--related-boundary--

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
        $this->assertEquals('multipart/alternative; boundary=test-boundary', $multipart->getContentType());
        $this->assertEquals(strlen($expected), $multipart->getContentLength());
    }

    public function testMail() {
        $fromAddress = $this->getConfigValue('mail.from', false);
        $toAddress = $this->getConfigValue('mail.to', false);
        if (is_null($fromAddress) || is_null($toAddress)) {
            $this->markTestSkipped('mail.from and/or mail.to is missing');
        }

        $this->setIniFromConfig('mail.smtp.server', 'SMTP', false);
        $this->setIniFromConfig('mail.smtp.port', 'smtp_port', false);
        $this->setIniFromConfig('mail.sendmail.path', 'sendmail_path', false);

        $multipart = new MultipartAlternative();
        $multipart->addPart('Hello World', 'text/plain');
        $multipart->addPart("<html>\nGoodbye World\n</html>", 'text/html');
        $multipart->finish();

        $headers = ['From: ' . $fromAddress, 'Content-Type: ' . $multipart->getContentType()];
        mail($toAddress, 'MultipartAlternativeTest.testMail', (string) $multipart, implode("\r\n", $headers));
    }

    public function testMailWithTransferEncoding() {
        $fromAddress = $this->getConfigValue('mail.from', false);
        $toAddress = $this->getConfigValue('mail.to', false);
        if (is_null($fromAddress) || is_null($toAddress)) {
            $this->markTestSkipped('mail.from and/or mail.to is missing');
        }

        $this->setIniFromConfig('mail.smtp.server', 'SMTP', false);
        $this->setIniFromConfig('mail.smtp.port', 'smtp_port', false);
        $this->setIniFromConfig('mail.sendmail.path', 'sendmail_path', false);

        $multipart = new MultipartAlternative();
        $multipart->addPart('Hello World', 'text/plain');
        $multipart->addPart(base64_encode("<html>\nGoodbye World\n</html>"), 'text/html', -1, 'base64');
        $multipart->finish();

        $headers = ['From: ' . $fromAddress, 'Content-Type: ' . $multipart->getContentType()];
        mail($toAddress, 'MultipartAlternativeTest.testMailWithTransferEncoding', (string) $multipart, implode("\r\n", $headers));
    }

    public function testMailWithRelated() {
        $fromAddress = $this->getConfigValue('mail.from', false);
        $toAddress = $this->getConfigValue('mail.to', false);
        if (is_null($fromAddress) || is_null($toAddress)) {
            $this->markTestSkipped('mail.from and/or mail.to is missing');
        }

        $this->setIniFromConfig('mail.smtp.server', 'SMTP', false);
        $this->setIniFromConfig('mail.smtp.port', 'smtp_port', false);
        $this->setIniFromConfig('mail.sendmail.path', 'sendmail_path', false);

        $imagePath = dirname(__FILE__) . '/../../test.gif';
        $imageContent = base64_encode(file_get_contents($imagePath));
        $imageSize = filesize($imagePath);

        $related = new MultipartRelated();
        $related->addPart('<html>Hello World <img src="cid:inline_file">', 'text/html');
        $related->addInlineFile('inline_file', 'test.gif', $imageContent, 'image/gif', $imageSize, 'base64');
        $related->finish();

        $multipart = new MultipartAlternative();
        $multipart->addPart('Hello World', 'text/plain');
        $multipart->addMultipart($related);
        $multipart->finish();

        $headers = ['From: ' . $fromAddress, 'Content-Type: ' . $multipart->getContentType()];
        mail($toAddress, 'MultipartAlternativeTest.testMailWithRelated', (string) $multipart, implode("\r\n", $headers));
    }
}
