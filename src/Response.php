<?php

namespace Drewlabs\Dompdf;

use Symfony\Component\HttpFoundation\Response as BaseResponse;

class Response extends BaseResponse
{
    /**
     * Document name
     * 
     * @var string
     */
    private $name;

    /**
     * Content disposition header
     * 
     * @var string
     */
    private $disposition;

    /**
     * 
     * @var DomPdfable
     */
    private $dompdf;

    public function __construct()
    {
        parent::__construct('');
    }

    /**
     * Creates a new response
     * 
     * @param DomPdfable $content 
     * @param string $name 
     * @param string $disposition 
     * @return Response 
     */
    public static function new(
        DomPdfable $dompdf,
        string $name = 'document.php',
        string $disposition = 'attachement'
    ) {
        $object = new self;
        $object->dompdf = $dompdf;
        $object->name = $name;
        $object->disposition = $disposition;
        return $object;
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
            throw new \LogicException('The content cannot be set on a \Drewlabs\Dompdf\Response instance.');
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
     * Set the PDF instance on the response object
     * 
     * @param DomPdfable $pdf 
     * @return self
     */
    public function setPDF(DomPdfable $pdf)
    {
        $this->dompdf = $pdf;

        return $this;
    }

    /**
     * Set content disposition header parameters on the response object
     * 
     * @param string $name 
     * @param string $disposition 
     * @return self 
     */
    public function setContentDisposition(string $name, $disposition = 'attachment')
    {
        $this->name = $name;
        $this->disposition = $disposition;
        return $this;
    }

    public function sendContent()
    {
        return Psr7StreamResponse::new($this->dompdf->print(), 200, ['Content-Type' => 'application/pdf'])
            ->setContentDisposition($this->name ?? 'document.pdf', $this->disposition ?? 'attachement')
            ->sendContent();
    }
}
