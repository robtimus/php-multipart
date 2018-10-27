<?php
namespace Robtimus\Multipart;

class MultipartMixedTest extends MultipartTestBase {

    public function testReadPlainTextOnly() {
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

    public function testReadWithAlternative() {
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

    public function testMailPlainTextOnly() {
        $fromAddress = $this->getConfigValue('mail.from', false);
        $toAddress = $this->getConfigValue('mail.to', false);
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

    public function testMailWithRelatedOnly() {
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

        $multipart = new MultipartMixed();
        $multipart->addMultipart($related);
        $multipart->addAttachment('file.txt', 'Hello World', 'text/plain');
        $multipart->addAttachment('file.html', "<html>\nHello World\n</html>", 'text/html');
        $multipart->addAttachment('file.base64', base64_encode("<html>\nHello World\n</html>"), 'text/html', -1, 'base64');
        $multipart->finish();

        $headers = ['From: ' . $fromAddress, 'Content-Type: ' . $multipart->getContentType()];
        mail($toAddress, 'MultipartMixedTest.testMailWithRelatedOnly', (string) $multipart, implode("\r\n", $headers));
    }

    public function testMailWithAlternativeAndRelated() {
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
