<?php
namespace Robtimus\Multipart;

class MultipartRelatedTest extends MultipartTestBase {

    public function testRead() {
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

    public function testMail() {
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

        $multipart = new MultipartRelated();
        $multipart->addPart('<html>Hello World <img src="cid:inline_file">', 'text/html');
        $multipart->addInlineFile('inline_file', 'test.gif', $imageContent, 'image/gif', $imageSize, 'base64');
        $multipart->finish();

        $headers = ['From: ' . $fromAddress, 'Content-Type: ' . $multipart->getContentType()];
        mail($toAddress, 'MultipartRelatedTest.testMail', (string) $multipart, implode("\r\n", $headers));
    }
}
