<?php

namespace Drewlabs\Dompdf;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;

/**
 * Creates a Psr7 Stream Response object for DomPdf
 * 
 * @package Drewlabs\Dompdf
 */
class Psr7StreamResponse extends Response
{
    /**
     * @var StreamInterface
     */
    protected $stream;

    /**
     * 
     * @var int
     */
    protected $offset;

    /**
     * 
     * @var int
     */
    protected $maxLength;

    /**
     * 
     * @var string
     */
    private $mimeType;

    /**
     * Creates a {@see Psr7StreamResponse} class instance
     * 
     * @param StreamInterface $stream 
     * @param int $status 
     * @param array $headers 
     * @return void 
     * @throws InvalidArgumentException 
     */
    public function __construct(StreamInterface $stream, $status = 200, $headers = [])
    {
        parent::__construct(null, $status, $headers);
        $this->setStream($stream);
    }

    /**
     * Creates a Psr7StreamResponse from a disk path
     * 
     * @param string|StreamInterface|\SplFileInfo $streamOrPath 
     * @param int $status 
     * @param array $headers 
     * @return static 
     */
    public static function new($streamOrPath, $status = 200, $headers = [])
    {
        if ($streamOrPath instanceof StreamInterface) {
            return self::createStreamResponse($streamOrPath, $status, $headers);
        }
        if ($streamOrPath instanceof \SplFileInfo) {
            $streamOrPath = ($realpath = $streamOrPath->getRealPath()) === false ? '' : $realpath;
        }
        if (null === $streamOrPath) {
            return self::createStreamResponse((new Psr17Factory)->createStream(''), $status, $headers);
        }
        if (!is_string($streamOrPath)) {
            throw new InvalidArgumentException('$path argument must be of type string, StreamInterface  or \SplFileInfo::class');
        }
        // In case the path is a string but not a valid path, we simply create a stream  with the string provided
        if (is_string($streamOrPath) && !@is_file($streamOrPath)) {
            return self::createStreamResponse((new Psr17Factory)->createStream($streamOrPath), $status, $headers);
        }
        $response = new static((new Psr17Factory)->createStreamFromFile($streamOrPath), $status, $headers ?? []);
        // Merge content type into the response headers
        return $response->withContentType(self::getMimesType($streamOrPath));
    }


    /**
     * Creates a stream response object from a raw string or a Psr7 stream instance
     * 
     * @param StreamInterface $stream 
     * @param int $status 
     * @param array $headers 
     * @return static 
     * @throws InvalidArgumentException 
     */
    private static function createStreamResponse(StreamInterface $stream, $status = 200, $headers = [])
    {
        $response = new static($stream, $status, $headers ?? []);
        return in_array('Content-Type', $headers ?? [], false) ? $response : $response->withContentType('application/octect-stream');
    }

    /**
     * Sets the file to stream.
     *
     * @param StreamInterface $stream
     *
     * @return $this
     */
    public function setStream(StreamInterface $stream)
    {
        $this->stream = $stream;
        return $this;
    }

    /**
     * @return StreamInterface
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Sets the Content-Disposition header with the given filename.
     *
     * @param string $filename Use this UTF-8 encoded filename instead of the real name of the file
     * @param string $disposition ResponseHeaderBag::DISPOSITION_INLINE or ResponseHeaderBag::DISPOSITION_ATTACHMENT
     *
     * @return static
     */
    public function setContentDisposition($filename, $disposition = 'attachment')
    {
        $value = $this->headers->makeDisposition($disposition, $filename);
        $this->headers->set('Content-Disposition', $value);
        return $this;
    }

    /**
     * Set the request content type header
     * 
     * @param string $mimeType 
     * @return static 
     * @throws InvalidArgumentException 
     */
    public function withContentType(string $mimeType)
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function prepare(Request $request)
    {
        $this->headers->set('Content-Length', $this->stream->getSize());
        if (!$this->headers->has('Accept-Ranges')) {
            // Only accept ranges on safe HTTP methods
            $this->headers->set('Accept-Ranges', $request->isMethodSafe(false) ? 'bytes' : 'none');
        }
        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', $this->mimeType ?? 'application/octet-stream');
        }
        if ('HTTP/1.0' !== $request->server->get('SERVER_PROTOCOL')) {
            $this->setProtocolVersion('1.1');
        }
        $this->ensureIEOverSSLCompatibility($request);
        $this->offset = 0;
        $this->maxLength = -1;
        $this->processRequestRange($request);
        return $this;
    }

    /**
     * Sends the file.
     *
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function sendContent()
    {
        if (!$this->isSuccessful()) {
            return parent::sendContent();
        }
        if (0 === $this->maxLength) {
            return $this;
        }
        $this->stream->seek($this->offset);
        $this->maxLength = $this->maxLength === -1 ? $this->stream->getSize() - $this->offset : $this->maxLength;
        $this->content = $this->stream->read($this->maxLength);
        return parent::sendContent();
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException when the content is not null
     */
    #[\ReturnTypeWillChange]
    public function setContent($content)
    {
        if (null !== $content) {
            throw new \LogicException('The content cannot be set on a Psr7StreamResponse instance.');
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return false
     */
    #[\ReturnTypeWillChange]
    public function getContent()
    {
        return false;
    }


    /**
     * Processes request range & set the request ranges header
     * 
     * @param Request $request 
     * @return void 
     * @throws RuntimeException 
     * @throws InvalidArgumentException 
     */
    private function processRequestRange(Request $request)
    {
        if (!$request->headers->has('Range')) {
            return;
        }
        if (!(!$request->headers->has('If-Range') || $this->hasValidIfRangeHeader($request->headers->get('If-Range')))) {
            return;
        }

        $range = $request->headers->get('Range');
        $size = $this->stream->getSize();
        [$start, $end] = explode('-', substr($range, 6), 2) + array(0);
        $end = '' === ($value = trim($end)) ? $size - 1 : intval($value);
        if ('' === trim($start)) {
            $start = $size - $end;
            $end = $size - 1;
        } else {
            $start = (int)$start;
        }
        if ($start <= $end) {
            return;
        }
        if ($start < 0 || $end > $size - 1) {
            $this->setStatusCode(416);
            $this->headers->set('Content-Range', sprintf('bytes */%s', $size));
            return;
        }
        if (0 !== $start || $end !== $size - 1) {
            $this->maxLength = $end < $size ? $end - $start + 1 : -1;
            $this->offset = $start;
            $this->setStatusCode(206);
            $this->headers->set('Content-Range', sprintf('bytes %s-%s/%s', $start, $end, $size));
            $this->headers->set('Content-Length', $end - $start + 1);
        }
    }


    /**
     * Check is the request header hav valid range
     * 
     * @param mixed $header 
     * @return bool 
     * @throws RuntimeException 
     */
    private function hasValidIfRangeHeader($header)
    {
        if ($this->getEtag() === $header) {
            return true;
        }
        if (null === ($lastModified = $this->getLastModified())) {
            return false;
        }
        return $lastModified->format('D, d M Y H:i:s') . ' GMT' === $header;
    }

    /**
     * Get the content type from a document path using symfony mimes library
     *  or fallback to `application/octect-stream` if library is missing
     * 
     * @param string $path 
     * @return string 
     */
    private static function getMimesType(string $path)
    {
        if (class_exists(MimeTypes::class)) {
            MimeTypes::getDefault()->guessMimeType($path);
        }
        return 'application/octect-stream';
    }
}
