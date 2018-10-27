<?php
namespace Robtimus\Multipart;

class MultipartAlternativeTest extends MultipartTestBase {

    public function testRead() {
        $multipart = new MultipartAlternative('test-boundary');
        $multipart->addAlternative('Hello World', 'text/plain');
        $multipart->addAlternative("<html>\nHello World\n</html>", 'text/html');
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

    public function testMail() {
        $fromAddress = $this->getConfigValue('mail.from', false);
        $toAddress = $this->getConfigValue('mail.to', false);
        if (is_null($fromAddress) || is_null($toAddress)) {
            $this->markTestSkipped('mail.from and/or mail.to is missing');
        }

        $this->setIniFromConfig('mail.smtp.server', 'SMTP', false);
        $this->setIniFromConfig('mail.smtp.port', 'smtp_port', false);
        $this->setIniFromConfig('mail.sendmail.path', 'sendmail_path', false);

        $multipart = new MultipartAlternative('test-boundary');
        $multipart->addAlternative('Hello World', 'text/plain');
        $multipart->addAlternative("<html>\nGoodbye World\n</html>", 'text/html');
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

        $multipart = new MultipartAlternative('test-boundary');
        $multipart->addAlternative('Hello World', 'text/plain');
        $multipart->addAlternative(base64_encode("<html>\nGoodbye World\n</html>"), 'text/html', -1, 'base64');
        $multipart->finish();

        $headers = ['From: ' . $fromAddress, 'Content-Type: ' . $multipart->getContentType()];
        mail($toAddress, 'MultipartAlternativeTest.testMailWithTransferEncoding', (string) $multipart, implode("\r\n", $headers));
    }
}
