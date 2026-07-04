<?php
namespace Robtimus\Multipart;

use InvalidArgumentException;

class TestMultipart extends Multipart
{
    /**
     * @param string|int $boundary
     * @param string|int $contentType
     *
     * @throws InvalidArgumentException If either argument is not a string.
     * @throws InvalidArgumentException If the given content type is empty.
     */
    public function __construct($boundary = '', $contentType = 'multipart/test')
    {
        parent::__construct($boundary, $contentType);
    }

    public function add($content, $length = -1)
    {
        $this->addContent($content, $length);
    }
}
