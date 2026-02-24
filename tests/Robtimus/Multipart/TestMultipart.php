<?php
namespace Robtimus\Multipart;

use InvalidArgumentException;

class TestMultipart extends Multipart
{
    /**
     * @param string $boundary
     * @param string $contentType
     *
     * @throws InvalidArgumentException If the given content type is empty.
     */
    public function __construct(string $boundary = '', string $contentType = 'multipart/test')
    {
        parent::__construct($boundary, $contentType);
    }

    /**
     * @param string|resource|callable(int):string $content
     */
    public function add(mixed $content, int $length = -1): void
    {
        $this->addContent($content, $length);
    }
}
