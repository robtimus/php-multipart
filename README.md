# Multipart

A library to support (streaming) multiparts.

## Supported multipart types

### multipart/form-data

To create a multipart/form-data object, create a `MultipartFormData` object, add the form fields, and call `finish()`. There are two methods for adding form fields:

* `addValue($name, $value)` adds a string value with the given name.
* `addFile($name, $filename, $content, $contentType, $contentLength = -1)` adds a file with the given name. The filename, [content](#multipart-files) and content type are required; the content length is optional, and will be ignored if the content is a string.

An example:

    // the multipart object can take an optional pre-existing boundary
    $multipart = new MultipartFormData();
    $multipart->addValue('name', 'Rob');
    $multipart->addFile('file', 'file.txt', 'Hello World', 'text/plain');
    $multipart->finish();

#### Multiple values or files with the same parameter name

`MultipartFormData` follows [RFC 7578](https://tools.ietf.org/html/rfc7578), and not [RFC 2388](https://tools.ietf.org/html/rfc2388). This means that multiple values or files with the same parameter name are not sent with a multipart/mixed field but instead as separate parts.

PHP servers require multiple values or files to be sent with a name that ends with `[]`. Because `MultipartFormData` is written to support also other server types that do not have this requirement, it is up to the caller to add these. For instance:

    $multipart = new MultipartFormDataBuilder();
    $multipart->addValue('name', 'Rob');
    $multipart->addFile('file[]', 'file.txt', 'Hello World', 'text/plain');
    $multipart->addFile('file[]', 'file.html', '<html>Hello World</html>', 'text/html');
    $multipart->finish();

## Multipart files

For multiparts that support files, the content can be given in one of three ways:

* As a string. The content length will be ignored.
* As a resource that can be read using `fread`. It is up to the caller to close this resource.
* As a callable that takes a length, and returns a string that is not larger than the given length. If there is nothing more to read it should return the empty string.

Examples, using a `MultipartFormData` object:

    // content length is not necessary
    $multipart->addFile('file1', 'file.txt', 'Hello World', 'text/plain');

    // make sure to close the resource after the request has been sent
    $resource = fopen('file.html');
    $multipart->addFile('file.html', $resource, 'text/html', filesize('file.html'));

    // assume that class MyResource exists and has a function read($length)
    $myResource = new MyResource(...);
    $multipart->addFile('file.bin', array($myResource, 'read'), 'application/octet-stream');

## cURL support

To send a multipart object with a cURL request, you need to follow some steps:

* Set the request type using `CURLOPT_CUSTOMREQUEST`.
* Set the `CURLOPT_UPLOAD` option to `true`.
* Set the object's `curl_read` method as the `CURLOPT_READFUNCTION`.
* Make sure the `Content-Type` and `Content-Length` headers are set. Note that the `Content-Length` header is optional.

For instance:

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_UPLOAD, true);
    curl_setopt($ch, CURLOPT_READFUNCTION, array($multipart, 'curl_read'));
    
    $headers = ['Content-Type: ' . $multipart->getContentType()];
    $contentLength = $multipart->getContentLength();
    if ($contentLength >= 0) {
        $headers[] = 'Content-Length: ' .  $contentLength;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

## Non-streaming support

If streaming is not possible (e.g. because a string is required), you can buffer a multipart object in-memory by calling the `buffer` method. This method takes an optional buffer size, and returns the buffered contents. The content length will be set accordingly. Note that you should do this before calling `read` (or `curl_read`), otherwise the buffered contents may not contain all desired contents (especially if you're using resources or callables).

`Multipart.__toString()` has been overridden to buffer the multipart object as well, so you can achieve the same by casting a multipart object to `string`. The difference is that `buffer` requires the multipart object to be finished.
